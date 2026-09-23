const $=s=>document.querySelector(s);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt=v=>new Intl.DateTimeFormat('ja-JP',{timeZone:'Asia/Tokyo',dateStyle:'short',timeStyle:'short'}).format(new Date(v));
const jday=v=>new Intl.DateTimeFormat('ja-JP',{timeZone:'Asia/Tokyo',month:'long',day:'numeric',weekday:'short'}).format(new Date(v));
const jtime=v=>new Intl.DateTimeFormat('ja-JP',{timeZone:'Asia/Tokyo',hour:'2-digit',minute:'2-digit',hour12:false}).format(new Date(v));
const span=s=>`${jday(s.start)} ${jtime(s.start)}–${jtime(s.end)}`;
let token='',courses=[],slotsData=[],instructorsData=[],busyData={mine:[],reference:[]},weekStart=null,selected=new Set(),busyToken=0,overview=null;
async function request(url,input){const r=await fetch(url,{headers:{Authorization:'Bearer '+token,...(input?{'Content-Type':'application/json'}:{})},...(input?{method:'POST',body:JSON.stringify(input)}:{})});const d=await r.json();if(!r.ok)throw new Error(d.error);return d;}
function message(text,error=false){$('#admin-message').textContent=text;$('#admin-message').className=error?'error':'admin-message';}
const MODE_LABEL={preview:'公開準備中（サイトは案内と仮予約のみ。決済はメールで手動案内）',demo:'体験版（Mac内のみ・請求なし）',test:'Stripeテスト（実際の請求なし）',live:'本番受付（サイトで決済まで自動）'};
const JOB_LABEL={calendar:'Googleカレンダーへの予定登録',email:'予約確定メールの送信'};
const ORDER_LABEL={paid:'決済済み（参加確定）',pending:'お支払い手続き中',expired:'期限終了'};
const title=id=>courses.find(c=>c.id===id)?.title||'未確定（最初の方が選びます）';
const who=id=>instructorsData.find(i=>i.id===id)?.name||'—';
async function refresh(){
 const d=await request('/api/admin/overview');overview=d;
 if(!courses.length)courses=(await(await fetch('/api/catalog')).json()).courses;
 $('#dashboard').hidden=false;$('#slot-editor').hidden=false;$('#slot-calendar').hidden=false;$('#instructor-editor').hidden=false;slotsData=d.slots;instructorsData=d.instructors||[];fillOptions();renderCalendar();$('#logout').hidden=false;$('#login').hidden=true;document.querySelector('.admin-login .quiet').hidden=true;document.querySelector('.admin-login h2').textContent='ログイン中';
 const now=Date.now(),upcoming=d.slots.filter(s=>Date.parse(s.end)>now),past=d.slots.filter(s=>Date.parse(s.end)<=now);
 const paid=d.orders.filter(o=>o.status==='paid'),pending=d.orders.filter(o=>o.status==='pending'),week=upcoming.filter(s=>Date.parse(s.start)<now+7*86400000);
 const noUrl=upcoming.filter(s=>s.format==='オンライン'&&!s.meeting_url).length;
 const tile=(n,label,sub='')=>`<div class="stat"><strong>${n}</strong><span>${label}</span>${sub?`<small>${sub}</small>`:''}</div>`;
 $('#summary').innerHTML=tile(upcoming.length,'今後の募集枠',upcoming[0]?'次：'+span(upcoming[0]):'まだありません')+tile(week.length,'7日以内の枠')+tile(upcoming.filter(s=>s.fixed).length,'開催確定の枠','サイト決済分')+tile(noUrl,'参加URL未設定',noUrl?'確定メールの前に用意':'');
 $('#connection').textContent=`モード：${MODE_LABEL[d.mode]||d.mode} / Googleカレンダー：${d.calendarConnected?'接続設定あり':'予約の書き込み先は未設定'}`;
 $('#jobs').innerHTML=(d.jobs.length?d.jobs.map(j=>`<p class="error">${esc(JOB_LABEL[j.kind]||j.kind)}：${esc(j.last_error||'処理待ち')}（試行${j.attempts}回）</p>`).join(''):'')+(pending.length?`<p class="error">お支払い手続き中の方が${pending.length}名います。下の「サイトからの決済」を確認してください。</p>`:'')||'<p class="quiet">対応が必要な連携・手続きはありません。</p>';
 const row=s=>{const c=courses.find(x=>x.id===s.course_id),n=d.orders.filter(o=>o.slot_id===s.id&&o.status==='paid').length,allowed=s.allowed?s.allowed.split(','):[];
  return `<tr><td><strong>${span(s)}</strong><br>${esc(s.format)} / ${esc(s.location)}<br>講師：${esc(who(s.instructor_id))}</td><td>${esc(title(s.course_id))}<br><small title="${esc(allowed.map(title).join('、'))}">受付：${allowed.length?allowed.length+'講義のみ':'すべての講義'}</small></td><td>${s.fixed?'開催確定':'募集中'}<br><small>サイト決済 ${n}${c?' / 定員'+c.capacity:''}名</small><br><small>参加URL：${s.meeting_url?'設定済み':'未設定'}</small></td><td class="actions"><button type="button" data-mail="${s.id}" class="button small">案内メールを作る</button><button type="button" data-close="${s.id}" class="button small">枠を閉じる</button></td></tr>`;};
 const table=list=>'<table class="admin-table"><thead><tr><th>日時・会場</th><th>講座</th><th>状態</th><th></th></tr></thead><tbody>'+list.map(row).join('')+'</tbody></table>';
 $('#slots').innerHTML=upcoming.length?table(upcoming):'<p class="quiet">今後の募集枠はありません。上のカレンダーから登録できます。</p>';
 $('#past-slots-box').hidden=!past.length;$('#past-slots').innerHTML=past.length?table(past.slice().reverse()):'';
 renderOrders();
 document.querySelectorAll('[data-close]').forEach(b=>b.onclick=async()=>{
  if(b.dataset.armed!=='1'){b.dataset.armed='1';b.textContent='本当に閉じる？';b.classList.add('danger');setTimeout(()=>{if(b.isConnected){b.dataset.armed='';b.textContent='枠を閉じる';b.classList.remove('danger');}},4000);return;}
  try{await request('/api/admin/close',{slotId:b.dataset.close});await refresh();message('募集枠を閉じました。');}catch(e){message(e.message,true);}});
 document.querySelectorAll('[data-mail]').forEach(b=>b.onclick=()=>{fillMailSlots();$('#mail-form').elements.slot.value=b.dataset.mail;applySlot();$('#mail-tool').scrollIntoView({behavior:'smooth'});});
 fillMailSlots();
}
function renderOrders(){
 const d=overview;if(!d)return;const all=$('#show-expired').checked,list=d.orders.filter(o=>all||o.status!=='expired');
 $('#orders-note').textContent=d.mode==='preview'?'いまは手動決済の運用です。決済リンクからの入金はここには出ないので、Stripeの支払い一覧で確認してください。':'サイトで決済した方が表示されます。「決済済み」が参加確定、「手続き中」は参加者に数えません。';
 $('#orders').innerHTML=list.length?'<table class="admin-table"><thead><tr><th>参加者</th><th>講座・日時</th><th>状態</th></tr></thead><tbody>'+list.map(o=>{const s=d.slots.find(x=>x.id===o.slot_id);return `<tr><td>${esc(o.name)}<br>${esc(o.email)}</td><td>${esc(title(o.course_id))}<br>${s?span(s):'（閉じた枠）'}</td><td>${esc(ORDER_LABEL[o.status]||o.status)}<br>${Number(o.amount).toLocaleString('ja-JP')}円${o.note?'<br>'+esc(o.note):''}<br><small>申込：${fmt(o.created)}</small></td></tr>`;}).join('')+'</tbody></table>':'<p class="quiet">表示する決済はありません。</p>';
}
$('#show-expired').onchange=renderOrders;
$('#login').onsubmit=async e=>{e.preventDefault();token=$('#token').value;try{await refresh();$('#token').value='';message('管理画面を開きました。');}catch(e){token='';message(e.message,true);}};
$('#logout').onclick=()=>{token='';overview=null;$('#dashboard').hidden=true;$('#slot-editor').hidden=true;$('#slot-calendar').hidden=true;$('#instructor-editor').hidden=true;selected.clear();$('#logout').hidden=true;$('#login').hidden=false;document.querySelector('.admin-login .quiet').hidden=false;document.querySelector('.admin-login h2').textContent='管理者ログイン';for(const id of ['#slots','#past-slots','#orders','#summary','#jobs'])$(id).innerHTML='';$('#connection').textContent='';$('#mail-form').reset();$('#mail-subject').value='';$('#mail-body').value='';message('ログアウトしました。');};
$('#slot-form').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;try{const d=Object.fromEntries(new FormData(form));d.start=new Date(d.start+':00+09:00').toISOString();d.end=new Date(d.end+':00+09:00').toISOString();await request('/api/admin/slots',d);await refresh();message('募集枠を登録しました。');}catch(e){message(e.message,true);}};
$('#retry').onclick=async()=>{try{await request('/api/admin/retry',{});await refresh();message('再確認しました。');}catch(e){message(e.message,true);}};

