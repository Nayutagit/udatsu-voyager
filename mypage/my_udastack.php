<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (empty($uid) || empty($userName)) {
  header('Location: index.php');
  exit();
}

$userDir   = __DIR__ . '/../users/';
$postsFile = $userDir . $uid . '_posts.json';
$posts     = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];

$stackedPosts = array_values(array_reverse(array_filter($posts, fn($p) => $p['status'] === 'My Udastack追加済')));

$allSummaries = [];
$allKeywords = [];
foreach ($stackedPosts as $p) {
  if (!empty($p['summary'])) {
    $allSummaries[] = $p['summary'];
  }
  if (!empty($p['keywords']) && is_array($p['keywords'])) {
    foreach ($p['keywords'] as $kw) {
      $allKeywords[$kw] = true;
    }
  }
}
$uniqueKeywords = array_keys($allKeywords);
$summaryText = count($allSummaries) > 0 ? implode(" / ", array_slice($allSummaries, 0, 3)) : '（要約がまだありません）';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>My Udastack</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    /* Page specific styles */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem;
      position: relative;
    }
    h1 {
      font-size: clamp(2.2rem, 5vw, 3.5rem);
      font-weight: 700;
      margin-bottom: 3rem;
      text-align: center;
      color: var(--text-white);
      animation: slideDown 1s ease;
    }
    @keyframes slideDown {
      from { transform: translateY(-30px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    .summary-box {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      padding: 2.5rem;
      border-radius: 4px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
      margin-bottom: 4rem;
      position: relative;
      transition: all 0.4s ease;
      animation: fadeInUp 1s ease 0.3s both;
    }
    @keyframes fadeInUp {
      from { transform: translateY(40px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    .summary-box:hover {
      transform: translateY(-5px);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
    }
    .summary-box h2 {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 2rem;
      display: inline-block;
      margin-right: 1.5rem;
      color: var(--text-white);
    }
    .edit-button, .ai-button, .remove-button, .unstack-button {
      background: var(--primary-neon);
      color: #000;
      border: none;
      padding: 0.7rem 1.5rem;
      border-radius: 50px;
      cursor: pointer;
      font-weight: 700;
      font-size: 0.9rem;
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.4);
      transition: all 0.3s ease;
      margin-right: 1rem;
      margin-bottom: 1rem;
    }
    .edit-button:hover, .ai-button:hover {
      transform: translateY(-2px) scale(1.05);
      box-shadow: 0 0 25px rgba(252, 200, 0, 0.6);
    }
    .remove-button, .unstack-button {
      background: #ff5252;
      color: white;
      box-shadow: 0 0 15px rgba(255, 82, 82, 0.4);
    }
    .remove-button:hover, .unstack-button:hover {
      background: #ff1744;
      box-shadow: 0 0 25px rgba(255, 82, 82, 0.6);
      transform: translateY(-2px) scale(1.05);
    }
    .field-label {
      font-weight: 700;
      margin-top: 2rem;
      margin-bottom: 1rem;
      color: var(--primary-neon);
      font-size: 1.1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    #summaryText {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(10px);
      padding: 1.5rem;
      border-radius: 4px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      margin-bottom: 2rem;
      line-height: 1.7;
      font-size: 1.05rem;
      color: var(--text-white);
      transition: all 0.3s ease;
    }
    #summaryText[contenteditable="true"] {
      background: rgba(255, 255, 255, 0.1);
      border-color: var(--primary-neon);
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.3);
      outline: none;
    }
    textarea {
      font-family: inherit;
      width: 100%;
      padding: 1.2rem;
      border-radius: 4px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(10px);
      color: var(--text-white);
      font-size: 1rem;
      line-height: 1.6;
      resize: vertical;
      transition: all 0.3s ease;
      margin-bottom: 1.5rem;
    }
    textarea:focus {
      outline: none;
      border-color: var(--primary-neon);
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.3);
      background: rgba(255, 255, 255, 0.1);
    }
    textarea::placeholder {
      color: var(--text-muted);
    }
    .actions {
      display: flex;
      gap: 1.5rem;
      flex-wrap: wrap;
      margin-top: 2rem;
    }
    .actions a, .actions button {
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      color: var(--text-white);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 50px;
      padding: 0.8rem 1.5rem;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    .actions a:hover, .actions button:hover {
      color: #000;
      background: var(--primary-neon);
      border-color: var(--primary-neon);
      transform: translateY(-2px);
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.4);
    }
    .download-excel {
      margin: 4rem 0 3rem;
      text-align: center;
      font-weight: 700;
      font-size: 1.5rem;
      color: var(--text-white);
      animation: fadeInUp 1s ease 0.6s both;
    }
    .stack-item {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      padding: 2.5rem;
      border-radius: 4px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
      margin-bottom: 2.5rem;
      transition: all 0.4s ease;
      position: relative;
      overflow: hidden;
      animation: fadeInUp 1s ease both;
    }
    .stack-item:hover {
      transform: translateY(-5px);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
      border-color: var(--primary-neon);
    }
    .stack-item h3 {
      font-size: 1.4rem;
      font-weight: 700;
      margin-bottom: 2rem;
      color: var(--text-white);
      line-height: 1.5;
    }
    .stack-item .field-label {
      margin-top: 2rem;
      margin-bottom: 1rem;
    }
    .stack-item div:not(.field-label):not(.actions) {
      background: rgba(255, 255, 255, 0.05);
      padding: 1.2rem;
      border-radius: 4px;
      margin-bottom: 1.5rem;
      line-height: 1.7;
      color: var(--text-muted);
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    footer {
      text-align: center;
      margin-top: 6rem;
      margin-bottom: 3rem;
      animation: fadeInUp 1s ease 1.2s both;
    }
    footer a {
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      color: var(--text-white);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 50px;
      padding: 1rem 2.5rem;
      font-size: 1rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      display: inline-block;
    }
    footer a:hover {
      color: #000;
      background: var(--primary-neon);
      border-color: var(--primary-neon);
      transform: translateY(-3px);
      box-shadow: 0 0 20px rgba(252, 200, 0, 0.4);
    }
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(15px);
      justify-content: center;
      align-items: center;
      z-index: 1000;
      animation: modalFadeIn 0.4s ease;
    }
    @keyframes modalFadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    .modal-content {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      padding: 3.5rem;
      border-radius: 4px;
      text-align: center;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
      border: 2px solid var(--primary-neon);
      animation: modalSlideIn 0.5s ease;
      font-size: 1.2rem;
      color: var(--text-white);
    }
    @keyframes modalSlideIn {
      from { transform: scale(0.9) translateY(30px); opacity: 0; }
      to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .spinner {
      width: 50px;
      height: 50px;
      border: 4px solid rgba(255, 255, 255, 0.3);
      border-top: 4px solid var(--primary-neon);
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto 2rem;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }
      .summary-box {
        padding: 1.5rem;
      }
      .stack-item {
        padding: 1.5rem;
      }
      .actions {
        flex-direction: column;
        align-items: stretch;
      }
      .actions a, .actions button {
        text-align: center;
      }
      h1 {
        font-size: 2rem;
      }
      .modal-content {
        padding: 2rem;
        margin: 1rem;
      }
    }
    .fade-out {
      animation: fadeOut 0.5s ease-out forwards;
    }
    @keyframes fadeOut {
      from { opacity: 1; transform: translateY(0); }
      to { opacity: 0; transform: translateY(-20px); }
    }
  </style>
</head>
<body>

<div class="container">
  <h1>📦 My Udastack</h1>

  <div class="summary-box">
    <h2>🧭 思考資産まとめ</h2>
    <button class="edit-button" id="editBtn" onclick="toggleEdit()">📝 編集する</button>
    <button class="edit-button" id="saveEdit" style="display:none;" onclick="saveEditedSummary()">✅ 編集完了</button>

    <div class="field-label">🧠 全体の流れ:</div>
    <div id="summaryText" contenteditable="false"><?= htmlspecialchars($summaryText) ?></div>

    <div class="field-label">🗣 AIに直接指示して修正できます：</div>
    <textarea id="aiPrompt" placeholder="例：〇〇の話題は無し。このキーワードを入れて など自由に指示してください" rows="3"></textarea>
    <button class="ai-button" onclick="regenerateSummary()">🔁 AIに再出力を依頼</button>

    <div class="actions">
      <a href="download_summary.php">📄 テキストDL</a>
    </div>
  </div>

  <div class="download-excel">
    📚 積み上げた投稿一覧
  </div>

  <?php if (!empty($uniqueKeywords)): ?>
  <div style="margin-bottom: 2rem; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
    <?php foreach ($uniqueKeywords as $kw): ?>
      <span class="keyword-filter-tag" data-kw="<?= htmlspecialchars($kw) ?>" onclick="toggleFilter('<?= htmlspecialchars($kw) ?>')" style="
        background: rgba(255, 255, 255, 0.1); 
        border: 1px solid var(--accent-teal); 
        color: var(--text-white); 
        padding: 5px 15px; 
        border-radius: 20px; 
        cursor: pointer; 
        transition: all 0.2s;">
        #<?= htmlspecialchars($kw) ?>
      </span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php 
  // 元の投稿データからスタック済みのものを見つけるためのマッピング
  $originalIndexMap = [];
  foreach ($posts as $originalIndex => $post) {
    if ($post['status'] === 'My Udastack追加済') {
      $originalIndexMap[] = $originalIndex;
    }
  }
  ?>

  <?php foreach ($stackedPosts as $i => $post): ?>
  <div class="stack-item" id="stack-item-<?= $i ?>" data-keywords="<?= htmlspecialchars(json_encode($post['keywords'] ?? [])) ?>">
    <h3><?= htmlspecialchars($post['title'] ?? '（タイトル未設定）') ?></h3>
    <div class="field-label">🧠 整文:</div>
    <div><?= nl2br(htmlspecialchars($post['refined_text'] ?? '（なし）')) ?></div>
    <div class="field-label">🔑 この記事から抽出されたキーワード:</div>
    <div><?= htmlspecialchars(implode(', ', $post['keywords'] ?? [])) ?></div>
    <div class="field-label">📄 要約:</div>
    <div><?= nl2br(htmlspecialchars($post['summary'] ?? '（なし）')) ?></div>
    <div class="actions">
      <form method="POST" action="submit_post.php" style="display: inline;">
        <input type="hidden" name="index" value="<?= $originalIndexMap[$i] ?? $i ?>">
        <input type="hidden" name="action" value="unstack">
        <button type="button" class="unstack-button" onclick="unstackPost(<?= $i ?>, <?= $originalIndexMap[$i] ?? $i ?>)">📤 My Udastackから外す</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if (count($stackedPosts) === 0): ?>
  <div class="stack-item" style="text-align: center;">
    <h3>まだ積み上げた投稿がありません</h3>
    <div style="background: none; border: none; padding: 2rem;">
      <p style="color: var(--text-muted); margin-bottom: 2rem;">ダッシュボードから投稿をMy Udastackに積み上げてみましょう！</p>
      <a href="dashboard.php" style="
        background: var(--primary-neon);
        color: #000;
        padding: 1rem 2rem;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 700;
        display: inline-block;
        box-shadow: 0 0 15px rgba(252, 200, 0, 0.4);
        transition: all 0.3s ease;
      ">🚀 ダッシュボードへ</a>
    </div>
  </div>
  <?php endif; ?>

  <footer>
    <a href="dashboard.php">← マイページトップに戻る</a>
  </footer>
</div>

<div class="modal-overlay" id="loadingModal">
  <div class="modal-content">
    <div class="spinner"></div>
    再生成中です…しばらくお待ちください
  </div>
</div>

<div class="modal-overlay" id="unstackingModal">
  <div class="modal-content">
    <div class="spinner"></div>
    スタックから外しています…
  </div>
</div>

<script>
function toggleEdit() {
  const summary = document.getElementById('summaryText');
  const editBtn = document.getElementById('editBtn');
  const saveBtn = document.getElementById('saveEdit');
  if (summary.contentEditable === "true") {
    summary.contentEditable = "false";
    saveBtn.style.display = "none";
    editBtn.style.display = "inline-block";
  } else {
    summary.contentEditable = "true";
    summary.focus();
    saveBtn.style.display = "inline-block";
    editBtn.style.display = "none";
  }
}

function saveEditedSummary() {
  const editedText = document.getElementById('summaryText').innerText;
  fetch('save_summary_edit.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ summary: editedText })
  }).then(() => {
    toggleEdit();
    alert('保存しました');
  });
}

function regenerateSummary() {
  const prompt = document.getElementById('aiPrompt').value;
  if (!prompt) return alert('指示を入力してください');
  document.getElementById('loadingModal').style.display = 'flex';
  fetch('regenerate_summary.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ prompt: prompt })
  })
  .then(res => res.json())
  .then(data => {
    document.getElementById('summaryText').innerText = data.summary || '(再生成失敗)';
  })
  .catch(() => alert('再生成に失敗しました'))
  .finally(() => document.getElementById('loadingModal').style.display = 'none');
}

