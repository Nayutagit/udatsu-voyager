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
      filter: drop-shadow(0 0 8px rgba(252, 200, 0, 0.6));
    }
    header img.logo:hover {
      transform: scale(1.05);
      filter: drop-shadow(0 0 15px rgba(252, 200, 0, 0.6));
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
    .plan-section {
      text-align: center;
      margin-bottom: 4rem;
      animation: fadeInUp 1s ease 0.3s both;
    }
    @keyframes fadeInUp {
      from { transform: translateY(40px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    .plan-info {
      display: inline-flex;
      align-items: center;
      gap: 2rem;
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      padding: 1.2rem 2.5rem;
      border-radius: 50px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      font-weight: 600;
      font-size: 1.1rem;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
      color: var(--text-white);
    }
    .upgrade-link {
      background: var(--primary-neon);
      color: #000;
      padding: 0.7rem 1.5rem;
      border-radius: 30px;
      font-size: 0.9rem;
      text-decoration: none;
      font-weight: 700;
      transition: all 0.3s ease;
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.4);
    }
    .upgrade-link:hover {
      transform: scale(1.05);
      box-shadow: 0 0 25px rgba(252, 200, 0, 0.6);
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
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
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
    .profile-avatar {
      position: relative;
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
    .profile-avatar::before {
      content: '';
      position: absolute;
      inset: -6px;
      border-radius: 50%;
      background: linear-gradient(45deg, var(--primary-neon), transparent, var(--primary-neon));
      animation: quietGlow 4s ease-in-out infinite;
      z-index: -1;
    }
    @keyframes quietGlow {
      0%, 100% { opacity: 0.3; }
      50% { opacity: 0.6; }
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
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
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
      box-shadow: 0 0 30px rgba(252, 200, 0, 0.6);
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
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
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
      box-shadow: 0 0 10px rgba(252, 200, 0, 0.4);
    }
    .status.stacked {
      background: #a855f7;
      color: white;
      box-shadow: 0 0 10px rgba(168, 85, 247, 0.4);
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
      border-radius: 50px;
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
      backdrop-filter: blur(10px);
    }
    .btn-small:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
    }
    .btn-small.primary {
      background: var(--primary-neon);
      border: none;
      color: #000;
      box-shadow: 0 0 10px rgba(252, 200, 0, 0.4);
    }
    .btn-small.primary:hover {
      box-shadow: 0 0 20px rgba(252, 200, 0, 0.6);
    }
    .btn-small.danger {
      background: #ff5252;
      color: white;
      border: none;
      box-shadow: 0 0 10px rgba(255, 82, 82, 0.4);
    }
    .btn-small.secondary {
      background: var(--accent-teal);
      color: white;
      border: none;
      box-shadow: 0 0 10px rgba(0, 164, 216, 0.4);
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
      position: relative;
      overflow: hidden;
    }
    .nav-btn:hover {
      color: #000;
      background: var(--primary-neon);
      border-color: var(--primary-neon);
      transform: translateY(-3px);
      box-shadow: 0 0 15px rgba(252, 200, 0, 0.4);
    }
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
      backdrop-filter: blur(25px);
      padding: 3.5rem;
      border-radius: 4px;
      text-align: center;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
      border: 2px solid var(--primary-neon);
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
      color: var(--text-white);
    }
    .modal-content p {
      color: var(--text-muted);
      margin-bottom: 2.5rem;
      line-height: 1.7;
      font-size: 1.05rem;
    }
    .progress-bar {
      width: 100%;
      height: 10px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 5px;
      overflow: hidden;
      margin: 2.5rem 0;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .progress-fill {
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, var(--primary-neon), #ffda44, var(--accent-teal));
      box-shadow: 0 0 10px rgba(252, 200, 0, 0.4);
      animation: loading 2.5s ease-in-out infinite;
    }
    @keyframes loading {
      0% { transform: translateX(-100%); }
      50% { transform: translateX(0); }
      100% { transform: translateX(100%); }
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
    .fade-in-stagger {
      animation: fadeInUp 0.8s ease both;
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

  <div class="plan-section">
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
      <a href="edit_profile.php" class="btn-primary">
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
                  <button type="button" class="btn-small secondary" onclick="unstackPost(<?= $i ?>)">My Udastackから外す</button>
                </form>
              <?php else: ?>
                <form id="stackForm<?= $i ?>" method="POST" action="submit_post.php" style="display: inline;">
                  <input type="hidden" name="index" value="<?= $i ?>">
                  <input type="hidden" name="action" value="stack">
                  <button type="button" class="btn-small secondary" onclick="stackPost(<?= $i ?>)">My Udastackに追加</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="navigation">
    <a href="dashboard.php" class="nav-btn">ダッシュボードへ戻る</a>
    <a href="../logout.php" class="nav-btn" style="border-color: #ff5252; color: #ff5252;">ログアウト</a>
  </div>
</div>

<div id="deleteModal" class="modal">
  <div class="modal-content">
    <h3>本当に削除しますか？</h3>
    <p>この操作は取り消せません。</p>
    <div class="action-buttons" style="justify-content: center;">
      <button onclick="executeDelete()" class="btn-small danger">削除する</button>
      <button onclick="closeModal()" class="btn-small">キャンセル</button>
    </div>
  </div>
</div>

<div id="stackingModal" class="modal">
  <div class="modal-content">
    <h3>My Udastackに追加中...</h3>
    <p>AIが要約とキーワード抽出を行っています。<br>そのままお待ちください。</p>
    <div class="progress-bar">
      <div class="progress-fill"></div>
    </div>
  </div>
</div>

<script>
  let deleteTargetIndex = null;

  function checkPostLimit() {
    const currentPosts = <?= count($visiblePosts) ?>;
    const limit = <?= $postLimit ?>;
    if (currentPosts >= limit) {
      alert(`プランの上限（${limit}件）に達しています。\n古い投稿を削除するか、プランをアップグレードしてください。`);
    } else {
      window.location.href = 'create_post.php';
    }
  }

  function confirmDelete(index) {
    deleteTargetIndex = index;
    document.getElementById('deleteModal').style.display = 'flex';
  }

  function closeModal() {
    document.getElementById('deleteModal').style.display = 'none';
    deleteTargetIndex = null;
  }

  function executeDelete() {
    if (deleteTargetIndex === null) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'delete_post.php';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'index';
    input.value = deleteTargetIndex;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
  }

  function stackPost(index) {
    const currentStack = <?= $stackedCount ?>;
    const limit = <?= $stackLimit ?>;
    if (currentStack >= limit) {
      alert(`My Udastackの上限（${limit}件）に達しています。\nプランをアップグレードしてください。`);
      return;
    }
    if (!confirm('この投稿をMy Udastackに追加しますか？\n（AIが要約とキーワード抽出を行います）')) return;
    
    document.getElementById('stackingModal').style.display = 'flex';
    document.getElementById('stackForm' + index).submit();
  }

  function unstackPost(index) {
    if (!confirm('この投稿をMy Udastackから外しますか？')) return;
    document.getElementById('unstackForm' + index).submit();
  }

  // エフェクト初期化
  document.addEventListener('DOMContentLoaded', function() {
    const posts = document.querySelectorAll('.post-item');
    posts.forEach((post, index) => {
      post.style.animationDelay = `${1.2 + (index * 0.1)}s`;
      post.classList.add('fade-in-stagger');
    });
  });
</script>
</body>
</html>