// ---- メールのひな形（手動決済の運用）
// 1回券の決済リンク（Stripe本番・4,400円）。キャンセル条件は app.js の LEGAL.cancellation と揃える。
const PAY_LINK='https://buy.stripe.com/aFadRb0ITep25mmdVrbV60e',SITE='https://udatsuageteko.com/',SUPPORT='contact@nyct.jp';
const CANCEL_SHORT=`・お支払い後、開催の3日前（72時間前）まで：全額返金、または1回に限り別日程へ振替
・開催の3日前を過ぎてから：返金なし（前日までのご連絡で、1回に限り別日程へ振替）
・当日のご連絡・無断欠席：返金・振替なし
・こちらの都合で開催できない場合：全額返金、または別日程へ振替
くわしくは ${SITE} の「特定商取引法に基づく表記」をご覧ください。`;
const SIGN=`\n\n――――――――――\nUdatsu（ウダツ）／ニストスタジオ\n講師　秋山 那由他\n${SITE}\nお問い合わせ：${SUPPORT}\n（このメールへの返信でもご連絡いただけます）`;
const payUrl=v=>PAY_LINK+'?'+new URLSearchParams({...(v.email?{prefilled_email:v.email}:{}),...(v.ref?{client_reference_id:v.ref}:{})});
const MAILS={
 pay:{label:'① お支払いのご案内（日程OK）',subject:v=>`【Udatsu】日程確定とお支払いのご案内（${v.course}）`,body:v=>`${v.name} 様

Udatsuに仮予約いただき、ありがとうございます。講師の秋山 那由他です。
ご希望の日程で開催できます。内容をご確認のうえ、下のリンクからお支払いをお願いします。

■ お申し込み内容
講座：${v.course}
日時：${v.when}（日本時間）／60分
開催：オンライン（参加URLは、お支払いの確認後にお送りします）
受講料：4,400円（お支払い総額）
予約番号：${v.ref}

■ お支払い（クレジットカード・Stripe）
${payUrl(v)}
お支払い期限：${v.deadline}
※期限までにお支払いが確認できない場合、仮予約は取り消しとなります。
※お支払い画面では、このメールを受け取ったメールアドレスをお使いください。

■ キャンセル・返金について
${CANCEL_SHORT}

当日お会いできるのを楽しみにしています。${SIGN}`},
 reschedule:{label:'② 日程変更のお願い（日程NG）',subject:v=>`【Udatsu】ご希望日時についてのご相談（${v.course}）`,body:v=>`${v.name} 様

Udatsuに仮予約いただき、ありがとうございます。講師の秋山 那由他です。
申し訳ありません。ご希望の ${v.when} は、都合により開催が難しくなりました。

代わりに、次の日時でしたらご案内できます。
・（候補日時を書く）
・（候補日時を書く）

ご都合のよい日時を、このメールへの返信でお知らせください。
いずれも難しい場合は、ご希望の曜日や時間帯を教えていただければ調整します。
この時点で料金は発生していません。${SIGN}`},
 confirm:{label:'③ 予約確定・参加URL（入金確認後）',subject:v=>`【Udatsu】ご予約が確定しました（${v.course}）`,body:v=>`${v.name} 様

お支払いを確認しました。ありがとうございます。
以下の内容で、ご予約が確定しました。

■ ご予約内容
講座：${v.course}
日時：${v.when}（日本時間）／60分
受講料：4,400円（${v.paidOn}にお支払いを確認しました）
予約番号：${v.ref}

■ 当日の参加方法（オンライン）
参加URL：${v.meetingUrl||'（開催の前日までに、あらためてお送りします）'}
開始の5分前から入室できます。
${v.prep?`準備するもの：${v.prep}\n`:''}
■ キャンセル・日程変更
${CANCEL_SHORT}

当日お会いできるのを楽しみにしています。${SIGN}`},
 remind:{label:'④ 前日のリマインド',subject:v=>`【Udatsu】明日の講座のご案内（${v.course}）`,body:v=>`${v.name} 様

明日の講座のご案内です。

講座：${v.course}
日時：${v.when}（日本時間）／60分
参加URL：${v.meetingUrl||'（URLを書く）'}
${v.prep?`準備するもの：${v.prep}\n`:''}
開始の5分前から入室できます。つながらない場合は、このメールへの返信かLINEでお知らせください。

明日、お会いできるのを楽しみにしています。${SIGN}`},
 cancel:{label:'⑤ 仮予約の取り消し（期限切れ）',subject:v=>`【Udatsu】仮予約の取り消しについて（${v.course}）`,body:v=>`${v.name} 様

Udatsuに仮予約いただき、ありがとうございました。
${v.when} の「${v.course}」は、お支払い期限（${v.deadline}）までにお支払いが確認できなかったため、仮予約を取り消しました。
料金は発生していません。

行き違いでお支払い済みの場合や、別の日程をご希望の場合は、このメールへの返信でお知らせください。
またお会いできる日を楽しみにしています。${SIGN}`}
};
const pad=n=>String(n).padStart(2,'0');
function newRef(start){const d=new Date((start?Date.parse(start):Date.now())+9*3600000);return 'U'+pad(d.getUTCMonth()+1)+pad(d.getUTCDate())+'-'+Math.random().toString(36).slice(2,6).toUpperCase();}
function deadlineFor(start){const limit=Math.min(Date.now()+2*86400000,start?Date.parse(start)-86400000:Infinity);return jday(Math.max(limit,Date.now()))+' 23:59まで';}
function mailValues(){const f=$('#mail-form'),c=courses.find(x=>x.id===f.elements.course.value);return {name:f.elements.name.value.trim()||'（お名前）',email:f.elements.email.value.trim(),course:c?.title||'（講座名）',prep:c?.preparation||'',when:f.elements.when.value.trim()||'（日時）',deadline:f.elements.deadline.value.trim()||'（期限）',paidOn:f.elements.paidOn.value.trim()||'（入金日）',meetingUrl:f.elements.meetingUrl.value.trim(),ref:f.elements.ref.value.trim()};}
function renderMail(){
 const kind=$('#mail-kind').value,m=MAILS[kind],v=mailValues();
 document.querySelectorAll('#mail-form [data-for]').forEach(x=>x.hidden=!x.dataset.for.split(' ').includes(kind));
 $('#mail-subject').value=m.subject(v);$('#mail-body').value=m.body(v);
 $('#mail-note').textContent=!v.email?'メールアドレスを入れると、決済リンクにも自動で入ります。':kind==='pay'&&!v.ref?'予約番号が空です。Stripeで入金を照合しやすくするため、入れておくのがおすすめです。':kind==='confirm'?'予約番号は、①のメールで送った番号に合わせてください（Stripeの支払い詳細の「client_reference_id」で確認できます）。':'';
}
function fillMailSlots(){
 const sel=$('#mail-slot'),keep=sel.value,now=Date.now();
 sel.innerHTML='<option value="">（手入力する）</option>'+slotsData.filter(s=>Date.parse(s.end)>now).map(s=>`<option value="${esc(s.id)}">${esc(span(s))}${s.course_id?' / '+esc(title(s.course_id)):''}</option>`).join('');
 if([...sel.options].some(o=>o.value===keep))sel.value=keep;
 const cs=$('#mail-course'),ck=cs.value;cs.innerHTML=courses.map(c=>`<option value="${esc(c.id)}">${esc(c.title)}</option>`).join('');if(ck)cs.value=ck;
 const f=$('#mail-form');if(!f.elements.paidOn.value)f.elements.paidOn.value=jday(now);if(!f.elements.deadline.value)f.elements.deadline.value=deadlineFor();if(!f.elements.ref.value)f.elements.ref.value=newRef();
 renderMail();
}
function applySlot(){const f=$('#mail-form'),s=slotsData.find(x=>x.id===f.elements.slot.value);if(s){f.elements.when.value=span(s);f.elements.meetingUrl.value=s.meeting_url||'';if(s.course_id)f.elements.course.value=s.course_id;f.elements.deadline.value=deadlineFor(s.start);f.elements.ref.value=newRef(s.start);}renderMail();}
if(!$('#mail-kind').options.length)$('#mail-kind').innerHTML=Object.entries(MAILS).map(([k,m])=>`<option value="${k}">${m.label}</option>`).join('');
$('#mail-form').addEventListener('input',e=>{if(e.target.name==='slot')applySlot();else renderMail();});
$('#mail-form').addEventListener('change',e=>{if(e.target.name==='slot')applySlot();else renderMail();});
async function copy(text,label){try{await navigator.clipboard.writeText(text);message(label+'をコピーしました。');}catch(e){message('コピーできませんでした。文字を選んで手動でコピーしてください。',true);}}
$('#copy-subject').onclick=()=>copy($('#mail-subject').value,'件名');
$('#copy-body').onclick=()=>copy($('#mail-body').value,'本文');
$('#copy-link').onclick=()=>copy(payUrl(mailValues()),'決済リンク');
const mailParts=()=>({to:$('#mail-form').elements.email.value.trim(),su:$('#mail-subject').value,body:$('#mail-body').value});
$('#mail-gmail').onclick=()=>{const p=mailParts();window.open('https://mail.google.com/mail/?'+new URLSearchParams({view:'cm',fs:'1',to:p.to,su:p.su,body:p.body}),'_blank','noopener');};
$('#mail-app').onclick=()=>{const p=mailParts();location.href='mailto:'+encodeURIComponent(p.to)+'?subject='+encodeURIComponent(p.su)+'&body='+encodeURIComponent(p.body);};

