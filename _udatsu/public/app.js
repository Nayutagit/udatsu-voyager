const $ = s => document.querySelector(s);
const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const yen = n => n.toLocaleString('ja-JP') + '円';
const date = value => new Intl.DateTimeFormat('ja-JP',{timeZone:'Asia/Tokyo',month:'long',day:'numeric',weekday:'short'}).format(new Date(value));
const time = value => new Intl.DateTimeFormat('ja-JP',{timeZone:'Asia/Tokyo',hour:'2-digit',minute:'2-digit',hour12:false}).format(new Date(value));
const dialog = $('#booking-dialog'), body = $('#dialog-body');
let data, slots = [], filter = 'all', scheduleMode = 'open', slotsError = '', currentCourse, selectedSlot, bookingTimer;
async function api(url, input) {
  const res = await fetch(url, { ...(input ? {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(input)} : {}) });
  const value = await res.json();
  if (!res.ok) { const err = new Error(value.error || '通信に失敗しました。');err.bookingId=value.bookingId;throw err; }
  return value;
}
function open(html) { body.innerHTML = html; if (!dialog.open) dialog.showModal(); }
$('#close-dialog').addEventListener('click',()=>dialog.close());
dialog.addEventListener('close',()=>clearTimeout(bookingTimer));
dialog.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();}});
function errorMessage(error) { return `<p class="error" role="alert">${escape(error.message || error)}</p>`; }
function courseCard(c) {
  const symbol = {voice:'“ ”',ai:'✳',work:'↗'}[c.theme];
  return `<article class="course-card"><div class="card-art ${escape(c.theme)}"><span class="tag">${escape(c.category)}</span><span class="card-art-symbol" aria-hidden="true">${symbol}</span></div><div class="card-content"><p class="card-eyebrow">${escape(c.eyebrow)}</p><h3>${escape(c.title)}</h3><p class="card-description">${escape(c.description)}</p><div class="card-meta"><span>${c.minutes}分</span><span>オンライン</span><span>定員${c.capacity}名</span><span>1回完結</span></div><div class="card-bottom"><strong class="card-price">4,400<small>円 / 人</small></strong><button class="card-button" data-course="${c.id}" aria-label="${escape(c.title)}の受講予約">${BOOKING_MODE==='inquiry'?'無料で問い合わせる':'受講予約'} ↗</button></div></div></article>`;
}
function renderCourses() {
  $('#course-grid').innerHTML = data.courses.filter(c=>c.kind==='group'&&(filter==='all'||c.category===filter)).map(courseCard).join('');
  $('#course-grid').querySelectorAll('[data-course]').forEach(b=>b.onclick=()=>showCourse(b.dataset.course));
}
document.querySelectorAll('[data-filter]').forEach(b=>b.addEventListener('click',()=>{
  filter=b.dataset.filter;
  document.querySelectorAll('[data-filter]').forEach(other=>{const selected=other===b;other.classList.toggle('active',selected);other.setAttribute('aria-pressed',selected);});
  renderCourses();
}));
async function refreshSlots() {
  try { slots=(await api('/api/slots')).slots;slotsError=''; }
  catch(e) { slots=[];slotsError=e.message; }
  renderSchedule();
}
function renderSchedule() {
  const list=$('#schedule-list');
  if(slotsError){list.innerHTML=errorMessage(slotsError)+'<button class="button" id="retry-slots">再読み込み</button>';$('#retry-slots').onclick=refreshSlots;return;}
  const visible=slots.filter(s=>scheduleMode==='open'?s.status==='open':s.status==='available');
  if(!visible.length){list.innerHTML=`<div class="empty"><p>${scheduleMode==='open'?'まだ開催が決まった講座はありません。<br>好きな講座と日時を選んで、最初のひとりになりませんか。':'現在ご案内できる空き枠はありません。<br>次回の募集をお待ちください。'}</p><a class="button" href="#courses">講座一覧を見る ↗</a></div>`;return;}
  list.innerHTML=visible.map(s=>{
    const c=data.courses.find(c=>c.id===s.courseId);
    return `<article class="schedule-row"><div class="schedule-date">${date(s.start)}<small>${time(s.start)}–${time(s.end)}</small></div><div class="schedule-info"><span class="badge">${c?'開催確定・'+(s.remaining>0?'あと'+s.remaining+'席':'満席／手続き中'):'テーマを選べます'}</span><strong>${escape(c?.title||'あなたの受けたい講座で開催できます')}</strong><p>${escape(s.format)} / ${escape(s.location)}${s.instructor?' / 講師：'+escape(s.instructor):''}</p></div><button class="button small" data-slot="${s.id}" ${c&&s.remaining===0?'disabled':''}>${c?'この講座に参加する':'講座を選ぶ'} ↗</button></article>`;
  }).join('');
  list.querySelectorAll('[data-slot]').forEach(b=>b.onclick=()=>{
    const slot=slots.find(s=>s.id===b.dataset.slot);
    if(slot.courseId){currentCourse=data.courses.find(c=>c.id===slot.courseId);chooseSlot(slot);}
    else{
      open(`<h2 id="dialog-title">${date(slot.start)} ${time(slot.start)}<br>何を学びますか？</h2><p>最初の方のお支払いで、この時間の講座が決まります。</p><div class="slot-options">${data.courses.filter(c=>Date.parse(slot.end)-Date.parse(slot.start)>=c.minutes*60000&&(!slot.allowed?.length||slot.allowed.includes(c.id))).map(c=>`<button class="slot-option" data-pick="${c.id}"><span>${escape(c.title)}</span><small>${c.minutes}分 / ${yen(c.price)}${c.kind==='private'?' / 1対1':''}</small></button>`).join('')}</div>`);
      body.querySelectorAll('[data-pick]').forEach(button=>button.onclick=()=>{currentCourse=data.courses.find(c=>c.id===button.dataset.pick);showCourse(currentCourse.id,slot);});
    }
  });
}
for(const mode of ['open','available']) $('#show-'+mode).onclick=()=>{
  scheduleMode=mode;
  for(const other of ['open','available']){$('#show-'+other).classList.toggle('active',other===mode);$('#show-'+other).setAttribute('aria-pressed',other===mode);}
  renderSchedule();
};
const BOOKING_MODE = 'inquiry'; // 'inquiry'=問い合わせのみ / 'request'=決済なしの予約リクエスト / 'live'=Stripe決済まで
const LINE_URL = 'https://lin.ee/QPJ3dva', FORM_URL = 'https://formspree.io/f/xanzkprd';
// 手動決済（仮予約→確定メール→Stripe決済）運用中の販売条件。live では設定ファイルの条件を優先。
const LEGAL = {
  seller: '秋山 那由他（屋号：ニストスタジオ）', manager: '秋山 那由他', email: 'contact@nyct.jp',
  disclose: 'ご請求があった場合、遅滞なく電子メールで開示します。上記メールアドレスまでご連絡ください。',
  cancellation: `・お支払い前（仮予約の段階）：いつでも無料で取り消せます。メールまたはLINEでご連絡ください。
・お支払い後、開催の3日前（72時間前）まで：全額を返金します。1回に限り、別の日程への振替もできます。
・開催の3日前を過ぎてから：返金はできません。開催の前日までにご連絡いただければ、1回に限り別の日程へ振り替えます。
・開催当日のご連絡・無断欠席：返金・振替はできません。
・講師の体調不良や通信障害など、こちらの都合で開催できない場合：全額返金、または別の日程への振替をお選びいただけます。
・返金は、お支払いに使ったカードへStripeを通じて行います。反映の時期はカード会社によって異なります。`,
  privacy: `ニストスタジオ（運営責任者：秋山 那由他）は、お申し込み・お問い合わせでいただいたお名前・メールアドレス・ご連絡内容を、次の目的にだけ使います。
・予約の確認、日程の調整、お支払いのご案内
・講座の提供、参加方法のご案内
・お問い合わせへの返信

カード情報は決済代行会社のStripeが処理します。当方がカード番号を受け取ったり保存したりすることはありません。
フォームの受信（Formspree）、決済（Stripe）、日程とメールの管理（Google）、サイトの運用（エックスサーバー）に、必要な範囲で外部サービスを利用します。
法令に基づく場合を除き、ご本人の同意なく第三者に提供しません。
ご自身の情報の確認・訂正・削除をご希望の場合は、contact@nyct.jp までご連絡ください。`
};
const cancelText = () => data.mode==='live' ? data.cancellation : LEGAL.cancellation;
const privacyText = () => data.mode==='live' ? data.privacy : LEGAL.privacy;
function showInquiry(courseId) {
  const c = data.courses.find(x=>x.id===courseId), topic = c ? c.title : 'Udatsuについて';
  open(`<h2 id="dialog-title">無料でお問い合わせ</h2><p class="dialog-meta">${escape(topic)}</p><p>開催日程や内容など、気になることをお気軽にどうぞ。お問い合わせは無料です。講座は基本オンラインで開催します。</p><a class="button line-button wide" href="${LINE_URL}" target="_blank" rel="noopener">LINEで問い合わせる ↗</a><form id="inquiry-form"><input type="hidden" name="_subject" value="Udatsu（udatsuageteko.com）からのお問い合わせ"><input type="hidden" name="topic" value="${escape(topic)}"><input class="hp-field" type="text" name="_gotcha" tabindex="-1" autocomplete="off"><label class="form-field">お名前<input name="name" required maxlength="80" autocomplete="name"></label><label class="form-field">メールアドレス<input type="email" name="email" required maxlength="200" autocomplete="email"></label><label class="form-field">メッセージ<textarea name="message" rows="5" required maxlength="3000"></textarea></label><button class="button dark wide" type="submit">送信する</button><p class="dialog-meta" id="inquiry-status" role="status"></p><p class="dialog-meta">いただいた内容は、お問い合わせへの返信のためだけに使います（受信にはFormspreeを利用しています）。</p></form>`);
  $('#inquiry-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget, button = form.querySelector('[type=submit]'), status = $('#inquiry-status');
    button.disabled = true; status.textContent = '送信しています…';
    try {
      const r = await fetch(FORM_URL, {method:'POST', headers:{Accept:'application/json'}, body:new FormData(form)});
      if (!r.ok) throw new Error('send failed');
      form.innerHTML = '<h3>送信しました。ありがとうございます。</h3><p>内容を確認して、メールまたはLINEでご連絡します。</p>';
    } catch (e) {
      button.disabled = false; status.textContent = '送信できませんでした。時間をおくか、LINEからお問い合わせください。';
    }
  });
}
async function showCourse(courseId, preselected) {
  if (BOOKING_MODE==='inquiry') return showInquiry(courseId);
  currentCourse=data.courses.find(c=>c.id===courseId);
  const c=currentCourse;
  open(`<h2 id="dialog-title">${escape(c.title)}</h2><p class="dialog-meta">${c.minutes}分 / ${yen(c.price)}${c.kind==='group'?' / 定員'+c.capacity+'名':' / 1対1'} / オンライン</p><p>${escape(c.description)}</p><h3>参加する日時を選ぶ</h3><p class="quiet">日本時間（JST） / 開始${data.leadHours}時間前まで受付</p><div id="course-slots">空き状況を確認しています…</div><details class="course-details"><summary>この講座の内容・準備するもの</summary><div class="outcome"><strong>持ち帰れるもの</strong><br>${escape(c.outcome)}</div><h3>この講座でやること</h3><ol>${c.agenda.map(a=>`<li>${escape(a)}</li>`).join('')}</ol><h3>準備するもの</h3><p>${escape(c.preparation)}</p></details>`);
  await refreshSlots();
  if(!dialog.open||currentCourse.id!==c.id||!$('#course-slots'))return;
  if(slotsError){$('#course-slots').innerHTML=errorMessage(slotsError);return;}
  let available=slots.filter(s=>(s.status==='available'||(s.courseId===c.id&&s.remaining>0))&&Date.parse(s.end)-Date.parse(s.start)>=c.minutes*60000&&(!s.allowed?.length||s.allowed.includes(c.id)));
  if(preselected)available=available.filter(s=>s.id===preselected.id);
  if(preselected){const one=available.find(x=>x.id===preselected.id);if(one){chooseSlot(one);return;}}
  renderSlotCalendar(available,c);
}
let calMonth = null; // 表示中の月（年*12+月、日本時間）
const WD = ['日','月','火','水','木','金','土'];
const jstDate = v => new Date(Date.parse(v) + 9*3600000);
const monthKey = v => { const d = jstDate(v); return d.getUTCFullYear()*12 + d.getUTCMonth(); };
function chooseSlot(slot) { return BOOKING_MODE==='live' ? confirmBooking(slot) : confirmRequest(slot); }
function renderSlotCalendar(available, c) {
  const box = $('#course-slots');
  const askLink = '<p class="quiet slot-ask"><button class="text-button" id="ask-dates" type="button">希望の日程がない・質問がある方は、無料でお問い合わせ ↗</button></p>';
  if (!available.length) { box.innerHTML = '<p>ただいま、この講座の受付可能な日程はありません。</p>' + askLink; $('#ask-dates').onclick = () => showInquiry(c.id); return; }
  const months = [...new Set(available.map(s => monthKey(s.start)))].sort((a,b) => a-b);
  if (calMonth === null || !months.includes(calMonth)) calMonth = months[0];
  const y = Math.floor(calMonth/12), m = calMonth % 12;
  const first = new Date(Date.UTC(y, m, 1)).getUTCDay(), days = new Date(Date.UTC(y, m+1, 0)).getUTCDate();
  const idx = months.indexOf(calMonth);
  let cells = WD.map(w => `<div class="slot-dow">${w}</div>`).join('') + '<div class="slot-day pad"></div>'.repeat(first);
  for (let d = 1; d <= days; d++) {
    const list = available.filter(s => monthKey(s.start) === calMonth && jstDate(s.start).getUTCDate() === d);
    const dow = WD[(first + d - 1) % 7];
    cells += `<div class="slot-day ${list.length ? '' : 'none'}"><span class="dnum">${d}<small>（${dow}）</small></span>${list.map(s => `<button type="button" class="slot-time ${s.courseId ? 'joined' : ''}" data-pick-slot="${s.id}" title="${escape(s.format)}${s.courseId ? '・開催確定（相乗り）' : ''}">${time(s.start)}</button>`).join('')}</div>`;
  }
  box.innerHTML = `<div class="slot-cal-head"><button type="button" class="button small" id="slot-prev" ${idx === 0 ? 'disabled' : ''}>← 前の月</button><strong>${y}年${m+1}月</strong><button type="button" class="button small" id="slot-next" ${idx === months.length-1 ? 'disabled' : ''}>次の月 →</button></div><div class="slot-cal-grid">${cells}</div><p class="quiet">時間のボタンを押すと、予約に進みます。緑の枠は、すでに開催が決まっている相乗りの枠です。</p>` + askLink;
  $('#slot-prev').onclick = () => { calMonth = months[idx-1]; renderSlotCalendar(available, c); };
  $('#slot-next').onclick = () => { calMonth = months[idx+1]; renderSlotCalendar(available, c); };
  $('#ask-dates').onclick = () => showInquiry(c.id);
  box.querySelectorAll('[data-pick-slot]').forEach(b => b.onclick = () => chooseSlot(slots.find(s => s.id === b.dataset.pickSlot)));
}
function confirmRequest(slot) {
  selectedSlot = slot;
  const c = currentCourse, end = new Date(Date.parse(slot.start) + c.minutes*60000), label = `${date(slot.start)} ${time(slot.start)}–${time(end)}`;
  open(`<button class="back-button" id="back-course" type="button">← 日時を選び直す</button><h2 id="dialog-title">仮予約を送る</h2><dl class="payment-summary"><dt>講座</dt><dd>${escape(c.title)}</dd><dt>日時</dt><dd>${label}<br>日本時間 / ${c.minutes}分</dd><dt>開催形式</dt><dd>${escape(slot.format)}<br>${escape(slot.location)}${slot.instructor ? '<br>講師：' + escape(slot.instructor) : ''}</dd><dt>受講料</dt><dd><strong>${yen(c.price)}</strong><br>お支払いは、日程確定のご連絡メールでご案内します。</dd></dl><ol class="request-steps"><li>いま：お名前とメールアドレスで仮予約（料金はかかりません）</li><li>講師が日程を確認し、お支払いのご案内をメールでお送りします</li><li>メールのリンクからカードでお支払い → 予約確定・参加URLをお送りします</li></ol><p class="quiet">仮予約の時点では枠は確保されません。お支払いの期限はご案内メールに記載します。</p><form id="request-form"><input type="hidden" name="_subject" value="【仮予約】${escape(c.title)} ${escape(label)}"><input type="hidden" name="course" value="${escape(c.title)}"><input type="hidden" name="datetime" value="${escape(label)}（日本時間）"><input type="hidden" name="instructor" value="${escape(slot.instructor || '')}"><input type="hidden" name="slot_id" value="${escape(slot.id)}"><input type="hidden" name="price" value="${c.price}"><input class="hp-field" type="text" name="_gotcha" tabindex="-1" autocomplete="off"><label class="form-field">お名前<input name="name" autocomplete="name" maxlength="80" required></label><label class="form-field">メールアドレス<input name="email" type="email" autocomplete="email" maxlength="254" required></label><details><summary>キャンセル条件</summary><p class="policy-copy">${escape(cancelText())}</p></details><details><summary>個人情報の取り扱い</summary><p class="policy-copy">${escape(privacyText())}</p></details><label class="check-field"><input type="checkbox" name="accepted" required><span>日時・受講料・キャンセル条件・個人情報の取り扱いを確認しました。</span></label><div id="form-error" role="alert"></div><button class="button dark wide" type="submit">この内容で仮予約する（無料） ↗</button></form>`);
  $('#back-course').onclick = () => showCourse(c.id);
  $('#request-form').onsubmit = async event => {
    event.preventDefault();
    const form = event.currentTarget, button = form.querySelector('[type=submit]');
    button.disabled = true; $('#form-error').textContent = '';
    try {
      const r = await fetch(FORM_URL, {method:'POST', headers:{Accept:'application/json'}, body:new FormData(form)});
      if (!r.ok) throw new Error('send failed');
      body.innerHTML = `<h2 id="dialog-title">仮予約を受け付けました。</h2><p>${label}（日本時間）<br>${escape(c.title)}</p><p>日程を確認して、お支払いのご案内をメールでお送りします（目安：1日以内）。ご案内が届くまで、お支払いは不要です。</p><p class="quiet">メールが届かない場合は、迷惑メールフォルダをご確認いただくか、LINEでご連絡ください。</p><a class="button line-button wide" href="${LINE_URL}" target="_blank" rel="noopener">LINEでも連絡する ↗</a>`;
    } catch (e) {
      button.disabled = false; $('#form-error').innerHTML = errorMessage('送信できませんでした。時間をおくか、LINEからお問い合わせください。');
    }
  };
}
function confirmBooking(slot) {
  selectedSlot=slot;
  const c=currentCourse;
  open(`<button class="back-button" id="back-course">← 講座・日時に戻る</button><h2 id="dialog-title">お申し込み内容の確認</h2><dl class="payment-summary"><dt>講座</dt><dd>${escape(c.title)}</dd><dt>日時</dt><dd>${date(slot.start)} ${time(slot.start)}–${time(new Date(Date.parse(slot.start)+c.minutes*60000))}<br>日本時間 / ${c.minutes}分</dd><dt>開催形式</dt><dd>${escape(slot.format)}<br>${escape(slot.location)}${slot.instructor?'<br>講師：'+escape(slot.instructor):''}</dd><dt>参加人数</dt><dd>1名${c.kind==='private'?'（1対1の個別相談）':''}</dd><dt>お支払い総額</dt><dd><strong>${yen(c.price)}</strong><br>今回1回分。自動更新はありません。</dd></dl><p>${c.kind==='private'?'個別相談には、ほかの方が相乗りすることはありません。':slot.courseId?'開催確定済みの講座へのお申し込みです。':'最初の方の決済が完了すると開催が確定します。以降、同じ講座にほかの方も参加できます。'}</p><form id="booking-form"><label class="form-field">お名前<input name="name" autocomplete="name" maxlength="80" required ${data.mode==='demo'?'placeholder="体験用のお名前"':''}></label><label class="form-field">メールアドレス<input name="email" type="email" autocomplete="email" maxlength="254" required ${data.mode==='demo'?'placeholder="sample@example.com"':''}></label><details><summary>キャンセル条件</summary><p class="policy-copy">${escape(cancelText())}</p></details><details><summary>個人情報の取り扱い</summary><p class="policy-copy">${escape(privacyText())}</p></details><label class="check-field"><input type="checkbox" name="accepted" required><span>日時・料金・キャンセル条件・個人情報の取り扱いを確認しました。</span></label><div id="form-error" role="alert"></div><button class="button dark wide" type="submit">${data.mode==='demo'?'体験用のお申し込みへ':yen(c.price)+'をStripeで支払う'} ↗</button><p class="quiet">${data.mode==='demo'?'体験版です。実際の請求やメール送信はありません。':'カード情報はStripeの決済画面で入力します。お支払い成功後に予約が確定します。'}</p></form>`);
  $('#back-course').onclick=()=>showCourse(c.id);
  $('#booking-form').onsubmit=async event=>{
    event.preventDefault();const form=event.currentTarget,button=form.querySelector('[type=submit]');button.disabled=true;
    $('#form-error').innerHTML='';
    try{
      const response=await api('/api/checkout',{slotId:slot.id,courseId:c.id,name:form.elements.name.value,email:form.elements.email.value,accepted:form.elements.accepted.checked});
      // 課金の成功は、このボタンやURLでは決めず、サーバーのStripe照合結果で表示。
      location.assign(response.url);
    }catch(e){
      $('#form-error').innerHTML=errorMessage(e)+(e.bookingId?`<a class="inline-link" href="/?booking=${encodeURIComponent(e.bookingId)}">この予約の状況を確認する</a>`:'');
      if(!e.bookingId)button.disabled=false;
      void refreshSlots();
    }
  };
}
async function showBooking(bookingId, first=true) {
  if(first)open('<h2 id="dialog-title">お申し込みを確認しています…</h2>');
  try{
    const booking=await api('/api/bookings/'+encodeURIComponent(bookingId));
    const paid=booking.status==='paid',expired=booking.status==='expired';
    open(`<h2 id="dialog-title">${paid?'お申し込みが確定しました。':expired?'お支払い期限が終了しました。':'お支払い手続き中です。'}</h2>${data.mode==='demo'?'<div class="booking-status">体験用の予約です。実際の開催・請求・メール送信はありません。</div>':''}<p><strong>${escape(booking.course.title)}</strong><br>${date(booking.slot.start)} ${time(booking.slot.start)} / ${booking.course.minutes}分 / ${escape(booking.slot.format)}<br>${yen(booking.amount)} / 1名</p>${paid?`<p>${booking.course.kind==='private'?'1対1で、今の状況を一緒に整理しましょう。':'一緒に学べることを楽しみにしています。'}</p><div class="outcome"><strong>当日の準備</strong><br>${escape(booking.course.preparation)}</div>${booking.meetingUrl?`<a class="button dark" href="${escape(booking.meetingUrl)}" target="_blank" rel="noopener">当日の参加URLを開く ↗</a>`:`<p>${escape(booking.slot.location)}</p>`}<p class="quiet">${booking.confirmationEmail==='sent'?'確認メールを送信しました。':booking.confirmationEmail==='pending'?'参加案内メールを送信準備中です。この画面でも参加情報を確認できます。':''}</p>`:expired?'<p>日時を選び直してお申し込みください。</p><button class="button" id="choose-again">講座を選び直す</button>':`${booking.checking?errorMessage('決済状態を確認中です。再度のお申し込みはせず、この画面でお待ちください。'):''}<p>この画面を開いただけでは、予約は確定しません。決済完了を確認してから開催・参加が確定します。</p><div class="dialog-actions">${data.mode==='demo'?'<button class="button dark" id="demo-pay">支払い成功を体験する（請求なし）</button>':booking.checkoutUrl?`<a class="button dark" href="${escape(booking.checkoutUrl)}">Stripeのお支払いに戻る ↗</a>`:''}<button class="button" id="refresh-booking">状態を再確認</button></div><p class="quiet">手続きを中断すると、Stripeで期限切れを確認した後に枠が解放されます。</p>`}<p class="booking-id">予約番号：${escape(booking.id)}<br>このページのURLは予約確認用です。ほかの方に共有しないでください。</p>${data.supportEmail?`<p class="quiet">お問い合わせ：${escape(data.supportEmail)}</p>`:''}`);
    $('#demo-pay')?.addEventListener('click',async()=>{const b=$('#demo-pay');b.disabled=true;try{await api('/api/demo/pay',{bookingId});await refreshSlots();await showBooking(bookingId,false);}catch(e){b.insertAdjacentHTML('afterend',errorMessage(e));b.disabled=false;}});
    $('#refresh-booking')?.addEventListener('click',()=>showBooking(bookingId,false));
    $('#choose-again')?.addEventListener('click',()=>{history.replaceState({},'', '/');dialog.close();$('#courses').scrollIntoView();});
    clearTimeout(bookingTimer);
    if(!paid&&!expired&&data.mode!=='demo')bookingTimer=setTimeout(()=>{if(dialog.open)void showBooking(bookingId,false);},7000);
  }catch(e){open('<h2 id="dialog-title">予約を確認できませんでした</h2>'+errorMessage(e));}
}
$('#consult-button').onclick=()=>showCourse('consultation');
$('#privacy-button').onclick=()=>open(`<h2 id="dialog-title">個人情報の取り扱い</h2><p class="policy-copy">${escape(privacyText())}</p>`);
$('#legal-button').onclick=()=>{
  const live=BOOKING_MODE==='live', disclose=escape(LEGAL.disclose), row=(t,d)=>`<dt>${t}</dt><dd>${d}</dd>`;
  open(`<h2 id="dialog-title">特定商取引法に基づく表記</h2><dl class="payment-summary legal-list">${[
    row('販売事業者',escape(data.seller.name||LEGAL.seller)),
    row('運営責任者',escape(LEGAL.manager)),
    row('所在地',data.seller.address?escape(data.seller.address):disclose),
    row('電話番号',data.seller.phone?escape(data.seller.phone):disclose),
    row('メールアドレス',escape(data.supportEmail||LEGAL.email)),
    row('サービス名','Udatsu（ウダツ）公開講座・個別相談'),
    row('販売価格','公開講座・個別相談：各60分 4,400円 / 1名<br>表示価格がお支払い総額です。'),
    row('商品代金以外の必要料金','オンライン受講時のインターネット通信費、対面開催時の会場までの交通費は、受講者のご負担です。'),
    row('お支払い方法','クレジットカード決済（Stripe）'),
    row('お支払い時期',live?'お申し込み時にお支払いいただきます。':'仮予約のあと、日程確定のご案内メールに記載する決済ページからお支払いいただきます。お支払い期限はご案内メールに記載します（開催日の前日まで）。'),
    row('申し込みの有効期限',live?'決済ページの有効期限内にお支払いが完了しない場合、お申し込みは取り消されます。':'お支払い期限までにお支払いが確認できない場合、仮予約は取り消されます。'),
    row('サービスの提供時期','お申し込み時に選んだ日時に実施します。参加URLなどの参加方法は、お支払いの確認後にメールでお送りします。'),
    row('キャンセル・返金','<span class="policy-copy">'+escape(cancelText())+'</span><br>講座は役務の提供のため、受講後の返品・返金はお受けしていません。'),
    row('動作環境','オンライン開催はGoogle Meetなどのビデオ通話を使います。パソコンまたはスマートフォン、安定したインターネット接続、マイク（できればカメラも）をご用意ください。')
  ].join('')}</dl>`);
};
async function init(){
  try{
    data=await api('/api/catalog');
    if(data.mode!=='live'){$('#preview').hidden=false;$('#preview').textContent=data.mode==='preview'?'公開準備中｜講座の内容をご覧いただけます。予約受付は準備が整い次第、開始します。':data.mode==='demo'?'体験版｜日程はサンプルです。実際の予約・請求は発生しません。':'Stripeテスト環境｜実際の開催・請求はありません。テスト用の情報でお試しください。';}
    $('#cancel-policy').textContent=cancelText();
    renderCourses();await refreshSlots();
    const bookingId=new URLSearchParams(location.search).get('booking');
    if(bookingId)await showBooking(bookingId);
  }catch(e){$('#course-grid').innerHTML='';$('#load-error').textContent=e.message;$('#load-error').hidden=false;}
}
void init();
