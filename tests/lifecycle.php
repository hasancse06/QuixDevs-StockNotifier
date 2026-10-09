<?php
/** Exercise default preservation and opt-in deletion only on disposable storage. */
$root=getenv('QSN_WP_ROOT');if(!$root||!str_contains($root,'/qsn-tests/'))throw new RuntimeException('Isolated test installation required.');
require $root.'/wp-load.php';if(!str_contains(DB_NAME,'qsn_test'))throw new RuntimeException('Non-test database refused.');
use QuixDevs\StockNotifier\Support\Settings;
use QuixDevs\StockNotifier\Data\Notification_Repository;
$r=new Notification_Repository();$original=Settings::get();$original['delete_data']=false;update_option(Settings::OPTION,$original,false);
update_option('qsn_unrelated_lifecycle_sentinel','preserve-me',false);
$p=new WC_Product_Simple();$p->set_name('Lifecycle fixture');$p->set_manage_stock(true);$p->set_stock_quantity(17);$p->save();
$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
QuixDevs\StockNotifier\Deactivator::deactivate();
if(get_option(Settings::OPTION)!==$original||!$r->one('items',$p->get_id()))throw new RuntimeException('Deactivation failed to preserve state.');
define('WP_UNINSTALL_PLUGIN',true);include dirname(__DIR__).'/uninstall.php';
if(get_option(Settings::OPTION)!==$original||!$r->one('items',$p->get_id()))throw new RuntimeException('Default uninstall failed to preserve data.');
$delete=$original;$delete['delete_data']=true;update_option(Settings::OPTION,$delete,false);include dirname(__DIR__).'/uninstall.php';
if(get_option(Settings::OPTION,false)!==false)throw new RuntimeException('Owned option retained unexpectedly.');
$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$r->table('items')));if($exists)throw new RuntimeException('Owned table retained unexpectedly.');
if(get_option('qsn_unrelated_lifecycle_sentinel')!=='preserve-me')throw new RuntimeException('Unrelated option modified.');
if((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}")!==$before||wc_get_product($p->get_id())->get_stock_quantity()!=17)throw new RuntimeException('WooCommerce data changed.');
$r->install();update_option(Settings::OPTION,$original,false);delete_option('qsn_unrelated_lifecycle_sentinel');
echo "PASS: deactivation retains settings/history; default uninstall preserves owned data; opted-in uninstall removes owned tables/options and preserves products, stock, post count, and unrelated options.\n";
