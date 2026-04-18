<?php
require_once __DIR__ . '/../core/bootstrap.php';
if (empty($uid)) { header('Location: ../index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>思考DNA診断 | Udatsu Voyager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { background: var(--bg-dark); min-height: 100vh; }
    .quiz-container { max-width: 680px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

    .page-header { margin-bottom: 2.5rem; }
    .page-header h1 { font-size: 1.8rem; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.8rem; }
    .page-header h1 i { color: var(--primary-neon); }
    .subtitle { color: var(--text-muted); font-size: 0.92rem; }

    /* Progress */
    .progress-bar-wrap { background: rgba(255,255,255,0.08); border-radius: 99px; height: 4px; margin-bottom: 2rem; overflow: hidden; }
    .progress-bar-fill { height: 100%; background: var(--primary-neon); border-radius: 99px; transition: width 0.5s cubic-bezier(0.4,0,0.2,1); box-shadow: 0 0 8px var(--primary-neon); }
    .progress-label { display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.5rem; }

    /* Question card */
    .q-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 2rem; margin-bottom: 1.5rem; animation: fadeUp 0.25s ease; }
    @keyframes fadeUp { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }
    .q-scenario { color: var(--text-muted); font-size: 0.88rem; margin-bottom: 1rem; line-height: 1.6; padding: 0.75rem 1rem; background: rgba(255,255,255,0.025); border-radius: 8px; border-left: 2px solid rgba(0,255,204,0.3); }
    .q-prompt { color: var(--text-white); font-size: 1.05rem; font-weight: 600; margin-bottom: 1.4rem; line-height: 1.6; }

    .choice-btn { display: flex; align-items: flex-start; gap: 1rem; width: 100%; text-align: left;
                  background: rgba(255,255,255,0.04); border: 1.5px solid rgba(255,255,255,0.1);
                  border-radius: 12px; padding: 1rem 1.2rem; margin-bottom: 0.75rem; cursor: pointer;
                  color: var(--text-white); font-size: 0.92rem; line-height: 1.5; transition: all 0.2s; }
    .choice-btn:hover { border-color: rgba(0,255,204,0.4); background: rgba(0,255,204,0.04); }
    .choice-btn.selected { border-color: var(--primary-neon); background: rgba(0,255,204,0.07); box-shadow: 0 0 16px rgba(0,255,204,0.08); }
    .choice-label { min-width: 1.5rem; height: 1.5rem; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.25);
                    display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem;
                    flex-shrink: 0; color: var(--text-muted); transition: all 0.2s; }
    .choice-btn.selected .choice-label { background: var(--primary-neon); color: #000; border-color: var(--primary-neon); box-shadow: 0 0 10px rgba(0,255,204,0.5); }

    .nav-btn { display: flex; justify-content: flex-end; margin-top: 1rem; }
    .btn-next { background: var(--primary-neon); color: #000; font-weight: 700; padding: 0.75rem 2rem; border-radius: 10px; border: none; cursor: pointer; font-size: 0.95rem; opacity: 0.3; pointer-events: none; transition: all 0.2s; }
    .btn-next.active { opacity: 1; pointer-events: auto; }
    .btn-next.active:hover { box-shadow: 0 4px 20px rgba(0,255,204,0.4); transform: translateY(-1px); }

    /* States */
    .state-center { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 40vh; text-align: center; gap: 1.2rem; }
    .state-icon { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; }
    .state-icon.loading { background: rgba(0,255,204,0.1); color: var(--primary-neon); animation: pulse 2s ease-in-out infinite; }
    .state-icon.analyzing { background: rgba(168,85,247,0.1); color: #a855f7; animation: pulse 1.5s ease-in-out infinite; }
    .state-icon.done { background: rgba(0,255,204,0.1); color: var(--primary-neon); }
    @keyframes pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(0,255,204,0.2); } 50% { box-shadow: 0 0 0 12px rgba(0,255,204,0); } }
    .state-title { color: var(--text-white); font-size: 1.1rem; font-weight: 600; margin: 0; }
    .state-sub { color: var(--text-muted); font-size: 0.88rem; margin: 0; }

    /* Profile result */
    .profile-md { text-align: left; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 1.5rem 1.8rem; line-height: 1.8; margin-top: 1.5rem; }
    .profile-md h2 { color: var(--primary-neon); font-size: 1rem; margin-top: 1.5rem; margin-bottom: 0.5rem; letter-spacing: 0.05em; }
    .profile-md h3 { color: var(--text-white); font-size: 0.95rem; margin-top: 1rem; margin-bottom: 0.3rem; }
    .profile-md p, .profile-md li { color: var(--text-muted); font-size: 0.9rem; }

    .action-row { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 2rem; }

    footer { text-align: center; padding: 2rem; }
    footer a { color: var(--text-muted); text-decoration: none; font-size: 0.88rem; }
  </style>
</head>
<body>
<div class="quiz-container">

  <div class="page-header">
    <h1><i class="fas fa-dna"></i> 思考DNA診断</h1>
    <p class="subtitle">シナリオへの回答を重ねるたびに、あなたの思考傾向が精緻化されていきます</p>
  </div>

  <!-- Loading Questions -->
  <div id="loadingState" class="state-center">
    <div class="state-icon loading"><i class="fas fa-brain"></i></div>
    <p class="state-title">質問を生成しています</p>
    <p class="state-sub">あなたのVJデータをもとにパーソナライズされた質問を作成中...</p>
  </div>

  <!-- Quiz -->
  <div id="quizState" style="display:none;">
    <div class="progress-label">
      <span id="progressText" style="color:var(--primary-neon); font-weight:600;"></span>
      <span>10問</span>
    </div>
    <div class="progress-bar-wrap"><div class="progress-bar-fill" id="progressBar" style="width:0%;"></div></div>

    <div id="questionCard" class="q-card">
      <div class="q-scenario" id="qScenario"></div>
      <div class="q-prompt" id="qPrompt"></div>
      <button class="choice-btn" id="choiceA" onclick="selectChoice('A')">
        <span class="choice-label">A</span>
        <span id="choiceAText"></span>
      </button>
      <button class="choice-btn" id="choiceB" onclick="selectChoice('B')">
        <span class="choice-label">B</span>
        <span id="choiceBText"></span>
      </button>
    </div>

    <div class="nav-btn">
      <button class="btn-next" id="nextBtn" onclick="nextQuestion()">
        <i class="fas fa-arrow-right"></i> 次へ
      </button>
    </div>
  </div>

  <!-- Analyzing -->
  <div id="analyzingState" class="state-center" style="display:none;">
    <div class="state-icon analyzing"><i class="fas fa-atom"></i></div>
    <p class="state-title">プロファイルを更新しています</p>
    <p class="state-sub">これまでの全回答データと統合して分析中です</p>
  </div>

  <!-- Result -->
  <div id="resultState" style="display:none;">
    <div class="state-center" style="min-height: auto; margin-bottom: 1rem;">
      <div class="state-icon done"><i class="fas fa-check"></i></div>
      <p class="state-title">思考プロファイルが更新されました</p>
      <p class="state-sub">回答データが蓄積され、分析がより精緻になりました</p>
    </div>

    <div id="profileResult" class="profile-md"></div>

    <div class="action-row">
      <button onclick="startNew()" class="btn btn-secondary">
        <i class="fas fa-redo"></i> もう10問やる
      </button>
      <a href="dashboard.php" class="btn btn-primary">
        <i class="fas fa-home"></i> ダッシュボードへ
      </a>
    </div>
  </div>

  <!-- Error -->
  <div id="errorState" class="state-center" style="display:none;">
    <div class="state-icon" style="background:rgba(255,68,68,0.1); color:#ff4444;"><i class="fas fa-exclamation-triangle"></i></div>
    <p class="state-title" id="errorTitle">エラーが発生しました</p>
    <p class="state-sub" id="errorMsg"></p>
    <div class="action-row">
      <button onclick="startNew()" class="btn btn-secondary"><i class="fas fa-redo"></i> 再試行</button>
      <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-home"></i> ダッシュボードへ</a>
    </div>
  </div>

</div>
<footer><a href="dashboard.php"><i class="fas fa-chevron-left"></i> ダッシュボードに戻る</a></footer>

<script>
let questions = [];
let currentIndex = 0;
let answers = [];
let selectedChoice = null;

async function loadQuestions() {
  showState('loadingState');
  try {
    const res = await fetch('generate_quiz.php');
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch(e) { throw new Error('JSONパースエラー: ' + text.substring(0, 200)); }

    if (data.questions && data.questions.length > 0) {
      questions = data.questions;
      currentIndex = 0;
      answers = [];
      selectedChoice = null;
      showState('quizState');
      showQuestion(0);
    } else {
      throw new Error('質問データが空です');
    }
  } catch(e) {
    showError('質問の生成に失敗しました', e.message);
  }
}

function showState(id) {
  ['loadingState','quizState','analyzingState','resultState','errorState']
    .forEach(s => document.getElementById(s).style.display = (s === id) ? (s === 'quizState' ? 'block' : 'flex') : 'none');
}

function showError(title, msg) {
  document.getElementById('errorTitle').textContent = title;
  document.getElementById('errorMsg').textContent = msg || '';
  showState('errorState');
}

function showQuestion(idx) {
  const q = questions[idx];
  currentIndex = idx;
  selectedChoice = answers[idx] ?? null;

  document.getElementById('qScenario').textContent = q.scenario;
  document.getElementById('qPrompt').textContent = q.question;
  document.getElementById('choiceAText').textContent = q.a;
  document.getElementById('choiceBText').textContent = q.b;

  document.getElementById('choiceA').classList.toggle('selected', selectedChoice === 'A');
  document.getElementById('choiceB').classList.toggle('selected', selectedChoice === 'B');

  const pct = Math.round(((idx + 1) / questions.length) * 100);
  document.getElementById('progressBar').style.width = pct + '%';
  document.getElementById('progressText').textContent = (idx + 1) + ' / ' + questions.length;

  const nextBtn = document.getElementById('nextBtn');
  nextBtn.innerHTML = idx === questions.length - 1
    ? '<i class="fas fa-check"></i> 結果を見る'
    : '<i class="fas fa-arrow-right"></i> 次へ';
  nextBtn.classList.toggle('active', !!selectedChoice);
}

function selectChoice(choice) {
  selectedChoice = choice;
  answers[currentIndex] = choice;
  document.getElementById('choiceA').classList.toggle('selected', choice === 'A');
  document.getElementById('choiceB').classList.toggle('selected', choice === 'B');
  document.getElementById('nextBtn').classList.add('active');
}

async function nextQuestion() {
  if (!selectedChoice) return;
  if (currentIndex < questions.length - 1) {
    showQuestion(currentIndex + 1);
  } else {
    await submitAnswers();
  }
}

async function submitAnswers() {
  showState('analyzingState');

  const payload = questions.map((q, i) => ({
    question:     q.question,
    scenario:     q.scenario,
    a:            q.a,
    b:            q.b,
    chosen:       answers[i],
    chosen_text:  answers[i] === 'A' ? q.a : q.b
  }));

  try {
    const res = await fetch('submit_quiz.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session: payload })
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch(e) { throw new Error('レスポンスエラー: ' + text.substring(0, 200)); }

    showState('resultState');
    if (data.profile_md) {
      document.getElementById('profileResult').innerHTML = markdownToHtml(data.profile_md);
    }
  } catch(e) {
    showError('分析中にエラーが発生しました', e.message);
  }
}

function startNew() {
  loadQuestions();
}

function markdownToHtml(md) {
  return md
    .replace(/^## (.+)$/gm, '<h2>$1</h2>')
    .replace(/^### (.+)$/gm, '<h3>$1</h3>')
    .replace(/\*\*(.+?)\*\*/g, '<strong style="color:var(--text-white)">$1</strong>')
    .replace(/^- (.+)$/gm, '<li>$1</li>')
    .replace(/(<li>[^]*?<\/li>)/g, '<ul>$1</ul>')
    .replace(/\n{2,}/g, '</p><p>') 
    .replace(/^([^<\n].+)$/gm, (m) => m.startsWith('<') ? m : '<p>' + m + '</p>');
}

loadQuestions();
</script>
</body>
</html>
