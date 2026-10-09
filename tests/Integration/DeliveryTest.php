<?php
use QuixDevs\StockNotifier\Support\{Settings,Lock_Manager};
use QuixDevs\StockNotifier\Notifications\{Template_Renderer,Email_Sender,Notification_Queue};

final class DeliveryTest extends StockNotifierTestCase {
 public function testAsyncIndividualAcceptanceAndDuplicateJobs(): void {
  $event=$this->low_event();self::assertCount(0,$GLOBALS['qsn_captured_mail']);
  $rows=$this->send($event);self::assertCount(1,$GLOBALS['qsn_captured_mail']);self::assertSame('accepted',$rows[0]->status);
  $this->manager->process((int)$event->id,(int)$rows[0]->id);$this->manager->process((int)$event->id);self::assertCount(1,$GLOBALS['qsn_captured_mail']);
 }
 public function testPerRecipientAdminCopyAndNoAddressExposure(): void {
  $s=Settings::get();$s['admin_copy']=true;$s['admin_emails']=array('one@example.com','two@example.com');update_option(Settings::OPTION,$s,false);
  $event=$this->low_event();$rows=$this->send($event);self::assertCount(3,$rows);self::assertCount(3,$GLOBALS['qsn_captured_mail']);
  foreach($GLOBALS['qsn_captured_mail'] as $mail) { self::assertIsString($mail['to']);self::assertStringNotContainsString('Cc:',implode(' ',$mail['headers'])); }
 }
 public function testFailedTransportBoundedRetryAndRepair(): void {
  $s=Settings::get();$s['retry_limit']=1;update_option(Settings::OPTION,$s,false);
  $GLOBALS['qsn_mail_result']=false;$event=$this->low_event();$row=$this->expand($event)[0];
  $this->manager->process((int)$event->id,(int)$row->id);self::assertSame('queued',$this->repo->one('deliveries',(int)$row->id)->status);
  $this->repo->update('deliveries',array('next_at'=>gmdate('Y-m-d H:i:s',time()-1)),array('id'=>$row->id));
  $this->manager->process((int)$event->id,(int)$row->id);self::assertSame('failed',$this->repo->one('deliveries',(int)$row->id)->status);self::assertSame(2,(int)$this->repo->one('deliveries',(int)$row->id)->attempts);
  $this->manager->retry((int)$row->id);self::assertSame('queued',$this->repo->one('deliveries',(int)$row->id)->status);
 }
 public function testInterruptedTransportIsNotRetried(): void {
  $event=$this->low_event();$row=$this->expand($event)[0];
  $throw=static function(){throw new RuntimeException('Interrupted');};add_filter('pre_wp_mail',$throw,1);
  try { $this->manager->process((int)$event->id,(int)$row->id); } finally { remove_filter('pre_wp_mail',$throw,1); }
  self::assertSame('unknown',$this->repo->one('deliveries',(int)$row->id)->status);
  $this->manager->retry((int)$row->id);$this->manager->process((int)$event->id,(int)$row->id);self::assertCount(0,$GLOBALS['qsn_captured_mail']);
 }
 public function testRestockBeforePendingSendCancels(): void {
  $event=$this->low_event();$row=$this->expand($event)[0];wc_update_product_stock($event->item_id,10);
  $this->manager->process((int)$event->id,(int)$row->id);self::assertCount(0,$GLOBALS['qsn_captured_mail']);self::assertSame('cancelled',$this->repo->one('deliveries',(int)$row->id)->status);
 }
 public function testOldEpisodeCannotCancelNewCrossing(): void {
  $old=$this->low_event();$this->expand($old);wc_update_product_stock($old->item_id,10);wc_update_product_stock($old->item_id,5);
  $new=$this->events()[1];$this->manager->process((int)$old->id);$rows=$this->send($new);self::assertSame('accepted',$rows[0]->status);
 }
 public function testProviderRevisionInvalidatesOldJobs(): void {
  $event=$this->low_event();$row=$this->expand($event)[0];$s=Settings::get();++$s['revision'];update_option(Settings::OPTION,$s,false);
  $this->manager->process((int)$event->id,(int)$row->id);self::assertCount(0,$GLOBALS['qsn_captured_mail']);self::assertSame('cancelled',$this->repo->one('deliveries',(int)$row->id)->status);
 }
 public function testOwnershipChangeCancelsSnapshotWithoutFallback(): void {
  $event=$this->low_event();$row=$this->expand($event)[0];wp_update_post(array('ID'=>$event->product_id,'post_author'=>1));
  $this->manager->process((int)$event->id,(int)$row->id);self::assertSame('cancelled',$this->repo->one('deliveries',(int)$row->id)->status);self::assertCount(0,$GLOBALS['qsn_captured_mail']);
 }
 public function testQueueRepairRecreatesMissingJob(): void {
  $event=$this->low_event();$row=$this->expand($event)[0];$args=array('event_id'=>(int)$event->id,'delivery_id'=>(int)$row->id);
  as_unschedule_all_actions(Notification_Queue::HOOK,$args,Notification_Queue::GROUP);
  self::assertFalse(as_has_scheduled_action(Notification_Queue::HOOK,$args,Notification_Queue::GROUP));$this->recovery->repair();self::assertTrue(as_has_scheduled_action(Notification_Queue::HOOK,$args,Notification_Queue::GROUP));
 }
 public function testTemplateEscapingLiteralUnknownAndPlainFallback(): void {
  $renderer=new Template_Renderer();$s=Settings::get();$s['subject']='{PRODUCT_NAME} {UNKNOWN}';$s['body']='<p>{PRODUCT_NAME}</p><script>alert(1)</script><a href="{PRODUCT_URL}">view</a> [danger] {UNKNOWN}';
  $values=$renderer->sample();$values['PRODUCT_NAME']='<img src=x onerror=alert(1)> Bad';$values['PRODUCT_URL']='javascript:alert(1)';$mail=$renderer->render($values,$s);
  self::assertStringNotContainsString('<script',$mail['html']);self::assertStringNotContainsString('href="javascript:',$mail['html']);self::assertStringContainsString('&lt;img',$mail['html']);self::assertStringContainsString('{UNKNOWN}',$mail['html']);self::assertStringContainsString('[danger]',$mail['html']);self::assertStringNotContainsString('<p>',$mail['plain']);
 }
 public function testHeaderInjectionCannotSend(): void {
  self::assertFalse((new Email_Sender())->send("valid@example.com\r\nBcc: evil@example.com",array('subject'=>'hello','html'=>'hello','plain'=>'hello')));self::assertCount(0,$GLOBALS['qsn_captured_mail']);
 }
 public function testConnectionLockCannotBeStolen(): void {
  Lock_Manager::run('concurrency-test',function(){
   global $wpdb;$socket=substr(DB_HOST,strpos(DB_HOST,':')+1);$other=new mysqli('localhost',DB_USER,DB_PASSWORD,DB_NAME,0,$socket);
   $name='qsn_'.md5(DB_NAME.':'.$wpdb->prefix.':concurrency-test');$stmt=$other->prepare('SELECT GET_LOCK(?, 0)');$stmt->bind_param('s',$name);$stmt->execute();self::assertSame(0,(int)$stmt->get_result()->fetch_row()[0]);$other->close();
  });
 }
}
