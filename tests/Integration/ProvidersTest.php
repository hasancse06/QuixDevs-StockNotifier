<?php
use QuixDevs\StockNotifier\Support\Settings;

/** These tests exercise installed free integration APIs, never premium mocks. */
final class ProvidersTest extends StockNotifierTestCase {
 public function testActualDokanOwnershipAndEligibility(): void {
  if(!function_exists('dokan_get_vendor')){self::markTestSkipped('Dokan Lite not active');}
  $u=new WP_User($this->author);$u->set_role('seller');update_user_meta($this->author,'dokan_enable_selling','yes');
  $p=$this->product();$s=Settings::get();$s['provider']='dokan';update_option(Settings::OPTION,$s,false);
  $result=$this->resolver->resolve($p->get_id());self::assertCount(1,$result['recipients'],$result['reason'].' roles='.wp_json_encode(get_userdata($this->author)->roles).' owner='.get_post_field('post_author',$p->get_id(),'raw'));self::assertSame($this->author,$result['recipients'][0]->user_id);
  update_user_meta($this->author,'dokan_enable_selling','no');self::assertSame(array(),$this->resolver->resolve($p->get_id())['recipients']);
 }
 public function testActualWCVendorsOwnerAndInvalidSeller(): void {
  if(!class_exists('WCV_Vendors')){self::markTestSkipped('WC Vendors not active');}
  $u=new WP_User($this->author);$u->set_role('vendor');$p=$this->product();$s=Settings::get();$s['provider']='wcvendors';update_option(Settings::OPTION,$s,false);
  $result=$this->resolver->resolve($p->get_id());self::assertCount(1,$result['recipients'],$result['reason'].' roles='.wp_json_encode(get_userdata($this->author)->roles).' owner='.get_post_field('post_author',$p->get_id(),'raw'));self::assertSame($this->author,$result['recipients'][0]->user_id);
  $u->set_role('customer');self::assertSame(array(),$this->resolver->resolve($p->get_id())['recipients']);
 }
 public function testActualPublishPressAssignedUsersAndGuests(): void {
  if(!function_exists('get_post_authors')){self::markTestSkipped('PublishPress Authors not active');}
  if(!MultipleAuthors\Classes\Utils::is_post_type_enabled('product')){self::markTestSkipped('PublishPress product post type not enabled');}
  $p=$this->product();$a=MultipleAuthors\Classes\Objects\Author::create_from_user(get_userdata($this->author));$b=MultipleAuthors\Classes\Objects\Author::create_from_user(get_userdata(1));
  MultipleAuthors\Classes\Utils::set_post_authors($p->get_id(),array($a,$b),false);
  $s=Settings::get();$s['provider']='publishpress';$s['authors']='primary';update_option(Settings::OPTION,$s,false);
  $result=$this->resolver->resolve($p->get_id());self::assertCount(1,$result['recipients'],$result['reason'].' roles='.wp_json_encode(get_userdata($this->author)->roles).' owner='.get_post_field('post_author',$p->get_id(),'raw'));self::assertSame($this->author,$result['recipients'][0]->user_id);
  $s['authors']='all';update_option(Settings::OPTION,$s,false);self::assertCount(2,$this->resolver->resolve($p->get_id())['recipients']);
  $guest=wp_insert_term('Guest Test '.wp_generate_password(5,false),'author');$guestAuthor=MultipleAuthors\Classes\Objects\Author::get_by_term_id($guest['term_id']);
  MultipleAuthors\Classes\Utils::set_post_authors($p->get_id(),array($guestAuthor,$a),false);
  self::assertCount(1,$this->resolver->resolve($p->get_id())['recipients']);$s['authors']='primary';update_option(Settings::OPTION,$s,false);self::assertSame(array(),$this->resolver->resolve($p->get_id())['recipients']);
 }
}
