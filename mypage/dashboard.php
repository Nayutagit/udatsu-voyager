<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../plan_limits.php';

if (empty($uid) || empty($userName) || empty($userPlan)) {
  header('Location: index.php');
  exit();
}

$userDir     = __DIR__ . '/../users/';
$profileFile = $userDir . $uid . '_profile.json';
$postsFile   = $userDir . $uid . '_posts.json';

$profile = ["display_name" => $userName, "title" => '', "bio" => '', "image" => ''];
if (file_exists($profileFile)) {
  $profile = json_decode(file_get_contents($profileFile), true);
}
$displayName = $profile['display_name'] ?? $userName;
$title = $profile['title'] ?? '';
$imagePath   = !empty($profile['image']) ? '../uploads/' . $uid . '/' . $profile['image'] : '../img/default-icon.png';

$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
foreach ($posts as &$p) {
  if (!isset($p['status'])) {
    $p['status'] = '';
  }
}
unset($p);

function getPostStatusInfo($status) {
  if ($status === 'My Udastack追加済') {
    return ['label' => 'My Udastack追加済', 'class' => 'stacked'];
  }
  return ['label' => '', 'class' => ''];
}

$visiblePosts = array_filter($posts, fn($p) => $p['status'] !== '削除済');
$postLimit = $plan_limits[$userPlan]['post_limit'] ?? 100;
$remainingPosts = max(0, $postLimit - count($visiblePosts));
$stackedCount = count(array_filter($posts, fn($p) => $p['status'] === 'My Udastack追加済'));
$stackLimit   = $plan_limits[$userPlan]['max_stack_posts'] ?? 0;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Udatsuマイページ</title>
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
    header {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      margin-bottom: 3rem;
      padding: 1rem 0;
      animation: slideDown 1s ease;
    }
    @keyframes slideDown {
      from { transform: translateY(-30px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    header img.logo {
      height: 52px;
      transition: all 0.4s ease;
      filter: drop-shadow(0 0 8px var(--neon-gold-glow));
    }
    header img.logo:hover {
      transform: scale(1.05);
      filter: drop-shadow(0 0 15px var(--neon-gold-glow));
    }
    header a {
      color: var(--text-white);
      font-weight: 700;
      font-size: 1.3rem;
      text-decoration: none;
      transition: all 0.3s ease;
    }
    header a:hover {
      color: var(--primary-neon);
    }
    .welcome-section {
      text-align: center;
      margin-bottom: 4rem;
      animation: fadeInUp 1s ease 0.3s both;
    }
    .plan-info {
      display: inline-flex;
      align-items: center;
      gap: 2rem;
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      padding: 1.2rem 2.5rem;
      border-radius: 50px;
      border: 1px solid var(--accent-teal);
      font-weight: 600;
      font-size: 1.1rem;
      box-shadow: 0 0 15px rgba(0, 104, 136, 0.3);
    }
    .upgrade-link {
      background: var(--accent-teal);
      color: white;
      padding: 0.7rem 1.5rem;
      border-radius: 30px;
      font-size: 0.9rem;
      text-decoration: none;
      font-weight: 700;
      transition: all 0.3s ease;
    }
    .upgrade-link:hover {
      transform: scale(1.05);
      box-shadow: 0 0 15px rgba(0, 104, 136, 0.5);
    }
    .profile-card {
      display: flex;
      gap: 2.5rem;
      align-items: center;
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      padding: 2.5rem;
      border-radius: 4px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      margin-bottom: 4rem;
      animation: slideInLeft 1s ease 0.6s both;
      transition: all 0.4s ease;
    }
    @keyframes slideInLeft {
      from { transform: translateX(-60px); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    .profile-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
    }
    .profile-avatar img {
      width: 110px;
      height: 110px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--primary-neon);
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.3);
      transition: all 0.4s ease;
    }
    .profile-info h2 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
      color: var(--text-white);
    }
    .profile-info .title {
      font-size: 1.2rem;
      color: var(--primary-neon);
      margin-bottom: 1rem;
      font-weight: 500;
    }
    .profile-info p {
      color: var(--text-muted);
      margin-bottom: 2rem;
      line-height: 1.7;
      font-size: 1.05rem;
    }
    .main-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2.5rem;
      margin-bottom: 4rem;
      animation: fadeInUp 1s ease 0.9s both;
    }
    .action-card {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      padding: 3rem;
      border-radius: 4px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      text-align: center;
      transition: all 0.4s ease;
    }
    .action-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
      border-color: var(--primary-neon);
    }
    .action-card h3 {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 1.2rem;
      color: var(--text-white);
    }
    .action-card p {
      color: var(--text-muted);
      margin-bottom: 2.5rem;
      font-size: 1rem;
      line-height: 1.6;
    }
    .btn-primary {
      background: var(--primary-neon);
      color: #000;
      padding: 1rem 2.5rem;
      border: none;
      border-radius: 50px;
      font-size: 1.1rem;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.8rem;
      transition: all 0.3s ease;
      box-shadow: 0 0 20px rgba(252, 200, 0, 0.4);
      min-width: 220px;
    }
    .btn-primary:hover {
      transform: translateY(-3px) scale(1.05);
      box-shadow: 0 0 30px rgba(252, 200, 0, 0.6);
    }
    .big-button {
      background: var(--primary-neon);
      color: #000;
      padding: 1.8rem 2rem;
      border: none;
      border-radius: 4px;
      font-size: 1.2rem;
      font-weight: 700;
      cursor: pointer;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      transition: all 0.3s ease;
      box-shadow: 0 0 25px rgba(252, 200, 0, 0.4);
      text-decoration: none;
    }
    .big-button:hover {
      transform: scale(1.03);
      box-shadow: 0 0 35px rgba(252, 200, 0, 0.6);
    }
    .posts-section {
      animation: fadeInUp 1s ease 1.2s both;
    }
    .posts-section h2 {
      font-size: 2.2rem;
      font-weight: 700;
      margin-bottom: 3rem;
      color: var(--text-white);
    }
    .post-grid {
      display: grid;
      gap: 2rem;
    }
    .post-item {
      background: var(--bg-panel);
      backdrop-filter: blur(25px);
      padding: 2rem;
      border-radius: 4px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      transition: all 0.4s ease;
      position: relative;
      overflow: hidden;
    }
    .post-item:hover {
      transform: translateY(-5px);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
      border-color: var(--primary-neon);
    }
    .post-meta {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      font-size: 0.9rem;
      color: var(--text-muted);
      margin-bottom: 1.2rem;
      flex-wrap: wrap;
    }
    .post-title {
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: 2rem;
      line-height: 1.5;
      color: var(--text-white);
    }
    .status {
      display: inline-flex;
      align-items: center;
      padding: 0.4rem 1rem;
      border-radius: 25px;
      font-size: 0.8rem;
      font-weight: 600;
      background: var(--primary-neon);
      color: #000;
    }
    .status.stacked {
      background: var(--accent-teal);
      color: white;
    }
    .action-buttons {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin-top: 1.5rem;
    }
    .btn-small {
      padding: 0.6rem 1.2rem;
      border: none;
      border-radius: 4px;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s ease;
      background: rgba(255, 255, 255, 0.1);
      color: var(--text-white);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .btn-small:hover {
      transform: translateY(-2px);
      background: rgba(255, 255, 255, 0.2);
    }
    .btn-small.primary {
      background: var(--primary-neon);
      border: none;
      color: #000;
    }
    .btn-small.danger {
      background: #ff5252;
      color: white;
      border: none;
    }
    .navigation {
      display: flex;
      gap: 1.5rem;
      justify-content: center;
      flex-wrap: wrap;
      margin: 5rem 0 3rem;
      animation: fadeInUp 1s ease 1.5s both;
    }
    .nav-btn {
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      color: var(--text-white);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 50px;
      padding: 1rem 2rem;
      font-size: 1rem;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.3s ease;
    }
    .nav-btn:hover {
      color: #000;
      background: var(--primary-neon);
      border-color: var(--primary-neon);
      transform: translateY(-3px);
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.4);
    }
    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1000;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(15px);
      justify-content: center;
      align-items: center;
      animation: modalFadeIn 0.4s ease;
    }
    @keyframes modalFadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    .modal-content {
      background: var(--bg-panel);
      padding: 3.5rem;
      border-radius: 4px;
      text-align: center;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
      border: 1px solid var(--primary-neon);
      animation: modalSlideIn 0.5s ease;
    }
    @keyframes modalSlideIn {
      from { transform: scale(0.9) translateY(30px); opacity: 0; }
      to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .modal-content h3 {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 1.5rem;
      color: var(--primary-neon);
    }
    .modal-content p {
      color: var(--text-muted);
      margin-bottom: 2.5rem;
      line-height: 1.7;
      font-size: 1.05rem;
    }
    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }
      .main-actions {
        grid-template-columns: 1fr;
      }
      .profile-card {
        flex-direction: column;
        text-align: center;
        gap: 1.5rem;
      }
      .action-buttons {
        flex-direction: column;
      }
      .navigation {
        flex-direction: column;
        align-items: center;
      }
    }
  </style>
