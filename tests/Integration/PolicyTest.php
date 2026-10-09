<?php
use QuixDevs\StockNotifier\Support\Settings;

final class PolicyTest extends StockNotifierTestCase {
 private function raw(): array {
  return array('threshold'=>'5','provider'=>'wordpress','authors'=>'primary','enabled'=>'1','out_of_stock'=>'1','logging'=>'1','admin_emails'=>'','retry_limit'=>'3');
 }
 public function testInvalidSettingsPreservePreviousOptionAndStates(): void {
  $event=$this->low_event();$before=Settings::get();$raw=$this->raw();$raw['threshold']='-5';
  try { Settings::validate($raw,$before,$this->resolver);self::fail('Rejected input expected'); } catch(InvalidArgumentException $e) { self::assertSame($before,Settings::get());self::assertCount(1,$this->events()); }
 }
 public function testStoredValidatedThresholdIsInteger(): void {
  $new=Settings::validate($this->raw(),Settings::get(),$this->resolver);update_option(Settings::OPTION,$new,false);self::assertIsInt(get_option(Settings::OPTION)['threshold']);
 }
 public function testWordpressDefaultAndNoUnavailableFallback(): void {
  self::assertSame('wordpress',Settings::get()['provider']);$p=$this->product();self::assertSame($this->author,$this->resolver->resolve($p->get_id())['recipients'][0]->user_id);
  $s=Settings::get();$s['provider']='dokan';update_option(Settings::OPTION,$s,false);
  if('available'!==$this->resolver->providers()['dokan']->status()) { self::assertSame(array(),$this->resolver->resolve($p->get_id())['recipients']); }
 }
 public function testUnavailableProviderCannotBeNewlySelectedButOldSelectionPersists(): void {
  $provider=null;foreach($this->resolver->providers() as $id=>$candidate){if('available'!==$candidate->status()){$provider=$id;break;}}
  if(!$provider){self::markTestSkipped('All providers available in this configuration');}
  $raw=$this->raw();$raw['provider']=$provider;
  try { Settings::validate($raw,Settings::get(),$this->resolver);self::fail('Unavailable provider expected'); } catch(InvalidArgumentException $e) { self::assertSame('wordpress',Settings::get()['provider']); }
  $old=Settings::get();$old['provider']=$provider;$new=Settings::validate($raw,$old,$this->resolver);self::assertSame($provider,$new['provider']);
 }
 public function testMissingWordpressAuthorNeverChoosesAdministrator(): void {
  $p=$this->product();wp_update_post(array('ID'=>$p->get_id(),'post_author'=>99999999));self::assertSame(array(),$this->resolver->resolve($p->get_id())['recipients']);
 }
 public function testExpiredOrUnconfirmedPreviewRejected(): void {
  $this->expectException(RuntimeException::class);$this->recovery->confirm_existing('not-a-preview',1);
 }
}
