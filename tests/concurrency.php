<?php
/** Independent-process regression against explicitly isolated test storage. */
$root=getenv('QSN_WP_ROOT');
if(!$root||!str_contains($root,'/qsn-tests/')){throw new RuntimeException('Explicit isolated qsn-tests installation required.');}
require $root.'/wp-load.php';
if(!str_contains(DB_NAME,'qsn_test')){throw new RuntimeException('Refusing non-test database.');}
use QuixDevs\StockNotifier\Data\Notification_Repository;
use QuixDevs\StockNotifier\Recipients\Recipient_Resolver;
use QuixDevs\StockNotifier\Notifications\{Notification_Manager,Notification_Queue};
use QuixDevs\StockNotifier\Inventory\Stock_Monitor;
use QuixDevs\StockNotifier\Support\{Settings,Logger};
$repo=new Notification_Repository();$logger=new Logger($repo);$manager=new Notification_Manager($repo,new Recipient_Resolver(),new Notification_Queue(),$logger);$monitor=new Stock_Monitor($repo,$manager,$logger);
if(($argv[1]??'')==='worker'){
 if($argv[2]==='update'){wc_update_product_stock((int)$argv[3],1,'decrease');}
 elseif($argv[2]==='observe'){$monitor->observe(wc_get_product((int)$argv[3]));}
 else{$manager->process((int)$argv[3],(int)$argv[4]);}
 exit;
}
update_option(Settings::OPTION,Settings::defaults(),false);
global $wpdb;
foreach(['items','events','deliveries','activity'] as $table){$wpdb->query('TRUNCATE TABLE '.$repo->table($table));}
as_unschedule_all_actions(Notification_Queue::HOOK,null,Notification_Queue::GROUP);
$p=new WC_Product_Simple();$p->set_name('Concurrent inventory fixture');$p->set_status('publish');$p->set_manage_stock(true);$p->set_stock_quantity(8);$p->save();wp_update_post(['ID'=>$p->get_id(),'post_author'=>1]);
parallel_qsn(['update',(string)$p->get_id()]);$event=$repo->page('events')['rows'][0];$manager->process((int)$event->id);$delivery=$repo->deliveries((int)$event->id)[0];
function parallel_qsn(array $args):void{
 $jobs=[];
 for($i=0;$i<4;$i++){$pipes=[];$proc=proc_open(array_merge([PHP_BINARY,__FILE__,'worker'],$args),[1=>['pipe','w'],2=>['pipe','w']],$pipes);$jobs[]=[$proc,$pipes];}
 foreach($jobs as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);if($exit!==0){throw new RuntimeException('Worker failed: '.$out.$err);}}
}
parallel_qsn(['observe',(string)$p->get_id()]);
if((int)$wpdb->get_var('SELECT COUNT(*) FROM '.$repo->table('events'))!==1){throw new RuntimeException('Duplicate parallel episode.');}
$capture='/private/tmp/qsn-tests/concurrent-mail.jsonl';file_put_contents($capture,'');putenv('QSN_MAIL_CAPTURE='.$capture);
parallel_qsn(['send',(string)$event->id,(string)$delivery->id]);
$messages=file($capture,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
if(count($messages)!==1||$repo->one('deliveries',(int)$delivery->id)->status!=='accepted'){throw new RuntimeException('Parallel send claim did not suppress duplicates.');}
echo "PASS: four concurrent WooCommerce stock decrements (8 to 4) and four independent observers create one episode; four simultaneous delivery workers invoke the intercepted transport exactly once.\n";