function unstackPost(stackIndex, originalIndex) {
  if (!confirm('この投稿をMy Udastackから外しますか？')) {
    return;
  }
  
  // モーダル表示
  document.getElementById('unstackingModal').style.display = 'flex';
  
  // フォームデータ作成
  const formData = new FormData();
  formData.append('index', originalIndex);
  formData.append('action', 'unstack');
  
  fetch('submit_post.php', {
    method: 'POST',
    body: formData,
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(res => res.json())
  .then(data => {
    document.getElementById('unstackingModal').style.display = 'none';
    if (data.status === 'ok') {
      // アニメーション付きで要素を削除
      const item = document.getElementById('stack-item-' + stackIndex);
      if (item) {
        item.classList.add('fade-out');
        setTimeout(() => {
          item.remove();
          // ページ全体をリロードしてサマリーも更新
          location.reload();
        }, 500);
      }
    } else {
      alert(data.message || 'スタックから外すのに失敗しました');
    }
  })
  .catch(err => {
    document.getElementById('unstackingModal').style.display = 'none';
    alert('通信エラーが発生しました');
    console.error(err);
  });
}

let currentFilterKw = null;
function toggleFilter(kw) {
  const allTags = document.querySelectorAll('.keyword-filter-tag');
  allTags.forEach(tag => {
    if (tag.dataset.kw === kw) {
      if (currentFilterKw === kw) {
        // Reset
        tag.style.background = 'rgba(255, 255, 255, 0.1)';
        tag.style.borderColor = 'var(--accent-teal)';
        tag.style.color = 'var(--text-white)';
      } else {
        // Select
        tag.style.background = 'var(--primary-neon)';
        tag.style.borderColor = 'var(--primary-neon)';
        tag.style.color = '#000';
      }
    } else {
      // Unselected
      tag.style.background = 'rgba(255, 255, 255, 0.1)';
      tag.style.borderColor = 'var(--accent-teal)';
      tag.style.color = 'var(--text-white)';
    }
  });

  if (currentFilterKw === kw) {
    currentFilterKw = null;
  } else {
    currentFilterKw = kw;
  }

  // Filter items
  const items = document.querySelectorAll('.stack-item');
  items.forEach(item => {
    if (!item.dataset.keywords) return; // empty state item
    const kws = JSON.parse(item.dataset.keywords || '[]');
    if (currentFilterKw && !kws.includes(currentFilterKw)) {
      item.style.display = 'none';
    } else {
      item.style.display = 'block';
    }
  });
}
</script>
</body>
</html>