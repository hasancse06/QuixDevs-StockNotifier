<?php
/**
 * Notification Repository component.
 *
 * @package QuixDevsStockNotifier
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Durable operational records require current uncached reads; no WordPress object API exists for these owned tables.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers and query fragments are fixed or allowlisted; all external values use prepare().

namespace QuixDevs\StockNotifier\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** Durable per-inventory episodes and per-recipient attempts. No WooCommerce writes. */
final class Notification_Repository {
	/**
	 * Table.
	 *
	 * @param string $name Name.
	 * @return string
	 * @throws \InvalidArgumentException When validation or persistence cannot complete.
	 */
	public function table( string $name ): string {
		global $wpdb;
		if ( ! in_array( $name, array( 'items', 'events', 'deliveries', 'activity' ), true ) ) {
			throw new \InvalidArgumentException( 'Unknown table.' );
		}
		return $wpdb->prefix . 'quixdevs_stocknotifier_' . $name;
	}
	/**
	 * Create the indexed InnoDB tables using WordPress database conventions.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$sql     = array(
			"CREATE TABLE {$this->table('items')} (
    item_id bigint(20) unsigned NOT NULL,
    product_id bigint(20) unsigned NOT NULL,
    quantity decimal(24,6) DEFAULT NULL,
    threshold bigint(20) unsigned NOT NULL DEFAULT 5,
    generation bigint(20) unsigned NOT NULL DEFAULT 1,
    low tinyint(1) NOT NULL DEFAULT 0,
    episode bigint(20) unsigned NOT NULL DEFAULT 0,
    state varchar(24) NOT NULL DEFAULT 'healthy',
    updated_at datetime NOT NULL,
    PRIMARY KEY  (item_id),
    KEY state (state),
    KEY low_quantity (low,quantity),
    KEY product_id (product_id)
   ) ENGINE=InnoDB $charset;",
			"CREATE TABLE {$this->table('events')} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    item_id bigint(20) unsigned NOT NULL,
    product_id bigint(20) unsigned NOT NULL,
    episode bigint(20) unsigned NOT NULL,
    revision bigint(20) unsigned NOT NULL,
    provider varchar(24) NOT NULL,
    quantity decimal(24,6) NOT NULL,
    threshold bigint(20) unsigned NOT NULL,
    status varchar(24) NOT NULL DEFAULT 'unresolved',
    retry_at datetime NOT NULL DEFAULT '1970-01-01 00:00:01',
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY item_episode (item_id,episode),
    KEY status_retry (status,retry_at,id),
    KEY created_at (created_at)
   ) ENGINE=InnoDB $charset;",
			"CREATE TABLE {$this->table('deliveries')} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    event_id bigint(20) unsigned NOT NULL,
    recipient_key char(64) NOT NULL,
    user_id bigint(20) unsigned NOT NULL DEFAULT 0,
    email varchar(254) NOT NULL,
    name varchar(250) NOT NULL,
    store_name varchar(250) NOT NULL DEFAULT '',
    recipient_type varchar(24) NOT NULL DEFAULT 'owner',
    status varchar(24) NOT NULL DEFAULT 'queued',
    attempts smallint(5) unsigned NOT NULL DEFAULT 0,
    next_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    error_code varchar(100) NOT NULL DEFAULT '',
    PRIMARY KEY  (id),
    UNIQUE KEY event_recipient (event_id,recipient_key),
    KEY status_next (status,next_at),
    KEY status_id (status,id),
    KEY event_status (event_id,status)
   ) ENGINE=InnoDB $charset;",
			"CREATE TABLE {$this->table('activity')} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    item_id bigint(20) unsigned NOT NULL DEFAULT 0,
    event_id bigint(20) unsigned NOT NULL DEFAULT 0,
    provider varchar(24) NOT NULL DEFAULT '',
    code varchar(100) NOT NULL,
    context longtext NOT NULL,
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY created_at (created_at),
    KEY item_id (item_id)
   ) ENGINE=InnoDB $charset;",
		);
		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}
	/**
	 * Read a single durable monitoring or delivery record.
	 *
	 * @param string $table Table.
	 * @param int    $id Id.
	 * @return object|null
	 */
	public function one( string $table, int $id ): ?object {
		global $wpdb;
		$key = 'items' === $table ? 'item_id' : 'id';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table($table)} WHERE %i = %d", $key, $id ) );
	}
	/**
	 * Insert an owned durable record and return its generated identity.
	 *
	 * @param string $table Table.
	 * @param array  $data Data.
	 * @return int
	 * @throws \RuntimeException When validation or persistence cannot complete.
	 */
	public function insert( string $table, array $data ): int {
		global $wpdb;
		if ( false === $wpdb->insert( $this->table( $table ), $data ) ) {
			throw new \RuntimeException( 'StockNotifier database insert failed.' );
		}
		return (int) $wpdb->insert_id;
	}
	/**
	 * Persist selected owned record fields using the supplied identity.
	 *
	 * @param string $table Table.
	 * @param array  $data Data.
	 * @param array  $where Where.
	 * @return void
	 * @throws \RuntimeException When validation or persistence cannot complete.
	 */
	public function update( string $table, array $data, array $where ): void {
		global $wpdb;
		if ( false === $wpdb->update( $this->table( $table ), $data, $where ) ) {
			throw new \RuntimeException( 'StockNotifier database update failed.' );
		}
	}
	/**
	 * Commit all related state changes together or roll them back.
	 *
	 * @param callable $callback Callback.
	 * @throws \RuntimeException When validation or persistence cannot complete.
	 * @throws \Throwable When the operation rolls back.
	 */
	public function transaction( callable $callback ) {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		try {
			$result = $callback();
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'StockNotifier commit failed.' );
			}
			return $result;
		} catch ( \Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			throw $error;
		}
	}
	/**
	 * Invalidate pending work when the inventory episode ends.
	 *
	 * @param int $item_id Item id.
	 * @return void
	 */
	public function cancel_item( int $item_id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$this->table('deliveries')} d INNER JOIN {$this->table('events')} e ON e.id=d.event_id SET d.status='cancelled',d.error_code='episode_invalid' WHERE e.item_id=%d AND d.status IN ('queued','failed','paused')", $item_id ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$this->table('events')} SET status='cancelled' WHERE item_id=%d AND status IN ('unresolved','queued','paused')", $item_id ) );
	}
	/**
	 * Invalidate pending work selected by an obsolete configuration revision.
	 *
	 * @param int $revision Revision.
	 * @return void
	 */
	public function cancel_obsolete( int $revision ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$this->table('deliveries')} d INNER JOIN {$this->table('events')} e ON e.id=d.event_id SET d.status='cancelled',d.error_code='configuration_changed' WHERE e.revision<>%d AND d.status IN ('queued','failed','paused')", $revision ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$this->table('events')} SET status='cancelled' WHERE revision<>%d AND status IN ('unresolved','queued','paused')", $revision ) );
	}
	/**
	 * Read the last alert creation timestamp for the current item episode.
	 *
	 * @param int $item_id Inventory identity.
	 * @param int $episode Item episode sequence.
	 * @return string
	 */
	public function last_alert( int $item_id, int $episode ): string {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT created_at FROM %i WHERE item_id=%d AND episode=%d', $this->table( 'events' ), $item_id, $episode ) );
	}
	/**
	 * Read the individual recipient records for one episode.
	 *
	 * @param int $event_id Event id.
	 * @return array
	 */
	public function deliveries( int $event_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table('deliveries')} WHERE event_id=%d ORDER BY id", $event_id ) );
	}
	/**
	 * Aggregate delivery outcomes without treating partial success as complete.
	 *
	 * @param int $event_id Event id.
	 * @return void
	 */
	public function summarize( int $event_id ): void {
		$rows = $this->deliveries( $event_id );
		if ( ! $rows ) {
			return;
		}
		$statuses = array_column( $rows, 'status' );
		$state    = 'accepted';
		foreach ( array( 'queued', 'sending', 'paused', 'failed', 'unknown', 'cancelled' ) as $candidate ) {
			if ( in_array( $candidate, $statuses, true ) ) {
				$state = $candidate;
				break;
			}
		}
		$this->update( 'events', array( 'status' => $state ), array( 'id' => $event_id ) );
		$event = $this->one( 'events', $event_id );
		$this->update(
			'items',
			array( 'state' => $state ),
			array(
				'item_id' => $event->item_id,
				'episode' => $event->episode,
				'low'     => 1,
			)
		);
	}
	/**
	 * Read one bounded page with allowlisted filtering and sorting.
	 *
	 * @param string $table Table.
	 * @param string $status Status.
	 * @param int    $page Page.
	 * @param string $search Search.
	 * @param string $sort Sort.
	 * @return array
	 */
	public function page( string $table, string $status = '', int $page = 1, string $search = '', string $sort = 'id' ): array {
		global $wpdb;
		$where = '1=1';
		if ( '' !== $status ) {
			if ( 'items' === $table && in_array( $status, array( 'low', 'out' ), true ) ) {
				$where .= 'low' === $status ? ' AND low=1' : ' AND quantity<=0';
			} else {
				$column = 'items' === $table ? 'state' : ( 'activity' === $table ? 'code' : 'status' );
				$where .= $wpdb->prepare( " AND $column=%s", $status );
			}
		}
		if ( 'items' === $table && '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= $wpdb->prepare( " AND (item_id=%d OR product_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s) OR item_id IN (SELECT product_id FROM {$wpdb->prefix}wc_product_meta_lookup WHERE sku LIKE %s))", (int) $search, $like, $like );
		}
		$allowed = 'items' === $table ? array( 'item_id', 'quantity', 'state', 'updated_at' ) : array( 'id', 'created_at' );
		$sort    = in_array( $sort, $allowed, true ) ? $sort : $allowed[0];
		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table($table)} WHERE $where" );
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table($table)} WHERE $where ORDER BY %i DESC LIMIT 25 OFFSET %d", $sort, ( max( 1, $page ) - 1 ) * 25 ) );
		return array(
			'total' => $total,
			'rows'  => $rows,
		);
	}
	/**
	 * Read aggregate dashboard counts without loading the product catalog.
	 *
	 * @return array
	 */
	public function stats(): array {
		global $wpdb;
		$stats               = (array) $wpdb->get_row( "SELECT COUNT(*) monitored,COALESCE(SUM(low=1),0) low,COALESCE(SUM(quantity<=0),0) out_of_stock FROM {$this->table('items')}", ARRAY_A );
		$stats['episodes']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table('events')}" );
		$stats['unresolved'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table('events')} WHERE status='unresolved'" );
		foreach ( $wpdb->get_results( "SELECT status,COUNT(*) total FROM {$this->table('deliveries')} GROUP BY status" ) as $row ) {
			$stats[ $row->status ] = (int) $row->total;
		}
		return $stats;
	}
	/**
	 * Return a UTC database timestamp.
	 *
	 * @return string
	 */
	public static function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