</head>
<body>
<div class="container">
  <header>
    <a href="https://udatsuageteko.com">
      <img src="../img/udatsu-logo.png" alt="Udatsuロゴ" class="logo">
    </a>
    <a href="dashboard.php">Udatsuマイページ</a>
  </header>

  <div class="welcome-section">
    <div class="plan-info">
      <span>現在のプラン：<strong><?= htmlspecialchars($userPlan) ?></strong></span>
      <a href="membership.php" class="upgrade-link">アップグレード</a>
    </div>
  </div>

  <div class="profile-card">
    <div class="profile-avatar">
      <img src="<?= htmlspecialchars($imagePath) ?>" alt="プロフィール画像">
    </div>
    <div class="profile-info">
      <h2><?= htmlspecialchars($displayName) ?></h2>
      <?php if (!empty($title)): ?>
        <div class="title"><?= htmlspecialchars($title) ?></div>
      <?php endif; ?>
      <?php if (!empty($profile['bio'])): ?>
        <p><?= nl2br(htmlspecialchars($profile['bio'])) ?></p>
      <?php else: ?>
        <p>プロフィールを設定してください。</p>
      <?php endif; ?>
      <a href="profile.php" class="btn-primary">
        <span>プロフィール編集</span>
      </a>
    </div>
  </div>

  <div class="main-actions">
    <div class="action-card">
      <h3>My Udastack</h3>
      <p>思考資産を確認・管理</p>
      <a href="my_udastack.php" class="big-button">
        <span>My Udastack</span>
      </a>
    </div>
    
    <div class="action-card">
      <h3>新規投稿</h3>
      <p>新しいうだつを作成</p>
      <button onclick="checkPostLimit()" class="big-button">
        <span>新規投稿</span>
      </button>
    </div>
  </div>

  <div class="posts-section">
    <h2>今まで上げたうだつ</h2>
    <?php if (count($visiblePosts) === 0): ?>
      <div class="action-card" style="margin-top: 3rem;">
        <h3>まだ投稿がありません</h3>
        <p>最初のうだつを上げてみましょう！</p>
        <button onclick="checkPostLimit()" class="big-button">
          <span>投稿を作成</span>
        </button>
      </div>
    <?php else: ?>
      <div class="post-grid">
        <?php foreach ($visiblePosts as $i => $post): ?>
          <div class="post-item">
            <div class="post-meta">
              <span><?= htmlspecialchars($post['date']) ?></span>
              <span><?= htmlspecialchars($post['category'] ?? '未分類') ?></span>
              <?php $statusInfo = getPostStatusInfo($post['status']); ?>
              <?php if ($statusInfo['label']): ?>
                <span class="status <?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
              <?php endif; ?>
            </div>
            <div class="post-title"><?= htmlspecialchars($post['title']) ?></div>
            <div class="action-buttons">
              <a href="view_post.php?index=<?= $i ?>" class="btn-small">詳細を見る</a>
              <a href="edit_post.php?index=<?= $i ?>" class="btn-small primary">編集する</a>
              <button class="btn-small danger" onclick="confirmDelete(<?= $i ?>)" type="button">削除する</button>

              <?php if ($post['status'] === 'My Udastack追加済'): ?>
                <form id="unstackForm<?= $i ?>" method="POST" action="submit_post.php" style="display: inline;">
                  <input type="hidden" name="index" value="<?= $i ?>">
                  <input type="hidden" name="action" value="unstack">
                  <button type="button" class="btn-small secondary" onclick="confirmUnstack(<?= $i ?>)">Udastackから外す</button>
                </form>
              <?php else: ?>
                <form id="stackForm<?= $i ?>" method="POST" action="submit_post.php" style="display: inline;">
                  <input type="hidden" name="index" value="<?= $i ?>">
                  <input type="hidden" name="action" value="stack">
                  <button type="button" class="btn-small secondary" onclick="confirmStack(<?= $i ?>)">My Udastackに追加</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="navigation">
    <a href="../voyager_upload.php" class="nav-btn">音声アップロードへ</a>
    <a href="logout.php" class="nav-btn">ログアウト</a>
  </div>
