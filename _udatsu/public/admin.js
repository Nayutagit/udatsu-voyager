const $=s=>document.querySelector(s);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt=v=>new Intl.DateTimeFormat('ja-JP',{timeZone:'Asia/Tokyo',dateStyle:'short',timeStyle:'short'}).format(new Date(v));
let token='',courses=[];
async function request(url,input){const r=await fetch(url,{headers:{Authorization:'Bearer '+token,...(input?{'Content-Type':'application/json'}:{})},...(input?{method:'POST',body:JSON.stringify(input)}:{})});const d=await r.json();if(!r.ok)throw new Error(d.error);return d;}
function message(text,error=false){$('#admin-message').textContent=text;$('#admin-message').className=error?'error':'admin-message';}
async function refresh(){
 const d=await request('/api/admin/overview');
 if(!courses.length)courses=(await(await fetch('/api/catalog')).json()).courses;
 const title=id=>courses.find(c=>c.id===id)?.title||'未確定';
 $('#dashboard').hidden=false;$('#slot-editor').hidden=false;$('#logout').hidden=false;
 $('#connection').textContent=`モード：${d.mode} / Googleカレンダー：${d.calendarConnected?'接続設定あり':'未設定'}`;
 $('#jobs').innerHTML=d.jobs.length?d.jobs.map(j=>`<p class="error">${esc(j.kind)}：${esc(j.last_error||'処理待ち')} / 試行${j.attempts}回</p>`).join(''):'<p class="quiet">未処理の連携はありません。</p>';
 $('#slots').innerHTML='<table class="admin-table"><thead><tr><th>日時・会場</th><th>テーマ</th><th>受付</th></tr></thead><tbody>'+d.slots.map(s=>`<tr><td>${fmt(s.start)}<br>${esc(s.format)} / ${esc(s.location)}</td><td>${esc(title(s.course_id))}<br>${s.fixed?'開催確定':'未確定／手続き中'}</td><td><button data-close="${s.id}" class="button small">枠を閉じる</button></td></tr>`).join('')+'</tbody></table>';
 $('#orders').innerHTML='<table class="admin-table"><thead><tr><th>参加者</th><th>講座・日時</th><th>状態</th></tr></thead><tbody>'+d.orders.map(o=>`<tr><td>${esc(o.name)}<br>${esc(o.email)}</td><td>${esc(title(o.course_id))}<br>${fmt(d.slots.find(s=>s.id===o.slot_id)?.start||o.created)}</td><td>${esc({paid:'決済済み',pending:'手続き中',expired:'期限終了'}[o.status]||o.status)}<br>${o.amount}円${o.note?'<br>'+esc(o.note):''}<br><small>${esc(o.session_id||'決済作成待ち')}</small></td></tr>`).join('')+'</tbody></table>';
 document.querySelectorAll('[data-close]').forEach(b=>b.onclick=async()=>{try{await request('/api/admin/close',{slotId:b.dataset.close});await refresh();message('募集枠を閉じました。');}catch(e){message(e.message,true);}});
}
$('#login').onsubmit=async e=>{e.preventDefault();token=$('#token').value;try{await refresh();$('#token').value='';message('管理画面を開きました。');}catch(e){token='';message(e.message,true);}};
$('#logout').onclick=()=>{token='';$('#dashboard').hidden=true;$('#slot-editor').hidden=true;$('#logout').hidden=true;$('#slots').innerHTML='';$('#orders').innerHTML='';message('ログアウトしました。');};
$('#slot-form').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;try{const d=Object.fromEntries(new FormData(form));d.start=new Date(d.start+':00+09:00').toISOString();d.end=new Date(d.end+':00+09:00').toISOString();await request('/api/admin/slots',d);await refresh();message('募集枠を登録しました。');}catch(e){message(e.message,true);}};
$('#retry').onclick=async()=>{try{await request('/api/admin/retry',{});await refresh();message('再確認しました。');}catch(e){message(e.message,true);}};
