<?php
/**
 * Reconciliation component.
 *
 * @package QuixDevsStockNotifier
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Durable operational records require current uncached reads; no WordPress object API exists for these owned tables.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers and query fragments are fixed or allowlisted; all external values use prepare().

namespace QuixDevs\StockNotifier\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use QuixDevs\StockNotifier\Data\Notification_Repository;
use QuixDevs\StockNotifier\Notifications\{Notification_Manager, Notification_Queue};
use QuixDevs\StockNotifier\Support\{Settings, Lock_Manager};
use QuixDevs\StockNotifier\Recipients\Recipient_Resolver;

/** Bounded queue repair each minute; a catalog pass at most once per day. */
final class Reconciliation {
	/**
	 * Connect the collaborating services for this component.
	 *
	 * @param Notification_Repository $repository Repository.
	 * @param Stock_Monitor           $monitor Monitor.
	 * @param Notification_Manager    $manager Manager.
	 */
	public function __construct( private Notification_Repository $repository, private Stock_Monitor $monitor, private Notification_Manager $manager ) {}
	/**
	 * Read the next bounded catalog identity batch.
	 *
	 * @param int $cursor Cursor.
	 * @param int $max Max.
	 * @return array
	 */
	public function ids( int $cursor, int $max = PHP_INT_MAX ): array {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM %i WHERE ID>%d AND ID<=%d AND post_type IN ('product','product_variation') AND post_status NOT IN ('trash','auto-draft') ORDER BY ID LIMIT 100", $wpdb->posts, $cursor, $max ) ) );
	}
	/**
	 * Recreate missing jobs without automatically retrying ambiguous sends.
	 *
	 * @return void
	 */
	public function repair(): void {
		global $wpdb;
		$queue = new Notification_Queue();
		// Interrupted workers never enter the automatic retry path.
		$interrupted = $wpdb->get_results( $wpdb->prepare( "SELECT d.id,d.event_id,e.item_id FROM %i d INNER JOIN %i e ON e.id=d.event_id WHERE d.status='sending' AND d.updated_at<%s LIMIT 100", $this->repository->table( 'deliveries' ), $this->repository->table( 'events' ), gmdate( 'Y-m-d H:i:s', time() - 900 ) ) );
		foreach ( $interrupted as $row ) {
			try {
				Lock_Manager::run(
					'item:' . $row->item_id,
					function () use ( $row ) {
							$this->repository->update(
								'deliveries',
								array(
									'status'     => 'unknown',
									'error_code' => 'worker_interrupted',
								),
								array(
									'id'     => $row->id,
									'status' => 'sending',
								)
							);
							$this->repository->summarize( (int) $row->event_id );
					}
				);
			} catch ( \RuntimeException $error ) {
				// A live sender still owns the connection lock; leave its record untouched.
				continue;
			}
		}
		$events = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE status IN ('unresolved','paused') AND retry_at<=%s ORDER BY retry_at,id LIMIT 100", $this->repository->table( 'events' ), Notification_Repository::now() ) );
		foreach ( $events as $id ) {
			$queue->enqueue( (int) $id );
		}
		$cursor = (int) get_option( 'quixdevs_stocknotifier_repair_cursor', 0 );
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT id,event_id,next_at FROM %i WHERE status='queued' AND id>%d ORDER BY id LIMIT 100", $this->repository->table( 'deliveries' ), $cursor ) );
		update_option( 'quixdevs_stocknotifier_repair_cursor', count( $rows ) < 100 ? 0 : (int) end( $rows )->id, false );
		foreach ( $rows as $row ) {
			$delay = max( 0, strtotime( $row->next_at . ' UTC' ) - time() );
			if ( 'paused' === $this->repository->one( 'deliveries', (int) $row->id )->status ) {
				$queue->enqueue( (int) $row->event_id );
			} else {
				$queue->enqueue( (int) $row->event_id, (int) $row->id, $delay );
			}
		}
	}
	/**
	 * Execute a bounded operation under connection-owned concurrency protection.
	 *
	 * @return void
	 */
	public function run(): void {
		Lock_Manager::run(
			'reconciliation',
			function () {
				$this->repair();
				if ( time() >= (int) get_option( 'quixdevs_stocknotifier_next_scan', 0 ) ) {
					$cursor = (int) get_option( 'quixdevs_stocknotifier_scan_cursor', 0 );
					$ids    = $this->ids( $cursor );
					foreach ( $ids as $id ) {
						$this->monitor->observe( $this->monitor->fresh( $id ) );
					}
					update_option( 'quixdevs_stocknotifier_scan_cursor', $ids ? max( $ids ) : 0, false );
					if ( count( $ids ) < 100 ) {
						update_option( 'quixdevs_stocknotifier_scan_cursor', 0, false );
						update_option( 'quixdevs_stocknotifier_next_scan', time() + DAY_IN_SECONDS, false );
					}
					$this->cleanup();
				}
				update_option( 'quixdevs_stocknotifier_last_reconciliation', time(), false );
			}
		);
	}
	/**
	 * Remove expired terminal history in bounded batches.
	 *
	 * @return void
	 */
	public function cleanup(): void {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at<%s LIMIT 500', $this->repository->table( 'activity' ), $cutoff ) );
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT e.id FROM %i e WHERE e.created_at<%s AND e.status IN ('accepted','cancelled','failed') AND NOT EXISTS (SELECT 1 FROM %i d WHERE d.event_id=e.id AND d.status IN ('unknown','sending')) ORDER BY e.id LIMIT 100", $this->repository->table( 'events' ), $this->repository->table( 'deliveries' ), $cutoff ) );
		foreach ( $ids as $id ) {
			$wpdb->delete( $this->repository->table( 'deliveries' ), array( 'event_id' => (int) $id ) );
			$wpdb->delete( $this->repository->table( 'events' ), array( 'id' => (int) $id ) );
		}
		// Remove monitoring rows for products that were permanently deleted.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE item_id NOT IN (SELECT ID FROM %i) LIMIT 100', $this->repository->table( 'items' ), $wpdb->posts ) );
	}
	/**
	 * Create a bounded background tool run for an authorized administrator.
	 *
	 * @param string $mode Mode.
	 * @param int    $user_id User id.
	 * @return void
	 * @throws \InvalidArgumentException When validation or persistence cannot complete.
	 */
	public function start_tool( string $mode, int $user_id ): void {
		if ( ! in_array( $mode, array( 'preview', 'baseline', 'retry' ), true ) ) {
			throw new \InvalidArgumentException( 'Unsupported tool.' );
		}
		Lock_Manager::run(
			'tools',
			function () use ( $mode, $user_id ) {
				global $wpdb;
				$previous = get_option( 'quixdevs_stocknotifier_tool_run' );
				if ( $previous && 'running' === $previous['status'] && $previous['expires'] > time() ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
					throw new \RuntimeException( __( 'A background tool is already running.', 'quixdevs-stocknotifier' ) );
				}
				$run = array(
					'id'         => wp_generate_uuid4(),
					'mode'       => $mode,
					'user_id'    => $user_id,
					'revision'   => Settings::get()['revision'],
					'status'     => 'running',
					'cursor'     => 0,
					'max'        => (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" ),
					'affected'   => 0,
					'resolved'   => 0,
					'unresolved' => 0,
					'estimated'  => 0,
					'ids'        => array(),
					'expires'    => time() + HOUR_IN_SECONDS,
				);
				update_option( 'quixdevs_stocknotifier_tool_run', $run, false );
				as_enqueue_async_action(
					'quixdevs_stocknotifier_tool_batch',
					array(
						'run_id' => $run['id'],
						'cursor' => 0,
					),
					Notification_Queue::GROUP,
					false
				);
			}
		);
	}
	/**
	 * Bind a manual campaign to its completed administrator preview.
	 *
	 * @param string $run_id Run id.
	 * @param int    $user_id User id.
	 * @return void
	 */
	public function confirm_existing( string $run_id, int $user_id ): void {
		Lock_Manager::run(
			'tools',
			function () use ( $run_id, $user_id ) {
				$run = get_option( 'quixdevs_stocknotifier_tool_run' );
				if ( ! $run || $run['id'] !== $run_id || 'preview' !== $run['mode'] || 'complete' !== $run['status'] || $run['expires'] < time() || Settings::get()['revision'] !== $run['revision'] || $run['user_id'] !== $user_id ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception data is escaped at Admin::notice or private preview output.
					throw new \RuntimeException( __( 'Preview expired or settings changed. Run a fresh preview.', 'quixdevs-stocknotifier' ) );
				}
				$run['mode']   = 'notify';
				$run['status'] = 'running';
				$run['cursor'] = 0;
				update_option( 'quixdevs_stocknotifier_tool_run', $run, false );
				as_enqueue_async_action(
					'quixdevs_stocknotifier_tool_batch',
					array(
						'run_id' => $run['id'],
						'cursor' => 0,
					),
					Notification_Queue::GROUP,
					false
				);
			}
		);
	}
	/**
	 * Advance one idempotent background tool batch.
	 *
	 * @param string $run_id Run id.
	 * @param int    $cursor Cursor.
	 * @return void
	 */
	public function tool_batch( string $run_id, int $cursor ): void {
		Lock_Manager::run(
			'tools',
			function () use ( $run_id, $cursor ) {
				global $wpdb;
				$run = get_option( 'quixdevs_stocknotifier_tool_run' );
				if ( ! $run || $run['id'] !== $run_id || $run['cursor'] !== $cursor || 'running' !== $run['status'] ) {
					return;
				}
				if ( $run['expires'] < time() || Settings::get()['revision'] !== $run['revision'] || ! user_can( $run['user_id'], 'manage_options' ) ) {
					$run['status'] = 'cancelled';
					update_option( 'quixdevs_stocknotifier_tool_run', $run, false );
					return;
				}
				$settings = Settings::get();
				$done     = false;
				if ( 'retry' === $run['mode'] ) {
							$rows = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE id>%d AND status='failed' ORDER BY id LIMIT 100", $this->repository->table( 'deliveries' ), $cursor ) );
					foreach ( $rows as $id ) {
						$this->manager->retry( (int) $id );
					}
					$run['affected'] += count( $rows );
					$run['cursor']    = $rows ? (int) max( $rows ) : $cursor;
					$done             = count( $rows ) < 100 || $run['affected'] >= 500;
				} elseif ( 'notify' === $run['mode'] ) {
					$ids = array_slice( $run['ids'], $cursor, 25 );
					foreach ( $ids as $id ) {
						$this->monitor->observe( $this->monitor->fresh( $id ), false, true );
					}
					$run['cursor'] += count( $ids );
					$done           = $run['cursor'] >= count( $run['ids'] );
				} else {
					$ids = $this->ids( $cursor, $run['max'] );
					foreach ( $ids as $id ) {
							$product   = $this->monitor->fresh( $id );
							$canonical = $this->monitor->canonical( $product );
						if ( ! $canonical || $canonical->get_id() !== $id ) {
							continue;
						}
						if ( 'baseline' === $run['mode'] ) {
							$this->monitor->observe( $canonical, true );
							++$run['affected'];
							continue;
						}
						if ( ! is_numeric( $canonical->get_stock_quantity() ) || $canonical->get_stock_quantity() > $settings['threshold'] || ( ! $settings['out_of_stock'] && $canonical->get_stock_quantity() <= 0 ) ) {
							continue;
						}
						++$run['affected'];
						$result = ( new Recipient_Resolver() )->resolve( $id, $settings );
						$count  = count( $result['recipients'] );
						if ( $count ) {
								$run['resolved'] += $count;
						} else {
							++$run['unresolved'];
						}
						$item = $this->repository->one( 'items', $id );
						if ( ( ! $item || 0 === (int) $item->episode ) && $count && count( $run['ids'] ) < 500 ) {
							$run['ids'][] = $id;
							$emails       = array_map( static fn( $r ) => strtolower( $r->email ), $result['recipients'] );
							if ( $settings['admin_copy'] ) {
								$emails = array_merge( $emails, $settings['admin_emails'] );
							}
							$run['estimated'] += count( array_unique( $emails ) );
						}
					}
					$run['cursor'] = $ids ? max( $ids ) : $cursor;
					$done          = count( $ids ) < 100;
				}
				$run['status'] = $done ? 'complete' : 'running';
				update_option( 'quixdevs_stocknotifier_tool_run', $run, false );
				if ( ! $done ) {
					as_schedule_single_action(
						time() + 5,
						'quixdevs_stocknotifier_tool_batch',
						array(
							'run_id' => $run_id,
							'cursor' => $run['cursor'],
						),
						Notification_Queue::GROUP,
						false
					);
				}
			}
		);
	}
}
