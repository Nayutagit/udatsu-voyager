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
<html lang="ja" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Udatsu | プロフィール</title>
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
  <style>
    .profile-header {
      padding: 30px 20px;
      text-align: center;
      background: var(--bg-secondary);
      border-bottom: 1px solid var(--border-color);
      margin-bottom: 20px;
    }
    .profile-header img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 15px;
      border: 3px solid var(--accent-color);
    }
    .profile-name {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 5px;
    }
    .profile-title {
      font-size: 0.9rem;
      color: var(--accent-color);
      margin-bottom: 15px;
    }
    .profile-bio {
      font-size: 0.95rem;
      color: var(--text-secondary);
      line-height: 1.5;
      margin-bottom: 20px;
      padding: 0 20px;
    }
    .profile-stats {
      display: flex;
      justify-content: center;
      gap: 30px;
      margin-bottom: 20px;
    }
    .stat-item {
      text-align: center;
    }
    .stat-val {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--text-primary);
    }
    .stat-lbl {
      font-size: 0.8rem;
      color: var(--text-tertiary);
    }
    .action-row {
      display: flex;
      justify-content: center;
      gap: 10px;
    }
    .btn-edit {
      padding: 8px 24px;
      border-radius: 20px;
      background: var(--bg-tertiary);
      color: var(--text-primary);
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 600;
      border: 1px solid var(--border-color);
    }
    .btn-edit:active {
      background: var(--bg-secondary);
    }
    .btn-logout {
      padding: 8px 24px;
      border-radius: 20px;
      background: rgba(255, 68, 68, 0.1);
      color: #ff4444;
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 600;
      border: 1px solid rgba(255, 68, 68, 0.2);
    }
    .posts-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 2px;
      padding: 0 2px 20px 2px;
    }
    .grid-item {
      aspect-ratio: 1;
      background: var(--bg-tertiary);
      position: relative;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .grid-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .grid-item .overlay {
      position: absolute;
      inset: 0;
      background: rgba(0,0,0,0.6);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: white;
      opacity: 0;
      transition: opacity 0.2s;
    }
    .grid-item:active .overlay {
      opacity: 1;
    }
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: var(--text-tertiary);
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/header.php'; ?>

<main class="app-main" style="padding-top: 60px;">
  
  <div class="profile-header">
    <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($displayName) ?>">
    <div class="profile-name"><?= htmlspecialchars($displayName) ?></div>
    <div class="profile-title"><?= htmlspecialchars($title) ?></div>
    
    <div class="profile-stats">
      <div class="stat-item">
        <div class="stat-val"><?= count($visiblePosts) ?></div>
        <div class="stat-lbl">投稿</div>
      </div>
      <div class="stat-item">
        <div class="stat-val"><?= $remainingPosts ?></div>
        <div class="stat-lbl">残り枠</div>
      </div>
      <div class="stat-item">
        <div class="stat-val"><?= htmlspecialchars($userPlan) ?></div>
        <div class="stat-lbl">プラン</div>
      </div>
    </div>

    <?php if (!empty($profile['bio'])): ?>
    <div class="profile-bio"><?= nl2br(htmlspecialchars($profile['bio'])) ?></div>
    <?php endif; ?>

    <div class="action-row">
      <a href="edit_profile.php" class="btn-edit">プロフィール編集</a>
      <a href="../logout.php" class="btn-logout">ログアウト</a>
    </div>
  </div>

  <?php if (count($visiblePosts) === 0): ?>
    <div class="empty-state">
      <i class="fas fa-microphone-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
      <p>まだ投稿がありません</p>
    </div>
  <?php else: ?>
    <div class="posts-grid">
      <?php foreach (array_reverse($visiblePosts, true) as $i => $post): ?>
        <a href="view_post.php?index=<?= $i ?>" class="grid-item">
          <?php if (!empty($post['thumbnail'])): ?>
            <img src="../<?= htmlspecialchars($post['thumbnail']) ?>" alt="Thumbnail">
          <?php else: ?>
            <!-- Default placeholder for voice-only post -->
            <div style="color: var(--text-tertiary); font-size: 2rem;">
              <i class="fas fa-music"></i>
            </div>
          <?php endif; ?>
          <div class="overlay">
            <i class="fas fa-play" style="margin-bottom: 5px;"></i>
            <span style="font-size: 0.75rem; text-align: center; padding: 0 5px;"><?= mb_strimwidth($post['title'], 0, 20, '…') ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>

<nav class="app-nav">
  <a href="mypage.php" class="nav-item">
    <i class="fas fa-home"></i>
    <span>ホーム</span>
  </a>
  <a href="timeline.php" class="nav-item">
    <i class="fas fa-compass"></i>
    <span>探す</span>
  </a>
  <a href="../voyager_upload.php" class="nav-item" aria-label="録音">
    <div class="nav-record">
      <i class="fas fa-microphone"></i>
    </div>
  </a>
  <a href="network.php" class="nav-item">
    <i class="fas fa-user-friends"></i>
    <span>つながり</span>
  </a>
  <a href="profile.php" class="nav-item active">
    <i class="fas fa-user"></i>
    <span>自分</span>
  </a>
</nav>

<script>
// Mobile specific simple interactions can go here
</script>
</body>
</html>