<?php
use QuixDevs\StockNotifier\Support\Settings;

final class InventoryTest extends StockNotifierTestCase {
 public function testActivationAndExistingLowBaselineDoNotSend(): void {
  $this->product(3);
  self::assertCount(0,$this->events());
  self::assertCount(0,$GLOBALS['qsn_captured_mail']);
 }
 public function testCrossingDuplicatesAndRestocking(): void {
  $p=$this->product();
  wc_update_product_stock($p,8);
  self::assertCount(0,$this->events());
  wc_update_product_stock($p,5);
  $this->monitor->changed(wc_get_product($p->get_id()));
  wc_update_product_stock($p,4);
  wc_update_product_stock($p,2);
  self::assertCount(1,$this->events());
  wc_update_product_stock($p,10);
  wc_update_product_stock($p,5);
  self::assertCount(2,$this->events());
 }
 public function testFirstCrossingBeforeBackgroundBaseline(): void {
  global $wpdb;
  $p=$this->product();
  $wpdb->delete($this->repo->table('items'),array('item_id'=>$p->get_id()));
  wc_update_product_stock($p,5);
  self::assertCount(1,$this->events());
 }
 public function testCrudAdminSaveCapturesPersistedOldQuantity(): void {
  global $wpdb;
  $p=$this->product();
  $wpdb->delete($this->repo->table('items'),array('item_id'=>$p->get_id()));
  $p->set_stock_quantity(5); $p->save();
  self::assertCount(1,$this->events());
 }
 public function testUnmanagedStockNeverBecomesZero(): void {
  $p=$this->product(0,false);
  $this->monitor->observe($p);
  self::assertNull($this->repo->one('items',$p->get_id()));
  self::assertCount(0,$this->events());
 }
 public function testThresholdZeroAndBackorders(): void {
  $s=Settings::get();$s['threshold']=0;update_option(Settings::OPTION,$s,false);
  $p=$this->product(3);$p->set_backorders('yes');$p->save();
  wc_update_product_stock($p,0);wc_update_product_stock($p,-3);
  self::assertCount(1,$this->events());
 }
 public function testThresholdChangeDoesNotCreateCampaign(): void {
  $p=$this->product(8);
  $s=Settings::get();$s['threshold']=10;++$s['generation'];++$s['revision'];update_option(Settings::OPTION,$s,false);
  $this->monitor->observe(wc_get_product($p->get_id()));
  self::assertCount(0,$this->events());
  wc_update_product_stock($p,12);wc_update_product_stock($p,10);
  self::assertCount(1,$this->events());
 }
 public function testSharedParentAndIndependentVariationStock(): void {
  $parent=new WC_Product_Variable();$parent->set_name('Shirt');$parent->set_status('publish');$parent->set_manage_stock(true);$parent->set_stock_quantity(12);$parent->save();
  wp_update_post(array('ID'=>$parent->get_id(),'post_author'=>$this->author));
  $variation=new WC_Product_Variation();$variation->set_parent_id($parent->get_id());$variation->set_status('publish');$variation->set_regular_price('20');$variation->save();
  wc_update_product_stock(wc_get_product($variation->get_id()),5);
  self::assertCount(1,$this->events());self::assertSame($parent->get_id(),(int)$this->events()[0]->item_id);
  $independent=new WC_Product_Variation();$independent->set_parent_id($parent->get_id());$independent->set_manage_stock(true);$independent->set_stock_quantity(10);$independent->set_regular_price('20');$independent->save();
  wc_update_product_stock($independent,5);
  self::assertCount(2,$this->events());self::assertSame($independent->get_id(),(int)$this->events()[1]->item_id);self::assertSame($parent->get_id(),(int)$this->events()[1]->product_id);
 }
 public function testOrderReductionAndRestoration(): void {
  $p=$this->product(6);$order=wc_create_order();$order->add_product($p,2);$order->save();
  wc_reduce_stock_levels($order->get_id());self::assertCount(1,$this->events());
  wc_increase_stock_levels($order->get_id());self::assertSame(0,(int)$this->repo->one('items',$p->get_id())->low);
 }
 public function testRestApiUpdate(): void {
  $p=$this->product();
  $request=new WP_REST_Request('PUT','/wc/v3/products/'.$p->get_id());$request->set_param('id',$p->get_id());$request->set_param('stock_quantity',5);
  $controller=new WC_REST_Products_Controller();$response=$controller->update_item($request);
  self::assertNotInstanceOf(WP_Error::class,$response);self::assertCount(1,$this->events());
 }
 public function testReconciliationRecoversMissedEvent(): void {
  $p=$this->product();
  // Simulate an unsupported direct SQL edit solely to test bounded recovery.
  update_post_meta($p->get_id(),'_stock',3);
  $this->monitor->observe($this->monitor->fresh($p->get_id()));self::assertCount(1,$this->events());
 }
 public function testManualExistingCampaignIsPreviewBoundAndDeduplicated(): void {
  $p=$this->product(3);
  $this->recovery->start_tool('preview',1);
  do { $run=get_option('quixdevs_stocknotifier_tool_run');$this->recovery->tool_batch($run['id'],$run['cursor']);$run=get_option('quixdevs_stocknotifier_tool_run'); } while('running'===$run['status']);
  self::assertGreaterThanOrEqual(1,$run['affected']);self::assertContains($p->get_id(),$run['ids']);self::assertCount(0,$this->events());
  $this->recovery->confirm_existing($run['id'],1);
  do { $run=get_option('quixdevs_stocknotifier_tool_run');$this->recovery->tool_batch($run['id'],$run['cursor']);$run=get_option('quixdevs_stocknotifier_tool_run'); } while('running'===$run['status']);
  $count=count($this->events());$this->monitor->observe(wc_get_product($p->get_id()),false,true);self::assertCount($count,$this->events());
 }
}