// ---- カレンダーで募集枠を選ぶ（日本時間で計算）
const HOUR0=7,HOUR1=22,STEP=30*60000,DAY=86400000,JST=9*3600000,WD=['日','月','火','水','木','金','土'];
function jstMidnight(ms){return Math.floor((ms+JST)/DAY)*DAY-JST;}
const mobileMq=matchMedia('(max-width:760px)'),viewDays=()=>mobileMq.matches?1:7;
function thisWeek(){const t=jstMidnight(Date.now());return viewDays()===1?t:t-new Date(t+JST).getUTCDay()*DAY;}
function jparts(ms){const d=new Date(ms+JST);return {m:d.getUTCMonth()+1,d:d.getUTCDate(),h:d.getUTCHours(),n:d.getUTCMinutes(),w:d.getUTCDay()};}
const hhmm=ms=>{const p=jparts(ms);return String(p.h).padStart(2,'0')+':'+String(p.n).padStart(2,'0');};
const hit=(list,a,b)=>list.some(x=>Date.parse(x.start)<b&&Date.parse(x.end)>a);
const isoJ=ms=>new Date(ms).toISOString();
function selectedHit(a,b){return [...selected].some(t=>t<b&&t+2*STEP>a);}
async function loadBusy(){
  const my=++busyToken,from=weekStart,to=weekStart+viewDays()*DAY;
  try{const d=await request('/api/admin/busy?from='+encodeURIComponent(isoJ(from))+'&to='+encodeURIComponent(isoJ(to)));if(my!==busyToken)return;busyData=d;
    $('#cal-note').textContent=!d.configured?'Googleカレンダーが未接続のため、予定は表示されません。':d.unreadable?`読み取れないカレンダーが${d.unreadable}件あります（共有設定を確認してください）。`:'';}
  catch(e){if(my!==busyToken)return;busyData={mine:[],reference:[]};$('#cal-note').textContent='予定を読み込めませんでした。重なりの確認は登録時にサーバー側でも行います。';}
  renderCalendar();
}
function renderCalendar(){
  if(weekStart===null){weekStart=thisWeek();loadBusy();}
  const now=Date.now(),g=$('#cal-grid');
  const nd=viewDays(),first=jparts(weekStart),last=jparts(weekStart+(nd-1)*DAY);
  $('#cal-title').textContent=nd===1?`${first.m}/${first.d}（${WD[first.w]}）`:`${first.m}/${first.d}〜${last.m}/${last.d}`;
  $('#cal-prev').textContent=nd===1?'← 前の日':'← 前の週';$('#cal-next').textContent=nd===1?'次の日 →':'次の週 →';$('#cal-today').textContent=nd===1?'今日':'今週';
  let html='<thead><tr><th></th>'+[...Array(nd).keys()].map(i=>{const p=jparts(weekStart+i*DAY);return `<th>${p.m}/${p.d}（${WD[p.w]}）</th>`;}).join('')+'</tr></thead><tbody>';
  for(let m=HOUR0*60;m<HOUR1*60;m+=30){
    html+=`<tr><td class="time">${m%60===0?String(m/60).padStart(2,'0')+':00':''}</td>`;
    for(let i=0;i<nd;i++){
      const a=weekStart+i*DAY+m*60000,b=a+STEP;
      const cls=['cal-cell'];let label='';
      if(b<=now+3600000)cls.push('past');
      else if(selectedHit(a,b)){cls.push('selected');if(selected.has(a))label='選択';}
      else if(hit(slotsData,a,b))cls.push('existing');
      else if(hit(busyData.mine,a,b))cls.push('mine');
      else if(hit(busyData.reference,a,b))cls.push('ref');
      const off=cls.includes('past')||cls.includes('existing')||cls.includes('mine');
      html+=`<td><button type="button" class="${cls.join(' ')}" data-t="${a}" ${off?'disabled':''} aria-label="${hhmm(a)}から60分">${label}</button></td>`;
    }
    html+='</tr>';
  }
  g.innerHTML=html+'</tbody>';
  const list=[...selected].sort((x,y)=>x-y);
  $('#cal-selected').textContent=list.length?'選んだ枠：'+list.map(t=>{const p=jparts(t);return `${p.m}/${p.d}（${WD[p.w]}）${hhmm(t)}〜${hhmm(t+2*STEP)}`;}).join('、'):'選んだ枠：なし';
  $('#cal-submit').disabled=!list.length;
  g.querySelectorAll('.cal-cell:not(:disabled)').forEach(b=>b.onclick=()=>toggleCell(Number(b.dataset.t)));
}
function toggleCell(a){
  if(selected.has(a)){selected.delete(a);renderCalendar();return;}
  const b=a+2*STEP;
  if(selectedHit(a,b)){message('選んだ枠同士が重なっています。',true);return;}
  if(hit(slotsData,a,b)||hit(busyData.mine,a,b)){message('この時間には登録済みの枠か、自分の予定があります。',true);return;}
  if(a<Date.now()+3600000){message('1時間以上先の時間を選んでください。',true);return;}
  if(hit(busyData.reference,a,b))message('参考カレンダー（HAIなど）の予定と重なっています。問題なければそのまま登録できます。');else message('');
  selected.add(a);renderCalendar();
}
function moveWeek(days){weekStart=days===0?thisWeek():weekStart+days*DAY;busyData={mine:[],reference:[]};renderCalendar();loadBusy();}
$('#cal-prev').onclick=()=>moveWeek(-viewDays());$('#cal-next').onclick=()=>moveWeek(viewDays());
mobileMq.addEventListener('change',()=>{weekStart=null;busyData={mine:[],reference:[]};if(!$('#slot-calendar').hidden)renderCalendar();});$('#cal-today').onclick=()=>moveWeek(0);
$('#cal-form').onsubmit=async e=>{
  e.preventDefault();const f=Object.fromEntries(new FormData(e.currentTarget)),list=[...selected].sort((x,y)=>x-y),failed=[];let ok=0;
  const all=$('#cal-all').checked,picked=[...document.querySelectorAll('#cal-courses input:checked')].map(x=>x.value);
  if(!all&&!picked.length){message('受け付ける講義を1つ以上選んでください。',true);return;}
  $('#cal-submit').disabled=true;
  for(const a of list){try{await request('/api/admin/slots',{start:isoJ(a),end:isoJ(a+2*STEP),format:f.format,location:f.location,meetingUrl:f.meetingUrl||'',instructorId:f.instructorId,allowedCourses:all?[]:picked});selected.delete(a);ok++;}catch(err){const p=jparts(a);failed.push(`${p.m}/${p.d} ${hhmm(a)}：${err.message}`);}}
  try{await refresh();}catch(err){}
  message(`${ok}件の枠を登録しました。`+(failed.length?' 登録できなかった枠：'+failed.join(' / '):''),failed.length>0);
};

function fillOptions(){
  const sel=$('#cal-instructor'),keep=sel.value;
  sel.innerHTML=instructorsData.map(i=>`<option value="${esc(i.id)}">${esc(i.name)}</option>`).join('');
  if(keep&&instructorsData.some(i=>i.id===keep))sel.value=keep;
  const box=$('#cal-courses'),checked=new Set([...box.querySelectorAll('input:checked')].map(x=>x.value)),all=$('#cal-all').checked;
  box.innerHTML=courses.map(c=>`<label class="check-field"><input type="checkbox" value="${esc(c.id)}" ${all||checked.has(c.id)?'checked':''} ${all?'disabled':''}>${esc(c.title)}<small>（${esc(c.category)}）</small></label>`).join('');
}
$('#cal-all').onchange=()=>{const all=$('#cal-all').checked;document.querySelectorAll('#cal-courses input').forEach(x=>{x.disabled=all;if(all)x.checked=true;});};
$('#instructor-form').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;try{await request('/api/admin/instructors',{name:new FormData(form).get('name')});form.reset();await refresh();message('講師を追加しました。');}catch(err){message(err.message,true);}};
