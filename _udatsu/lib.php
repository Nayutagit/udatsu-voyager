<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Tokyo');

final class UError extends RuntimeException {
    public int $http;
    public function __construct(string $message, int $http = 409) { parent::__construct($message); $this->http = $http; }
}
function uid(): string { return bin2hex(random_bytes(20)); }
function iso(int $time): string { return gmdate('Y-m-d\TH:i:s.000\Z', $time); }
function config(): array {
    $e = [];
    $file = getenv('UDATSU_ENV_FILE') ?: __DIR__.'/private/.env';
    if (is_file($file)) foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        if (!str_contains($line, '=') || str_starts_with(ltrim($line), '#')) continue;
        [$k, $v] = explode('=', $line, 2); $e[trim($k)] = trim(trim($v), "\"'");
    }
    $c = [
        'mode'=>$e['APP_MODE']??'preview', 'base'=>getenv('UDATSU_BASE_URL') ?: ($e['BASE_URL']??'https://udatsuageteko.com'),
        'admin'=>$e['ADMIN_TOKEN']??'', 'stripe'=>$e['STRIPE_SECRET_KEY']??'', 'webhook'=>$e['STRIPE_WEBHOOK_SECRET']??'',
        'googleId'=>$e['GOOGLE_CLIENT_ID']??'', 'googleSecret'=>$e['GOOGLE_CLIENT_SECRET']??'', 'googleRefresh'=>$e['GOOGLE_REFRESH_TOKEN']??'',
        'busyIds'=>array_values(array_filter(array_map('trim',explode(',',$e['GOOGLE_BUSY_CALENDAR_IDS']??'primary')))),
        'bookingCalendar'=>$e['GOOGLE_BOOKING_CALENDAR_ID']??'', 'emailKey'=>$e['RESEND_API_KEY']??'', 'mailFrom'=>$e['MAIL_FROM']??'', 'mailTransport'=>$e['MAIL_TRANSPORT']??'php',
        'support'=>$e['SUPPORT_EMAIL']??'', 'seller'=>$e['SELLER_NAME']??'', 'address'=>$e['SELLER_ADDRESS']??'', 'phone'=>$e['SELLER_PHONE']??'',
        'cancellation'=>($e['CANCELLATION_POLICY']??'')?:'キャンセル条件は公開前に確定します。現在は予約を受け付けていません。',
        'privacy'=>($e['PRIVACY_POLICY']??'')?:'公開準備中です。個人情報の取り扱いは受付開始前にご案内します。',
        'lead'=>max(1,(int)($e['LEAD_HOURS']??24)), 'format'=>$e['FORMAT']??'オンライン',
    ];
    $c['base']=rtrim($c['base'],'/');
    $c['googleReady']=(bool)($c['googleId']&&$c['googleSecret']&&$c['googleRefresh']&&$c['bookingCalendar']&&$c['busyIds']);
    if (!in_array($c['mode'],['preview','demo','test','live'],true)) throw new UError('公開設定を確認しています。',503);
    if ($c['mode']==='demo' && !in_array(parse_url($c['base'],PHP_URL_HOST),['127.0.0.1','localhost','::1'],true)) throw new UError('体験版はMac内専用です。',503);
    if (in_array($c['mode'],['test','live'],true) && (!str_starts_with($c['stripe'],$c['mode']==='live'?'sk_live_':'sk_test_')||!str_starts_with($c['webhook'],'whsec_')||strlen($c['admin'])<32)) throw new UError('決済の接続設定中です。',503);
    if ($c['mode']==='live' && (($e['LIVE_BOOKING_ENABLED']??'')!=='true'||($e['CRON_ENABLED']??'')!=='true'||!$c['googleReady']||!str_starts_with($c['base'],'https://')||!in_array($c['mailTransport'],['php','resend'],true)||($c['mailTransport']==='resend'&&!$c['emailKey'])||!filter_var($c['mailFrom'],FILTER_VALIDATE_EMAIL)||!$c['support']||!$c['seller']||!$c['address']||!$c['phone']||empty($e['CANCELLATION_POLICY'])||empty($e['PRIVACY_POLICY']))) throw new UError('本番受付の準備中です。',503);
    return $c;
}
class UStore {
    public PDO $db;
    public array $catalog;
    public function __construct(string $path, ?array $catalog=null) {
        $this->catalog=$catalog??json_decode(file_get_contents(__DIR__.'/catalog.json'),true,512,JSON_THROW_ON_ERROR);
        $this->db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $this->db->exec("PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;
          CREATE TABLE IF NOT EXISTS slots(id TEXT PRIMARY KEY,start TEXT NOT NULL,end TEXT NOT NULL,format TEXT NOT NULL,location TEXT NOT NULL,meeting_url TEXT NOT NULL DEFAULT '',course_id TEXT,fixed INTEGER NOT NULL DEFAULT 0,closed INTEGER NOT NULL DEFAULT 0);
          CREATE TABLE IF NOT EXISTS orders(id TEXT PRIMARY KEY,slot_id TEXT NOT NULL REFERENCES slots(id),course_id TEXT NOT NULL,name TEXT NOT NULL,email TEXT NOT NULL,amount INTEGER NOT NULL,status TEXT NOT NULL DEFAULT 'pending',session_id TEXT UNIQUE,checkout_url TEXT,created INTEGER NOT NULL,expires INTEGER NOT NULL,payment_intent TEXT,policy TEXT NOT NULL,note TEXT NOT NULL DEFAULT '');
          CREATE TABLE IF NOT EXISTS jobs(id TEXT PRIMARY KEY,kind TEXT NOT NULL,reference TEXT NOT NULL,done INTEGER NOT NULL DEFAULT 0,attempts INTEGER NOT NULL DEFAULT 0,last_error TEXT NOT NULL DEFAULT '');
          CREATE TABLE IF NOT EXISTS rate_limits(ip TEXT PRIMARY KEY,count INTEGER NOT NULL,until INTEGER NOT NULL);");
    }
    public function q(string $sql,array $args=[]): PDOStatement { $s=$this->db->prepare($sql);$s->execute($args);return $s; }
    public function tx(callable $fn): mixed {
        $this->db->exec('BEGIN IMMEDIATE');
        try {$result=$fn();$this->db->exec('COMMIT');return $result;}
        catch(Throwable $e){$this->db->exec('ROLLBACK');throw $e;}
    }
    public function course(?string $id): ?array {foreach($this->catalog as $c)if($c['id']===$id)return $c;return null;}
    public function slot(string $id): ?array {return $this->q('SELECT * FROM slots WHERE id=?',[$id])->fetch()?:null;}
    public function order(string $id): ?array {return $this->q('SELECT * FROM orders WHERE id=?',[$id])->fetch()?:null;}
    public function counts(string $id): array {return $this->q("SELECT COALESCE(SUM(status='paid'),0) paid,COALESCE(SUM(status='pending'),0) pending FROM orders WHERE slot_id=?",[$id])->fetch();}
    public function slots(): array {return $this->q('SELECT * FROM slots WHERE closed=0 ORDER BY start')->fetchAll();}
    public function addSlot(array $input): array {
        $a=strtotime($input['start']??'');$b=strtotime($input['end']??'');
        if(!$a||!$b||$a<=time()||$b-$a<3600||$b-$a>14400)throw new UError('未来の日時で、60分以上4時間以内の枠を指定してください。',400);
        if(!in_array($input['format']??'',['オンライン','対面'],true)||empty(trim($input['location']??'')))throw new UError('開催形式と場所が必要です。',400);
        if(!empty($input['meetingUrl'])&&!str_starts_with($input['meetingUrl'],'https://'))throw new UError('参加URLはhttpsで指定してください。',400);
        return $this->tx(function()use($input,$a,$b){
            $start=iso($a);$end=iso($b);
            if($this->q('SELECT id FROM slots WHERE start<? AND end>? AND closed=0',[$end,$start])->fetch())throw new UError('ほかの募集枠と時間が重なっています。');
            $id=uid();$this->q('INSERT INTO slots(id,start,end,format,location,meeting_url) VALUES(?,?,?,?,?,?)',[$id,$start,$end,$input['format'],mb_substr($input['location'],0,200),$input['meetingUrl']??'']);return $this->slot($id);
        });
    }
    public function reserve(array $input,int $lead=24): array {
        $course=$this->course($input['courseId']??'');
        if(!$course)throw new UError('講座を選び直してください。',400);
        $name=trim($input['name']??'');$email=strtolower(trim($input['email']??''));
        if(!$name||mb_strlen($name)>80||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new UError('お名前とメールアドレスを確認してください。',400);
        return $this->tx(function()use($input,$lead,$course,$name,$email){
            $slot=$this->slot($input['slotId']??'');
            if(!$slot||$slot['closed']||strtotime($slot['start'])<time()+$lead*3600)throw new UError('この日時の受付は終了しました。');
            if(strtotime($slot['end'])-strtotime($slot['start'])<$course['minutes']*60)throw new UError('開催時間が不足しています。');
            if($slot['course_id']&&$slot['course_id']!==$course['id'])throw new UError('この時間は別の講座が選ばれています。別の日時をお選びください。');
            $count=$this->counts($slot['id']);
            if(!$slot['fixed']&&$count['pending']>0)throw new UError('最初の方がお支払い中です。少し時間をおいてご確認ください。');
            if($count['paid']+$count['pending']>=$course['capacity'])throw new UError('満席、またはほかの方がお支払い手続き中です。');
            if($this->q("SELECT id FROM orders WHERE slot_id=? AND email=? AND status IN ('pending','paid')",[$slot['id'],$email])->fetch())throw new UError('このメールアドレスでお申し込み済み、または手続き中です。');
            $id=uid();$this->q('UPDATE slots SET course_id=? WHERE id=?',[$course['id'],$slot['id']]);
            $this->q('INSERT INTO orders(id,slot_id,course_id,name,email,amount,created,expires,policy) VALUES(?,?,?,?,?,?,?,?,?)',[$id,$slot['id'],$course['id'],$name,$email,$course['price'],time()*1000,time()+1860,$input['policy']??'']);
            return $this->order($id);
        });
    }
    public function attach(string $id,array $session): void {
        $order=$this->order($id);
        if(!$order||($order['session_id']&&$order['session_id']!==$session['id']))throw new UError('決済情報が一致しません。');
        $this->q('UPDATE orders SET session_id=?,checkout_url=? WHERE id=?',[$session['id'],$session['url']??'',$id]);
    }
    public function release(string $id): void {
        $this->tx(function()use($id){
            $order=$this->order($id);if(!$order||$order['status']!=='pending')return;
            $this->q("UPDATE orders SET status='expired' WHERE id=?",[$id]);$count=$this->counts($order['slot_id']);
            if(!$count['paid']&&!$count['pending'])$this->q('UPDATE slots SET course_id=NULL WHERE id=? AND fixed=0',[$order['slot_id']]);
        });
    }
    public function settle(array $session,bool $live): void {
        $this->tx(function()use($session,$live){
            $order=$this->order($session['client_reference_id']??'');
            if(!$order)throw new UError('予約が見つかりません。',404);
            if(($session['payment_status']??'')!=='paid'||($session['status']??'')!=='complete'||($session['amount_total']??0)!==$order['amount']||($session['currency']??'')!=='jpy'||($session['livemode']??null)!==$live||($session['metadata']['order_id']??'')!==$order['id']||($order['session_id']&&$order['session_id']!==$session['id']))throw new UError('決済情報や金額が一致しません。',400);
            if($order['status']==='paid')return;
            if($order['status']!=='pending')throw new UError('この予約は終了しています。確認が必要です。');
            $slot=$this->slot($order['slot_id']);if($slot['course_id']!==$order['course_id'])throw new UError('講座情報が一致しません。');
            $this->q("UPDATE orders SET status='paid',session_id=?,payment_intent=? WHERE id=?",[$session['id'],$session['payment_intent']??'',$order['id']]);
            $this->q('UPDATE slots SET fixed=1 WHERE id=?',[$slot['id']]);
            $this->q("INSERT OR IGNORE INTO jobs(id,kind,reference) VALUES(?,'calendar',?)",['calendar-'.$slot['id'],$slot['id']]);
            $this->q("INSERT OR IGNORE INTO jobs(id,kind,reference) VALUES(?,'email',?)",['email-'.$order['id'],$order['id']]);
        });
    }
    public function closeSlot(string $id): void {
        $this->tx(function()use($id){$count=$this->counts($id);if($count['paid']||$count['pending'])throw new UError('申込者がいる枠は閉じられません。先に連絡・返金対応が必要です。');$this->q('UPDATE slots SET closed=1 WHERE id=?',[$id]);});
    }
    public function publicSlot(array $s): array {
        $n=$this->counts($s['id']);$c=$this->course($s['course_id']);
        return ['id'=>$s['id'],'start'=>$s['start'],'end'=>$s['end'],'format'=>$s['format'],'location'=>$s['location'],'courseId'=>$s['fixed']?$s['course_id']:null,'status'=>$s['fixed']?'open':($n['pending']?'held':'available'),'paid'=>$n['paid'],'remaining'=>$c?max(0,$c['capacity']-$n['paid']-$n['pending']):null];
    }
}
function webhook(string $body,string $signature,string $secret,?int $now=null): array {
    preg_match('/(?:^|,)t=(\d+)/',$signature,$m);$time=$m[1]??'';
    if(!$secret||!$time||abs(($now??time())-(int)$time)>300)throw new UError('署名が期限切れ、または未設定です。',400);
    $expected=hash_hmac('sha256',$time.'.'.$body,$secret);preg_match_all('/(?:^|,)v1=([a-f0-9]{64})/',$signature,$matches);
    $valid=false;foreach($matches[1] as $s)if(hash_equals($expected,$s))$valid=true;
    if(!$valid)throw new UError('署名が一致しません。',400);
    return json_decode($body,true,512,JSON_THROW_ON_ERROR);
}
class UIntegrations {
    public array $c;
    public function __construct(array $c){$this->c=$c;}
    public function request(string $url,string $method='GET',?string $body=null,array $headers=[]): array {
        $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
        $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($response===false||$status<200||$status>=300)throw new UError('外部サービスとの接続を確認できませんでした。', $status===409?409:503);
        return json_decode($response,true,512,JSON_THROW_ON_ERROR);
    }
    public function stripe(string $path,?array $form=null,?string $key=null): array {
        $headers=['Authorization: Bearer '.$this->c['stripe']];if($form!==null){$headers[]='Content-Type: application/x-www-form-urlencoded';$headers[]='Idempotency-Key: '.$key;}
        return $this->request('https://api.stripe.com/v1'.$path,$form===null?'GET':'POST',$form===null?null:http_build_query($form),$headers);
    }
    public function createCheckout(array $o,array $s,array $course): array {
        return $this->stripe('/checkout/sessions',[
            'mode'=>'payment','locale'=>'ja','customer_email'=>$o['email'],'client_reference_id'=>$o['id'],'metadata'=>['order_id'=>$o['id']],
            'payment_method_types'=>['card'],'line_items'=>[['price_data'=>['currency'=>'jpy','unit_amount'=>$o['amount'],'product_data'=>['name'=>$course['title'],'description'=>date('Y/m/d H:i',strtotime($s['start'])).'（日本時間） / 60分 / '.$s['format']]],'quantity'=>1]],
            'expires_at'=>$o['expires'],'success_url'=>$this->c['base'].'/?booking='.$o['id'],'cancel_url'=>$this->c['base'].'/?booking='.$o['id'].'&cancel=1'
        ],'udatsu-checkout-'.$o['id']);
    }
    public function getCheckout(string $id): array {return $this->stripe('/checkout/sessions/'.rawurlencode($id));}
    public function token(): string {
        $d=$this->request('https://oauth2.googleapis.com/token','POST',http_build_query(['grant_type'=>'refresh_token','client_id'=>$this->c['googleId'],'client_secret'=>$this->c['googleSecret'],'refresh_token'=>$this->c['googleRefresh']]),['Content-Type: application/x-www-form-urlencoded']);return $d['access_token'];
    }
    public function busy(string $start,string $end): array {
        if(in_array($this->c['mode'],['demo','preview'],true))return [];
        if(!$this->c['googleReady']){if($this->c['mode']==='live')throw new UError('カレンダーを確認できないため受付を一時停止しています。',503);return [];}
        $d=$this->request('https://www.googleapis.com/calendar/v3/freeBusy','POST',json_encode(['timeMin'=>$start,'timeMax'=>$end,'timeZone'=>'Asia/Tokyo','items'=>array_map(fn($id)=>['id'=>$id],$this->c['busyIds'])]),['Authorization: Bearer '.$this->token(),'Content-Type: application/json']);
        $busy=[];foreach($this->c['busyIds'] as $id){$cal=$d['calendars'][$id]??null;if(!$cal||!empty($cal['errors'])||!isset($cal['busy']))throw new UError('カレンダーを確認できません。',503);$busy=array_merge($busy,$cal['busy']);}return $busy;
    }
    public function createEvent(array $s,array $course): void {
        if($this->c['mode']!=='live')return;
        try{$this->request('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($this->c['bookingCalendar']).'/events','POST',json_encode(['id'=>'udatsu'.$s['id'],'summary'=>'Udatsu｜'.$course['title'],'start'=>['dateTime'=>$s['start'],'timeZone'=>'Asia/Tokyo'],'end'=>['dateTime'=>$s['end'],'timeZone'=>'Asia/Tokyo'],'location'=>$s['location'],'visibility'=>'private','description'=>'運営：ニストスタジオ。参加者は講座管理画面で確認。']),['Authorization: Bearer '.$this->token(),'Content-Type: application/json']);}
        catch(UError $e){if($e->http!==409)throw $e;}
    }
    public function email(array $o,array $s,array $course): void {
        if($this->c['mode']!=='live')return;
        $text=$o['name']." 様\n\n".$course['title']."\n".date('Y/m/d H:i',strtotime($s['start']))."（日本時間） / 60分\n".$s['format'].'：'.$s['location']."\n".($s['meeting_url']?'参加URL：'.$s['meeting_url']: '')."\nお支払い：".number_format($o['amount'])."円\n\n準備するもの：".$course['preparation']."\n\n予約確認：".$this->c['base'].'/?booking='.$o['id']."\n\nキャンセル条件：".$o['policy']."\nお問い合わせ：".$this->c['support']."\n\nUdatsu / ニストスタジオ\n秋山 那由他";
        if($this->c['mailTransport']==='php'){
            if(!filter_var($this->c['mailFrom'],FILTER_VALIDATE_EMAIL))throw new UError('メール送信元をご確認ください。',503);
            mb_language('Japanese');mb_internal_encoding('UTF-8');
            if(!mb_send_mail($o['email'],'【Udatsu】お申し込みが確定しました',$text,['From'=>$this->c['mailFrom']]))throw new UError('メール送信を再試行します。',503);
        }else{
            $this->request('https://api.resend.com/emails','POST',json_encode(['from'=>$this->c['mailFrom'],'to'=>[$o['email']],'subject'=>'【Udatsu】お申し込みが確定しました','text'=>$text]),['Authorization: Bearer '.$this->c['emailKey'],'Content-Type: application/json','Idempotency-Key: udatsu-email-'.$o['id']]);
        }
    }
}
function ensureCheckout(UStore $s,UIntegrations $i,array $o): array {
    if($o['session_id'])return ['id'=>$o['session_id'],'url'=>$o['checkout_url']];
    $session=$i->c['mode']==='demo'?['id'=>'demo_'.$o['id'],'url'=>$i->c['base'].'/?booking='.$o['id']]:$i->createCheckout($o,$s->slot($o['slot_id']),$s->course($o['course_id']));
    $s->attach($o['id'],$session);return $session;
}
function reconcile(UStore $s,UIntegrations $i,array $o): void {
    if($o['status']!=='pending')return;
    if($i->c['mode']==='demo'){if($o['expires']<=time())$s->release($o['id']);return;}
    $session=ensureCheckout($s,$i,$o);$latest=$i->getCheckout($session['id']);
    if($latest['status']==='complete'&&$latest['payment_status']==='paid')$s->settle($latest,$i->c['mode']==='live');
    elseif($latest['status']==='expired')$s->release($o['id']);
}
function tick(UStore $s,UIntegrations $i): void {
    $lock=fopen(__DIR__.'/private/jobs.lock','c');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))return;
    try {
    foreach($s->q("SELECT * FROM orders WHERE status='pending' ORDER BY created LIMIT 100")->fetchAll() as $o){
        try{reconcile($s,$i,$o);}catch(Throwable $e){$s->q('UPDATE orders SET note=? WHERE id=?',['決済状態の再確認が必要です。枠は保持しています。',$o['id']]);}
    }
    foreach($s->q('SELECT * FROM jobs WHERE done=0 ORDER BY rowid LIMIT 100')->fetchAll() as $j){
        try{
            if($j['kind']==='calendar'){$slot=$s->slot($j['reference']);$i->createEvent($slot,$s->course($slot['course_id']));}
            else{$o=$s->order($j['reference']);$i->email($o,$s->slot($o['slot_id']),$s->course($o['course_id']));}
            $s->q("UPDATE jobs SET done=1,attempts=attempts+1,last_error='' WHERE id=?",[$j['id']]);
        }catch(Throwable $e){$s->q('UPDATE jobs SET attempts=attempts+1,last_error=? WHERE id=?',['外部連携を再試行しています。設定をご確認ください。',$j['id']]);}
    }
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
