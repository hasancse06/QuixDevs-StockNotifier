<?php
use QuixDevs\StockNotifier\Support\Settings;
use QuixDevs\StockNotifier\Notifications\Notification_Queue;

final class RecoveryTest extends StockNotifierTestCase {
 public function testDuplicateAcceptedJobCannotRegressWhenRecipientUnavailable(): void {
  $event=$this->low_event();$row=$this->send($event)[0];wp_update_post(array('ID'=>$event->product_id,'post_author'=>9999999));
  $this->manager->process((int)$event->id,(int)$row->id);self::assertSame('accepted',$this->repo->one('events',(int)$event->id)->status);self::assertCount(1,$GLOBALS['qsn_captured_mail']);
 }
 public function testInterruptedWorkerBecomesUnknownAndNotRetried(): void {
  $event=$this->low_event();$row=$this->expand($event)[0];$this->repo->update('deliveries',array('status'=>'sending','updated_at'=>gmdate('Y-m-d H:i:s',time()-1000)),array('id'=>$row->id));
  $this->recovery->repair();self::assertSame('unknown',$this->repo->one('deliveries',(int)$row->id)->status);self::assertSame('unknown',$this->repo->one('events',(int)$event->id)->status);self::assertCount(0,$GLOBALS['qsn_captured_mail']);
 }
 public function testUnresolvedEpisodesBackOffAndDoNotFallback(): void {
  $event=$this->low_event();wp_update_post(array('ID'=>$event->product_id,'post_author'=>9999999));$this->manager->process((int)$event->id);$e=$this->repo->one('events',(int)$event->id);
  self::assertSame('unresolved',$e->status);self::assertGreaterThan(time(),strtotime($e->retry_at.' UTC'));self::assertCount(0,$this->repo->deliveries((int)$event->id));
 }
 public function testTransactionFailureLeavesNoPartialEpisode(): void {
  $before=count($this->events());try{$this->repo->transaction(function(){$this->repo->insert('activity',array('code'=>'rollback_test','context'=>'{}','created_at'=>gmdate('Y-m-d H:i:s')));throw new RuntimeException('rollback');});}catch(RuntimeException $e){self::assertSame('rollback',$e->getMessage());}
  self::assertCount($before,$this->events());global $wpdb;self::assertSame('0',(string)$wpdb->get_var("SELECT COUNT(*) FROM {$this->repo->table('activity')} WHERE code='rollback_test'"));
 }
 public function testDisablingManagedStockCancelsPendingEmail(): void {
  $event=$this->low_event();$row=$this->expand($event)[0];$p=wc_get_product($event->item_id);$p->set_manage_stock(false);$p->save();$this->manager->process((int)$event->id,(int)$row->id);self::assertCount(0,$GLOBALS['qsn_captured_mail']);self::assertSame('cancelled',$this->repo->one('deliveries',(int)$row->id)->status);
 }
}
