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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>

  <!-- Header -->
  <header class="header">
    <div class="container header-inner">
      <a href="../index.php" class="logo">
        <img src="../img/udatsu-logo.png" alt="Udatsu Logo">
        <span>Voyager</span>
      </a>
      <div class="nav-user">
        <div class="user-badge">
          <i class="fas fa-user-circle"></i> <?= htmlspecialchars($userName) ?>
        </div>
        <div class="user-badge">
          Plan: <strong><?= htmlspecialchars($userPlan) ?></strong>
        </div>
        <a href="../voyager_upload.php" class="btn btn-primary" style="padding: 8px 20px; font-size: 0.9rem;">
          <i class="fas fa-upload"></i> Upload
        </a>
      </div>
    </div>
  </header>

  <div class="container" style="padding-top: 120px; padding-bottom: 60px;">

    <!-- Profile Section -->
    <div class="glass-card animate-fadeup" style="display: flex; align-items: center; gap: 30px; margin-bottom: 40px;">
      <div style="position: relative;">
        <img src="<?= htmlspecialchars($imagePath) ?>" alt="Profile" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-neon); box-shadow: 0 0 15px rgba(252, 200, 0, 0.3);">
        <a href="profile.php" style="position: absolute; bottom: 0; right: 0; background: var(--bg-dark); border: 1px solid var(--primary-neon); color: var(--primary-neon); width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
          <i class="fas fa-pen"></i>
        </a>
      </div>
      <div style="flex: 1;">
        <h2 style="font-size: 1.8rem; margin-bottom: 5px;"><?= htmlspecialchars($displayName) ?></h2>
        <p class="text-muted" style="margin-bottom: 10px;"><?= !empty($title) ? htmlspecialchars($title) : 'No Title' ?></p>
        <div style="display: flex; gap: 15px;">
          <div class="user-badge"><i class="fas fa-layer-group"></i> Stacked: <strong><?= $stackedCount ?> / <?= $stackLimit ?></strong></div>
          <div class="user-badge"><i class="fas fa-pen-nib"></i> Posts: <strong><?= count($visiblePosts) ?></strong></div>
        </div>
      </div>
      <div>
        <a href="membership.php" class="btn btn-secondary">
          <i class="fas fa-crown"></i> Upgrade Plan
        </a>
      </div>
    </div>

    <!-- Main Actions -->
    <div class="dashboard-grid animate-fadeup delay-100" style="margin-bottom: 60px;">
      <a href="my_udastack.php" class="glass-card" style="text-align: center; display: block; text-decoration: none; border-color: var(--accent-teal);">
        <i class="fas fa-layer-group text-teal" style="font-size: 3rem; margin-bottom: 20px;"></i>
        <h3 style="margin-bottom: 10px;">My Udastack</h3>
        <p class="text-muted">蓄積された思考資産を確認</p>
      </a>
      
      <a href="javascript:void(0)" onclick="checkPostLimit()" class="glass-card" style="text-align: center; display: block; text-decoration: none; border-color: var(--primary-neon);">
        <i class="fas fa-pen-fancy text-neon" style="font-size: 3rem; margin-bottom: 20px;"></i>
        <h3 style="margin-bottom: 10px;">Create Post</h3>
        <p class="text-muted">新しい記事を作成する</p>
      </a>
    </div>

    <!-- Recent Posts -->
    <h2 class="section-title animate-fadeup delay-200" style="font-size: 1.8rem; margin-bottom: 30px;">Recent Posts</h2>
    
    <?php if (count($visiblePosts) === 0): ?>
      <div class="glass-card animate-fadeup delay-300" style="text-align: center; padding: 60px;">
        <i class="fas fa-ghost text-muted" style="font-size: 4rem; margin-bottom: 20px;"></i>
        <h3>No posts yet</h3>
        <p class="text-muted" style="margin-bottom: 30px;">最初のうだつを上げてみましょう！</p>
        <button onclick="checkPostLimit()" class="btn btn-primary">
          <i class="fas fa-plus"></i> Create First Post
        </button>
      </div>
    <?php else: ?>
      <div class="dashboard-grid animate-fadeup delay-300">
        <?php foreach ($visiblePosts as $i => $post): ?>
          <div class="post-card">
            <div class="post-content">
              <div class="post-meta" style="margin-bottom: 10px;">
                <span><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($post['date']) ?></span>
                <span class="text-teal"><?= htmlspecialchars($post['category'] ?? 'Uncategorized') ?></span>
              </div>
              <h3 class="post-title" style="min-height: 3em;">
                <a href="view_post.php?index=<?= $i ?>"><?= htmlspecialchars(mb_strimwidth($post['title'], 0, 50, '...')) ?></a>
              </h3>
              
              <div style="display: flex; gap: 10px; margin-top: 20px;">
                <a href="edit_post.php?index=<?= $i ?>" class="btn btn-secondary" style="padding: 5px 15px; font-size: 0.8rem; flex: 1;">
                  <i class="fas fa-edit"></i> Edit
                </a>
                <?php if ($post['status'] === 'My Udastack追加済'): ?>
                  <button class="btn btn-primary" style="padding: 5px 15px; font-size: 0.8rem; opacity: 0.7;" disabled>
                    <i class="fas fa-check"></i> Stacked
                  </button>
                <?php else: ?>
                  <form method="POST" action="submit_post.php" style="flex: 1;">
                    <input type="hidden" name="index" value="<?= $i ?>">
                    <input type="hidden" name="action" value="stack">
                    <button type="submit" class="btn btn-primary" style="padding: 5px 15px; font-size: 0.8rem; width: 100%;">
                      <i class="fas fa-plus"></i> Stack
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 60px;">
      <a href="logout.php" style="color: var(--text-muted); font-size: 0.9rem;">
        <i class="fas fa-sign-out-alt"></i> Log out
      </a>
    </div>

  </div>

<script>
function checkPostLimit() {
  fetch('check_post_limit.php')
    .then(res => res.json())
    .then(data => {
      if (data.status === 'limit') {
        alert(data.message);
      } else {
        window.location.href = 'create_post.php';
      }
    })
    .catch(() => {
      window.location.href = 'create_post.php'; // Fallback
    });
}
</script>
</body>
</html>