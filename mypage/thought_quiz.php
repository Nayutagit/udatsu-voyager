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
    h1 { font-size: 1.8rem; margin-bottom: 0.3rem; }
    .subtitle { color: var(--text-muted); margin-bottom: 2.5rem; font-size: 0.95rem; }

    /* Progress */
    .progress-bar-wrap { background: rgba(255,255,255,0.08); border-radius: 99px; height: 6px; margin-bottom: 2rem; overflow: hidden; }
    .progress-bar-fill { height: 100%; background: var(--primary-neon); border-radius: 99px; transition: width 0.4s ease; }
    .progress-label { text-align: right; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.4rem; }

    /* Question card */
    .q-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 2rem; margin-bottom: 1.5rem; animation: fadeUp 0.3s ease; }
    @keyframes fadeUp { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform: translateY(0); } }
    .q-scenario { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.5; padding: 0.8rem 1rem; background: rgba(255,255,255,0.03); border-radius: 8px; border-left: 3px solid rgba(255,255,255,0.1); }
    .q-prompt { color: var(--text-white); font-size: 1.1rem; font-weight: 600; margin-bottom: 1.5rem; line-height: 1.5; }
    .choice-btn { display: flex; align-items: flex-start; gap: 1rem; width: 100%; text-align: left;
                  background: rgba(255,255,255,0.05); border: 1.5px solid rgba(255,255,255,0.12);
                  border-radius: 12px; padding: 1rem 1.2rem; margin-bottom: 0.8rem; cursor: pointer;
                  color: var(--text-white); font-size: 0.95rem; line-height: 1.5; transition: all 0.2s; }
    .choice-btn:hover { border-color: var(--primary-neon); background: rgba(0,255,204,0.05); }
    .choice-btn.selected { border-color: var(--primary-neon); background: rgba(0,255,204,0.1); }
    .choice-label { min-width: 1.6rem; height: 1.6rem; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.3);
                    display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0; }
    .choice-btn.selected .choice-label { background: var(--primary-neon); color: #000; border-color: var(--primary-neon); }

    .nav-btn { display: flex; justify-content: flex-end; margin-top: 1rem; }
    .btn-next { background: var(--primary-neon); color: #000; font-weight: 700; padding: 0.8rem 2rem; border-radius: 10px; border: none; cursor: pointer; font-size: 1rem; opacity: 0.4; transition: opacity 0.2s; }
    .btn-next.active { opacity: 1; }
    .btn-next.active:hover { transform: translateY(-1px); }

    /* Loading / Result */
    .loading-state, .result-state { text-align: center; padding: 4rem 1rem; }
    .spinner { font-size: 2.5rem; animation: spin 1s linear infinite; margin-bottom: 1rem; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .profile-md { text-align: left; background: rgba(255,255,255,0.04); border-radius: 12px; padding: 1.5rem 2rem; line-height: 1.8; }
    .profile-md h2 { color: var(--primary-neon); margin-top: 1.5rem; }
    .profile-md h3 { color: var(--text-white); margin-top: 1.2rem; }
    .profile-md p { color: var(--text-muted); }
    .profile-md ul li { color: var(--text-muted); }
    footer { text-align: center; padding: 2rem; }
    footer a { color: var(--text-muted); text-decoration: none; font-size: 0.9rem; }
  </style>
</head>
<body>
<div class="quiz-container">

  <h1>🧬 思考DNA診断</h1>
  <p class="subtitle">シナリオへの回答を重ねるたびに、あなたの思考の傾向が精緻化されていきます。</p>

  <!-- Loading Questions -->
  <div id="loadingState" class="loading-state">
    <div class="spinner">🧠</div>
    <p style="color:var(--text-muted);">あなたのVJデータを元に質問を生成中...</p>
  </div>

  <!-- Quiz -->
  <div id="quizState" style="display:none;">
    <div class="progress-label"><span id="progressText">1 / 10</span></div>
    <div class="progress-bar-wrap"><div class="progress-bar-fill" id="progressBar" style="width:10%;"></div></div>

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
      <button class="btn-next" id="nextBtn" onclick="nextQuestion()">次の質問 →</button>
    </div>
  </div>

  <!-- Analyzing -->
  <div id="analyzingState" class="loading-state" style="display:none;">
    <div class="spinner">⚡</div>
    <p style="color:var(--text-muted); margin-bottom:0.5rem;">思考プロファイルを更新しています...</p>
    <p style="font-size:0.85rem; color:var(--text-muted); opacity:0.7;">これまでの全データと統合して分析中です</p>
  </div>

  <!-- Result -->
  <div id="resultState" class="result-state" style="display:none;">
    <div style="font-size:3rem; margin-bottom:1rem;">✨</div>
    <h2 style="margin-bottom:0.5rem;">思考プロファイルが更新されました</h2>
    <p style="color:var(--text-muted); margin-bottom:2rem;">今回のセッションの分析結果がThought Coreに反映されています。</p>
    <div id="profileResult" class="profile-md"></div>
    <div style="margin-top:2rem; display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
      <button onclick="startNew()" class="btn btn-secondary"><i class="fas fa-redo"></i> もう10問やる</button>
      <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-home"></i> ダッシュボードへ</a>
    </div>
  </div>

</div>
<footer><a href="dashboard.php">← ダッシュボードに戻る</a></footer>

<script>
let questions = [];
let currentIndex = 0;
let answers = [];
let selectedChoice = null;

async function loadQuestions() {
  try {
    const res = await fetch('generate_quiz.php');
    const data = await res.json();
    if (data.questions && data.questions.length > 0) {
      questions = data.questions;
      document.getElementById('loadingState').style.display = 'none';
      document.getElementById('quizState').style.display = 'block';
      showQuestion(0);
    } else {
      document.getElementById('loadingState').innerHTML = '<p style="color:var(--text-muted);">⚠️ 質問の生成に失敗しました。VJデータを追加してから再試行してください。</p>';
    }
  } catch(e) {
    document.getElementById('loadingState').innerHTML = '<p style="color:var(--text-muted);">⚠️ 通信エラーが発生しました。</p>';
  }
}

function showQuestion(idx) {
  const q = questions[idx];
  currentIndex = idx;
  selectedChoice = answers[idx] ?? null;

  document.getElementById('qScenario').textContent = q.scenario;
  document.getElementById('qPrompt').textContent = q.question;
  document.getElementById('choiceAText').textContent = q.a;
  document.getElementById('choiceBText').textContent = q.b;

  // Reset UI
  document.getElementById('choiceA').classList.remove('selected');
  document.getElementById('choiceB').classList.remove('selected');
  if (selectedChoice === 'A') document.getElementById('choiceA').classList.add('selected');
  if (selectedChoice === 'B') document.getElementById('choiceB').classList.add('selected');

  const pct = Math.round(((idx + 1) / questions.length) * 100);
  document.getElementById('progressBar').style.width = pct + '%';
  document.getElementById('progressText').textContent = (idx + 1) + ' / ' + questions.length;

  const nextBtn = document.getElementById('nextBtn');
  nextBtn.textContent = (idx === questions.length - 1) ? '結果を見る ✨' : '次の質問 →';
  nextBtn.className = 'btn-next' + (selectedChoice ? ' active' : '');
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
  document.getElementById('quizState').style.display = 'none';
  document.getElementById('analyzingState').style.display = 'block';

  const payload = questions.map((q, i) => ({
    question: q.question,
    scenario: q.scenario,
    a: q.a,
    b: q.b,
    chosen: answers[i],
    chosen_text: answers[i] === 'A' ? q.a : q.b
  }));

  try {
    const res = await fetch('submit_quiz.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session: payload })
    });
    const data = await res.json();

    document.getElementById('analyzingState').style.display = 'none';
    document.getElementById('resultState').style.display = 'block';

    if (data.profile_md) {
      document.getElementById('profileResult').innerHTML = markdownToHtml(data.profile_md);
    }
  } catch(e) {
    document.getElementById('analyzingState').innerHTML = '<p style="color:var(--text-muted);">⚠️ 分析中にエラーが発生しました。</p><a href="dashboard.php" class="btn btn-primary" style="margin-top:1rem;">ダッシュボードへ</a>';
  }
}

function startNew() {
  answers = [];
  currentIndex = 0;
  selectedChoice = null;
  document.getElementById('resultState').style.display = 'none';
  document.getElementById('loadingState').style.display = 'block';
  loadQuestions();
}

function markdownToHtml(md) {
  return md
    .replace(/^## (.+)$/gm, '<h2>$1</h2>')
    .replace(/^### (.+)$/gm, '<h3>$1</h3>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/^- (.+)$/gm, '<li>$1</li>')
    .replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>')
    .replace(/\n\n/g, '<br><br>');
}

loadQuestions();
</script>
</body>
</html>