</div>

<!-- 削除確認モーダル -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <h3>本当に削除しますか？</h3>
    <p>この操作は取り消せません。</p>
    <div class="action-buttons" style="justify-content: center;">
      <button class="btn-small" onclick="closeDeleteModal()">キャンセル</button>
      <form id="deleteForm" method="POST" action="submit_post.php">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="index" id="deleteIndex">
        <button type="submit" class="btn-small danger">削除する</button>
      </form>
    </div>
  </div>
</div>

<!-- Stack確認モーダル -->
<div id="stackModal" class="modal">
  <div class="modal-content">
    <h3>My Udastackに追加</h3>
    <p>
      現在: <?= $stackedCount ?> / <?= $stackLimit ?><br>
      この投稿を思考資産として保存しますか？
    </p>
    <div class="action-buttons" style="justify-content: center;">
      <button class="btn-small" onclick="closeStackModal()">キャンセル</button>
      <button class="btn-small primary" onclick="submitStack()">追加する</button>
    </div>
  </div>
</div>

<!-- Unstack確認モーダル -->
<div id="unstackModal" class="modal">
  <div class="modal-content">
    <h3>My Udastackから外す</h3>
    <p>この投稿を思考資産から外しますか？</p>
    <div class="action-buttons" style="justify-content: center;">
      <button class="btn-small" onclick="closeUnstackModal()">キャンセル</button>
      <button class="btn-small danger" onclick="submitUnstack()">外す</button>
    </div>
  </div>
