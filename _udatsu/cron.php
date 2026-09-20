<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/lib.php';
$lock=fopen(__DIR__.'/private/cron.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit;
$c=config();$s=new UStore(__DIR__.'/private/'.$c['mode'].'.sqlite');
tick($s,new UIntegrations($c));
flock($lock,LOCK_UN);fclose($lock);
