<?php
declare(strict_types=1);
require_once __DIR__.'/lib.php';
ini_set('display_errors','0');
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self' https://formspree.io; frame-ancestors 'none'; base-uri 'none'; form-action 'self' https://formspree.io");
function respond(mixed $data,int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function input(): array {$raw=file_get_contents('php://input',false,null,0,128001);if(strlen($raw)>128000)throw new UError('送信内容が大きすぎます。',413);$d=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($d))throw new UError('入力内容をご確認ください。',400);return $d;}
function admin(array $c): void {if(!$c['admin']||!hash_equals($c['admin'],preg_replace('/^Bearer /','',$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'')))throw new UError('管理トークンを確認してください。',401);}
function overlap(array $a,array $b): bool {return strtotime($a['start'])<strtotime($b['end'])&&strtotime($a['end'])>strtotime($b['start']);}
try {
    $c=config();$route=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);$method=$_SERVER['REQUEST_METHOD']??'GET';
    $files=['/'=>'index.html','/index.php'=>'index.html','/styles.css'=>'styles.css','/app.js'=>'app.js','/admin'=>'admin.html','/admin.js'=>'admin.js','/instructor.png'=>'instructor.png','/og.png'=>'og.png'];
    if($method==='GET'&&isset($files[$route])){
        $file=$files[$route];$ext=pathinfo($file,PATHINFO_EXTENSION);$mime=['html'=>'text/html; charset=utf-8','css'=>'text/css; charset=utf-8','js'=>'text/javascript; charset=utf-8','png'=>'image/png'][$ext];
        header('Content-Type: '.$mime);readfile(__DIR__.'/public/'.$file);exit;
    }
    if(!is_dir(__DIR__.'/private'))mkdir(__DIR__.'/private',0700,true);
    $s=new UStore(__DIR__.'/private/'.$c['mode'].'.sqlite');$i=new UIntegrations($c);
    if($c['mode']==='demo'&&!$s->slots())for($day=2;$day<=9;$day++)foreach([10,14,19] as $hour){$a=strtotime('+'.$day.' days');$a=strtotime(date('Y-m-d',$a).' '.$hour.':00:00');$s->addSlot(['start'=>iso($a),'end'=>iso($a+3600),'format'=>'オンライン','location'=>'オンライン開催（体験用の日程）']);}
    if($route==='/api/stripe/webhook'&&$method==='POST'){
        if(!in_array($c['mode'],['test','live'],true))throw new UError('通知受付前です。',404);
        $raw=file_get_contents('php://input',false,null,0,128001);if(strlen($raw)>128000)throw new UError('送信内容が大きすぎます。',413);
        $event=webhook($raw,$_SERVER['HTTP_STRIPE_SIGNATURE']??'',$c['webhook']);
        if(($event['livemode']??null)!==($c['mode']==='live'))throw new UError('決済モードが一致しません。',400);
        $session=$event['data']['object']??[];$order=$s->order($session['metadata']['order_id']??'');
        if($order&&in_array($event['type'],['checkout.session.completed','checkout.session.async_payment_succeeded'],true)&&($session['payment_status']??'')==='paid')$s->settle($session,$c['mode']==='live');
        elseif($order&&$event['type']==='checkout.session.expired'&&$order['session_id']===$session['id'])reconcile($s,$i,$order);
        // 永続ジョブを保存済み。通知の再送は決済の二重反映にならない。
        respond(['received'=>true]);
    }
    if($method==='POST'){
        if(($_SERVER['HTTP_ORIGIN']??'')!==$c['base']||!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json'))throw new UError('この画面から操作してください。',403);
        $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'local');
        $s->tx(function()use($s,$ip){$s->q('DELETE FROM rate_limits WHERE until<?',[time()]);$row=$s->q('SELECT count FROM rate_limits WHERE ip=?',[$ip])->fetch();if($row&&$row['count']>=30)throw new UError('少し時間をおいてお試しください。',429);if($row)$s->q('UPDATE rate_limits SET count=count+1 WHERE ip=?',[$ip]);else $s->q('INSERT INTO rate_limits(ip,count,until) VALUES(?,1,?)',[$ip,time()+60]);});
    }
    if($route==='/api/catalog'&&$method==='GET')respond(['courses'=>$s->catalog,'mode'=>$c['mode'],'leadHours'=>$c['lead'],'calendarConnected'=>$c['googleReady'],'cancellation'=>$c['cancellation'],'privacy'=>$c['privacy'],'supportEmail'=>$c['support'],'seller'=>['name'=>$c['seller'],'address'=>$c['address'],'phone'=>$c['phone']],'format'=>$c['format']]);
    if($route==='/api/slots'&&$method==='GET'){
        $slots=array_values(array_filter($s->slots(),fn($slot)=>strtotime($slot['start'])>=time()+$c['lead']*3600));
        if(!$slots)respond(['slots'=>[]]);
        $ends=array_column($slots,'end');$busy=$i->busy($slots[0]['start'],max($ends));
        $slots=array_filter($slots,function($slot)use($s,$busy){if($slot['fixed'])return $s->course($slot['course_id'])['kind']!=='private';foreach($busy as $b)if(overlap($slot,$b))return false;return true;});
        respond(['slots'=>array_map(fn($slot)=>$s->publicSlot($slot),array_values($slots))]);
    }
    if($route==='/api/checkout'&&$method==='POST'){
        if($c['mode']==='preview')throw new UError('予約受付の準備中です。開始までお待ちください。',503);
        $d=input();if(($d['accepted']??false)!==true)throw new UError('キャンセル条件と個人情報の取り扱いをご確認ください。',400);
        $slot=$s->slot($d['slotId']??'');if(!$slot)throw new UError('日時を選び直してください。',404);
        if($c['mode']==='live'&&$slot['format']==='オンライン'&&!$slot['meeting_url'])throw new UError('参加案内を準備中です。',503);
        if(!$slot['fixed'])foreach($i->busy($slot['start'],$slot['end']) as $b)if(overlap($slot,$b))throw new UError('講師の予定が入りました。別の日時をお選びください。');
        $d['policy']=$c['cancellation'];
        $o=$s->reserve($d,$c['lead']);
        try{$session=ensureCheckout($s,$i,$o);respond(['url'=>$session['url'],'bookingId'=>$o['id']],201);}
        catch(Throwable $e){respond(['error'=>'決済ページへの接続を確認中です。予約確認画面から再確認してください。','bookingId'=>$o['id']],503);}
    }
    if(preg_match('~^/api/bookings/([a-f0-9]{40})$~',$route,$m)&&$method==='GET'){
        $o=$s->order($m[1]);if(!$o)throw new UError('予約が見つかりません。',404);$checking=false;
        try{reconcile($s,$i,$o);}catch(Throwable $e){$checking=true;}
        $o=$s->order($m[1]);$slot=$s->slot($o['slot_id']);$result=['id'=>$o['id'],'status'=>$o['status'],'checking'=>$checking,'checkoutUrl'=>$o['checkout_url'],'course'=>$s->course($o['course_id']),'slot'=>$s->publicSlot($slot),'amount'=>$o['amount']];
        if($o['status']==='paid'){$job=$s->q('SELECT done FROM jobs WHERE id=?',['email-'.$o['id']])->fetch();$result+=['meetingUrl'=>$slot['meeting_url'],'confirmationEmail'=>$c['mode']==='live'?(!empty($job['done'])?'sent':'pending'):'demo'];}
        respond($result);
    }
    if($route==='/api/demo/pay'&&$method==='POST'){
        if($c['mode']!=='demo')throw new UError('体験版のみの操作です。',404);$d=input();$o=$s->order($d['bookingId']??'');if(!$o)throw new UError('予約が見つかりません。',404);
        reconcile($s,$i,$o);$s->settle(['id'=>$o['session_id'],'client_reference_id'=>$o['id'],'metadata'=>['order_id'=>$o['id']],'payment_status'=>'paid','status'=>'complete','amount_total'=>$o['amount'],'currency'=>'jpy','livemode'=>false],false);respond(['ok'=>true]);
    }
    if(str_starts_with($route,'/api/admin/')){
        admin($c);
        if($route==='/api/admin/overview'&&$method==='GET')respond(['slots'=>$s->slots(),'orders'=>$s->q('SELECT * FROM orders ORDER BY created DESC')->fetchAll(),'jobs'=>$s->q('SELECT * FROM jobs WHERE done=0')->fetchAll(),'mode'=>$c['mode'],'calendarConnected'=>$c['googleReady'],'instructors'=>$s->instructors()]);
        if($route==='/api/admin/slots'&&$method==='POST'){$d=input();if($c['mode']==='live'&&($d['format']??'')==='オンライン'&&empty($d['meetingUrl']))throw new UError('オンライン枠には参加URLが必要です。',400);respond($s->addSlot($d),201);}
        if($route==='/api/admin/busy'&&$method==='GET'){$a=strtotime((string)($_GET['from']??''));$b=strtotime((string)($_GET['to']??''));if(!$a||!$b||$b<=$a||$b-$a>15*86400)throw new UError('期間を確認してください。',400);respond($i->busyView(iso($a),iso($b)));}
        if($route==='/api/admin/instructors'&&$method==='POST')respond($s->addInstructor((string)(input()['name']??'')),201);
        if($route==='/api/admin/close'&&$method==='POST'){$s->closeSlot(input()['slotId']??'');respond(['ok'=>true]);}
        if($route==='/api/admin/retry'&&$method==='POST'){tick($s,$i);respond(['ok'=>true]);}
    }
    throw new UError('ページが見つかりません。',404);
}catch(Throwable $e){if(!($e instanceof UError)&&!($e instanceof JsonException))@file_put_contents(__DIR__.'/private/error.log',gmdate('c').' '.($route??'').' '.get_class($e).': '.mb_substr($e->getMessage(),0,300).' @'.basename($e->getFile()).':'.$e->getLine()."\n",FILE_APPEND|LOCK_EX);respond(['error'=>$e instanceof UError?$e->getMessage():($e instanceof JsonException?'入力内容を確認してください。':'接続を確認できませんでした。時間をおいてお試しください。')],$e instanceof UError?$e->http:($e instanceof JsonException?400:503));}
