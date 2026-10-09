<?php
use QuixDevs\StockNotifier\Support\{Logger,Settings};
use QuixDevs\StockNotifier\Admin\Admin;
use QuixDevs\StockNotifier\Notifications\Template_Renderer;

final class SecurityTest extends StockNotifierTestCase {
 private function admin(): Admin { return new Admin($this->repo,$this->resolver,$this->manager,$this->recovery,new Logger($this->repo)); }
 public function testVendorCannotReadRecipientsOrTriggerEmail(): void {
  $u=new WP_User($this->author);$u->set_role('customer');wp_set_current_user($this->author);
  $die=static fn()=>static function($message,$title='',$args=array()){throw new RuntimeException((string)($args['response']??0));};add_filter('wp_die_handler',$die);
  try { $this->admin()->authorize();self::fail('Authorization must fail'); } catch(RuntimeException $e){self::assertSame('403',$e->getMessage());} finally{remove_filter('wp_die_handler',$die);}
  self::assertCount(0,$GLOBALS['qsn_captured_mail']);
 }
 public function testMissingNonceCannotMutateSettings(): void {
  wp_set_current_user(1);$old=Settings::get();$_SERVER['REQUEST_METHOD']='POST';$_POST=array('task'=>'settings','threshold'=>'0');$_REQUEST=array();
  $die=static fn()=>static function(){throw new RuntimeException('nonce_rejected');};add_filter('wp_die_handler',$die);
  try{$this->admin()->handle();self::fail('Nonce must fail');}catch(RuntimeException $e){self::assertSame('nonce_rejected',$e->getMessage());}finally{remove_filter('wp_die_handler',$die);$_POST=array();$_REQUEST=array();}
  self::assertSame($old,Settings::get());
 }
 public function testTestEmailUsesSyntheticValuesAndCannotDuplicate(): void {
  $renderer=new Template_Renderer();$mail=$renderer->render($renderer->sample());$mail['subject']='[TEST EMAIL] '.$mail['subject'];$token=wp_generate_uuid4();
  $key='quixdevs_stocknotifier_test_1';update_option($key,array('token'=>$token,'email'=>'test@example.com','rendered'=>$mail,'status'=>'queued','created'=>time()),false);
  $this->admin()->test_email(1,$token);$this->admin()->test_email(1,$token);
  self::assertSame('accepted',get_option($key)['status']);self::assertCount(1,$GLOBALS['qsn_captured_mail']);self::assertStringContainsString('TEST EMAIL',$GLOBALS['qsn_captured_mail'][0]['subject']);self::assertCount(0,$this->events());
  delete_option($key);
 }
 public function testWorkerWithoutCapabilityCannotSendTest(): void {
  $u=new WP_User($this->author);$u->set_role('customer');$renderer=new Template_Renderer();$token=wp_generate_uuid4();$key='quixdevs_stocknotifier_test_'.$this->author;
  update_option($key,array('token'=>$token,'email'=>'test@example.com','rendered'=>$renderer->render($renderer->sample()),'status'=>'queued','created'=>time()),false);
  $this->admin()->test_email($this->author,$token);self::assertCount(0,$GLOBALS['qsn_captured_mail']);delete_option($key);
 }
 public function testWorkerEditLinkUsesRecipientCapabilityNotCurrentUser(): void {
  $p=$this->product();$r=$this->resolver->resolve($p->get_id())['recipients'][0];wp_set_current_user(0);
  $url=$this->resolver->edit_url($r);self::assertStringContainsString('post.php?post='.$p->get_id(),$url);
 }
}
