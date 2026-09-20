const $=s=>document.querySelector(s);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt=v=>new Intl.DateTimeFormat('ja-JP',{timeZone:'Asia/Tokyo',dateStyle:'short',timeStyle:'short'}).format(new Date(v));
let token='',courses=[],slotsData=[],instructorsData=[],busyData={mine:[],reference:[]},weekStart=null,selected=new Set(),busyToken=0;
async function request(url,input){const r=await fetch(url,{headers:{Authorization:'Bearer '+token,...(input?{'Content-Type':'application/json'}:{})},...(input?{method:'POST',body:JSON.stringify(input)}:{})});const d=await r.json();if(!r.ok)throw new Error(d.error);return d;}
function message(text,error=false){$('#admin-message').textContent=text;$('#admin-message').className=error?'error':'admin-message';}
async function refresh(){
 const d=await request('/api/admin/overview');
 if(!courses.length)courses=(await(await fetch('/api/catalog')).json()).courses;
 const title=id=>courses.find(c=>c.id===id)?.title||'未確定';
 $('#dashboard').hidden=false;$('#slot-editor').hidden=false;$('#slot-calendar').hidden=false;$('#instructor-editor').hidden=false;slotsData=d.slots;instructorsData=d.instructors||[];fillOptions();renderCalendar();$('#logout').hidden=false;
 $('#connection').textContent=`モード：${d.mode} / Googleカレンダー：${d.calendarConnected?'接続設定あり':'未設定'}`;
 $('#jobs').innerHTML=d.jobs.length?d.jobs.map(j=>`<p class="error">${esc(j.kind)}：${esc(j.last_error||'処理待ち')} / 試行${j.attempts}回</p>`).join(''):'<p class="quiet">未処理の連携はありません。</p>';
 $('#slots').innerHTML='<table class="admin-table"><thead><tr><th>日時・会場</th><th>テーマ</th><th>受付</th></tr></thead><tbody>'+d.slots.map(s=>`<tr><td>${fmt(s.start)}<br>${esc(s.format)} / ${esc(s.location)}<br>講師：${esc((d.instructors||[]).find(i=>i.id===s.instructor_id)?.name||'—')}</td><td>${esc(title(s.course_id))}<br>${s.fixed?'開催確定':'未確定／手続き中'}<br><small>受付：${s.allowed?s.allowed.split(',').length+'講義のみ':'すべての講義'}</small></td><td><button data-close="${s.id}" class="button small">枠を閉じる</button></td></tr>`).join('')+'</tbody></table>';
 $('#orders').innerHTML='<table class="admin-table"><thead><tr><th>参加者</th><th>講座・日時</th><th>状態</th></tr></thead><tbody>'+d.orders.map(o=>`<tr><td>${esc(o.name)}<br>${esc(o.email)}</td><td>${esc(title(o.course_id))}<br>${fmt(d.slots.find(s=>s.id===o.slot_id)?.start||o.created)}</td><td>${esc({paid:'決済済み',pending:'手続き中',expired:'期限終了'}[o.status]||o.status)}<br>${o.amount}円${o.note?'<br>'+esc(o.note):''}<br><small>${esc(o.session_id||'決済作成待ち')}</small></td></tr>`).join('')+'</tbody></table>';
 document.querySelectorAll('[data-close]').forEach(b=>b.onclick=async()=>{try{await request('/api/admin/close',{slotId:b.dataset.close});await refresh();message('募集枠を閉じました。');}catch(e){message(e.message,true);}});
}
$('#login').onsubmit=async e=>{e.preventDefault();token=$('#token').value;try{await refresh();$('#token').value='';message('管理画面を開きました。');}catch(e){token='';message(e.message,true);}};
$('#logout').onclick=()=>{token='';$('#dashboard').hidden=true;$('#slot-editor').hidden=true;$('#slot-calendar').hidden=true;$('#instructor-editor').hidden=true;selected.clear();$('#logout').hidden=true;$('#slots').innerHTML='';$('#orders').innerHTML='';message('ログアウトしました。');};
$('#slot-form').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;try{const d=Object.fromEntries(new FormData(form));d.start=new Date(d.start+':00+09:00').toISOString();d.end=new Date(d.end+':00+09:00').toISOString();await request('/api/admin/slots',d);await refresh();message('募集枠を登録しました。');}catch(e){message(e.message,true);}};
$('#retry').onclick=async()=>{try{await request('/api/admin/retry',{});await refresh();message('再確認しました。');}catch(e){message(e.message,true);}};

// ---- カレンダーで募集枠を選ぶ（日本時間で計算）
const HOUR0=7,HOUR1=22,STEP=30*60000,DAY=86400000,JST=9*3600000,WD=['日','月','火','水','木','金','土'];
function jstMidnight(ms){return Math.floor((ms+JST)/DAY)*DAY-JST;}
function thisWeek(){const t=jstMidnight(Date.now());return t-new Date(t+JST).getUTCDay()*DAY;}
function jparts(ms){const d=new Date(ms+JST);return {m:d.getUTCMonth()+1,d:d.getUTCDate(),h:d.getUTCHours(),n:d.getUTCMinutes(),w:d.getUTCDay()};}
const hhmm=ms=>{const p=jparts(ms);return String(p.h).padStart(2,'0')+':'+String(p.n).padStart(2,'0');};
const hit=(list,a,b)=>list.some(x=>Date.parse(x.start)<b&&Date.parse(x.end)>a);
const isoJ=ms=>new Date(ms).toISOString();
function selectedHit(a,b){return [...selected].some(t=>t<b&&t+2*STEP>a);}
async function loadBusy(){
  const my=++busyToken,from=weekStart,to=weekStart+7*DAY;
  try{const d=await request('/api/admin/busy?from='+encodeURIComponent(isoJ(from))+'&to='+encodeURIComponent(isoJ(to)));if(my!==busyToken)return;busyData=d;
    $('#cal-note').textContent=!d.configured?'Googleカレンダーが未接続のため、予定は表示されません。':d.unreadable?`読み取れないカレンダーが${d.unreadable}件あります（共有設定を確認してください）。`:'';}
  catch(e){if(my!==busyToken)return;busyData={mine:[],reference:[]};$('#cal-note').textContent='予定を読み込めませんでした。重なりの確認は登録時にサーバー側でも行います。';}
  renderCalendar();
}
function renderCalendar(){
  if(weekStart===null){weekStart=thisWeek();loadBusy();}
  const now=Date.now(),g=$('#cal-grid');
  const first=jparts(weekStart),last=jparts(weekStart+6*DAY);
  $('#cal-title').textContent=`${first.m}/${first.d}〜${last.m}/${last.d}`;
  let html='<thead><tr><th></th>'+[...Array(7).keys()].map(i=>{const p=jparts(weekStart+i*DAY);return `<th>${p.m}/${p.d}（${WD[p.w]}）</th>`;}).join('')+'</tr></thead><tbody>';
  for(let m=HOUR0*60;m<HOUR1*60;m+=30){
    html+=`<tr><td class="time">${m%60===0?String(m/60).padStart(2,'0')+':00':''}</td>`;
    for(let i=0;i<7;i++){
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
$('#cal-prev').onclick=()=>moveWeek(-7);$('#cal-next').onclick=()=>moveWeek(7);$('#cal-today').onclick=()=>moveWeek(0);
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