</div>

<script>
function checkPostLimit() {
  const remaining = <?= $remainingPosts ?>;
  if (remaining <= 0) {
    alert("今月の投稿上限に達しています。プランをアップグレードしてください。");
    window.location.href = "membership.php";
  } else {
    window.location.href = "create_post.php";
  }
}

// 削除モーダル
let deleteIndex = null;
function confirmDelete(index) {
  deleteIndex = index;
  document.getElementById('deleteIndex').value = index;
  document.getElementById('deleteModal').style.display = 'flex';
}
function closeDeleteModal() {
  document.getElementById('deleteModal').style.display = 'none';
}

// Stackモーダル
let stackIndex = null;
function confirmStack(index) {
  const current = <?= $stackedCount ?>;
  const limit = <?= $stackLimit ?>;
  if (limit !== 0 && current >= limit) { // Check if limit is not 0 and current count exceeds it
    alert("My Udastackの上限に達しています。");
    return;
  }
function showStackingModal() {
  const modal = document.getElementById('stackingModal');
  if (modal) modal.style.display = 'flex';
}

function hideStackingModal() {
  const modal = document.getElementById('stackingModal');
  if (modal) modal.style.display = 'none';
}

function showStackLimitModal() {
  const modal = document.getElementById('stackLimitModal');
  if (modal) modal.style.display = 'flex';
}

function hideStackLimitModal() {
  const modal = document.getElementById('stackLimitModal');
  if (modal) modal.style.display = 'none';
}

function checkPostLimit() {
  fetch('check_post_limit.php', {
    method: 'GET',
    credentials: 'same-origin'
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'limit') {
      alert(data.message);
    } else {
      window.location.href = 'create_post.php';
    }
  })
  .catch(() => {
    alert('通信エラーが発生しました');
  });
}

// シンプルなエフェクト
document.addEventListener('DOMContentLoaded', function() {
  // 投稿アイテムにスタガーアニメーション
  const postItems = document.querySelectorAll('.post-item');
  postItems.forEach((item, index) => {
    item.style.animationDelay = `${1.2 + (index * 0.15)}s`;
    item.classList.add('fade-in-stagger');
  });
  
  // リップル効果
  const buttons = document.querySelectorAll('.btn-primary, .big-button, .btn-small, .nav-btn');
  buttons.forEach(btn => {
    btn.addEventListener('click', function(e) {
      const ripple = document.createElement('span');
      const rect = btn.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;
      
      ripple.className = 'ripple';
      ripple.style.width = `${size}px`;
      ripple.style.height = `${size}px`;
      ripple.style.left = `${x}px`;
      ripple.style.top = `${y}px`;
      
      btn.style.position = 'relative';
      btn.style.overflow = 'hidden';
      btn.appendChild(ripple);
      
      setTimeout(() => ripple.remove(), 600);
    });
  });
});
</script>
</body>
</html>