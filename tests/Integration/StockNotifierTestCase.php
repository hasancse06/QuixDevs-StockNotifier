<?php
use PHPUnit\Framework\TestCase;
use QuixDevs\StockNotifier\Data\Notification_Repository;
use QuixDevs\StockNotifier\Recipients\Recipient_Resolver;
use QuixDevs\StockNotifier\Support\{Settings,Logger};
use QuixDevs\StockNotifier\Notifications\{Notification_Manager,Notification_Queue};
use QuixDevs\StockNotifier\Inventory\{Stock_Monitor,Reconciliation};

abstract class StockNotifierTestCase extends TestCase {
 protected Notification_Repository $repo;
 protected Recipient_Resolver $resolver;
 protected Notification_Manager $manager;
 protected Stock_Monitor $monitor;
 protected Reconciliation $recovery;
 protected int $author;
 protected function setUp(): void {
  global $wpdb;
  $this->repo = new Notification_Repository();
  as_unschedule_all_actions(Notification_Queue::HOOK,null,Notification_Queue::GROUP);
  foreach ( array('items','events','deliveries','activity') as $table ) {
   $wpdb->query( 'TRUNCATE TABLE ' . $this->repo->table($table) );
  }
  update_option(Settings::OPTION, Settings::defaults(), false);
  delete_option('quixdevs_stocknotifier_tool_run');
  $this->resolver = new Recipient_Resolver();
  $logger = new Logger($this->repo);
  $this->manager = new Notification_Manager($this->repo,$this->resolver,new Notification_Queue(),$logger);
  $this->monitor = new Stock_Monitor($this->repo,$this->manager,$logger);
  $this->recovery = new Reconciliation($this->repo,$this->monitor,$this->manager);
  $this->author = wp_insert_user(array('user_login'=>'qsn_'.wp_generate_password(10,false),'user_pass'=>'isolated-test','user_email'=>'owner-'.wp_generate_password(8,false).'@example.com','role'=>'administrator','display_name'=>'Stock Owner '.wp_generate_password(6,false)));
  $GLOBALS['qsn_captured_mail'] = array();
  $GLOBALS['qsn_mail_result'] = true;
  wp_set_current_user(1);
 }
 protected function product( int $quantity = 12, bool $managed = true ): WC_Product_Simple {
  $p = new WC_Product_Simple();
  $p->set_name('Wireless Headphones');
  $p->set_status('publish');
  $p->set_regular_price('25');
  $p->set_manage_stock($managed);
  $p->set_stock_quantity($quantity);
  $p->save();
  wp_update_post(array('ID'=>$p->get_id(),'post_author'=>$this->author));
  return wc_get_product($p->get_id());
 }
 protected function events(): array {
  global $wpdb;
  return $wpdb->get_results('SELECT * FROM '.$this->repo->table('events').' ORDER BY id');
 }
 protected function low_event(): object {
  $p = $this->product();
  wc_update_product_stock($p,5);
  return $this->events()[0];
 }
 protected function expand( object $event ): array {
  $this->manager->process((int)$event->id);
  return $this->repo->deliveries((int)$event->id);
 }
 protected function send( object $event ): array {
  foreach($this->expand($event) as $row) { $this->manager->process((int)$event->id,(int)$row->id); }
  return $this->repo->deliveries((int)$event->id);
 }
}
