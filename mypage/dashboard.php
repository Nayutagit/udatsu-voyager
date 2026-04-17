<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../plan_limits.php';

if (empty($uid) || empty($userName) || empty($userPlan)) {
  header('Location: index.php');
  exit();
}

$uploadSuccess = $_GET['upload_success'] ?? '0';

$userDir     = __DIR__ . '/../users/';
$profileFile = $userDir . $uid . '_profile.json';
$postsFile   = $userDir . $uid . '_posts.json';
$dictFile    = $userDir . $uid . '_dictionary.json';

$thoughtDict = file_exists($dictFile) ? json_decode(file_get_contents($dictFile), true) : [];
if (!is_array($thoughtDict)) $thoughtDict = [];

// Thought DNA profile
$profileDataFile = $userDir . $uid . '_thought_profile.json';
$thoughtProfile  = file_exists($profileDataFile) ? json_decode(file_get_contents($profileDataFile), true) : null;
$sessFile        = $userDir . $uid . '_quiz_sessions.json';
$quizSessions    = file_exists($sessFile) ? json_decode(file_get_contents($sessFile), true) : [];
$quizCount       = count($quizSessions ?? []);

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

  <?php if ($uploadSuccess === '1'): ?>
  <div id="toast" style="position:fixed; bottom:20px; right:20px; background:var(--primary-neon); color:#000; padding:15px 20px; border-radius:8px; font-weight:bold; box-shadow:0 10px 30px rgba(0,255,204,0.3); z-index:9999; animation: slideIn 0.5s ease-out;">
    <i class="fas fa-check-circle"></i> 音声解析中です。他の音声も続けてアップロードできます。
  </div>
  <script>
    setTimeout(() => {
      const toast = document.getElementById('toast');
      if (toast) {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s ease';
        setTimeout(() => toast.remove(), 500);
      }
    }, 5000);
  </script>
  <style>
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
  </style>
  <?php endif; ?>

    <!-- Main Actions -->
    <div class="dashboard-grid animate-fadeup delay-100" style="margin-bottom: 60px;">
      <a href="my_udastack.php" class="glass-card" style="text-align: center; display: block; text-decoration: none; border-color: var(--accent-teal);">
        <i class="fas fa-layer-group text-teal" style="font-size: 3rem; margin-bottom: 20px;"></i>
        <h3 style="margin-bottom: 10px;">My Udastack</h3>
        <p class="text-muted">蓄積された思考資産を確認</p>
      </a>
      
      <a href="../voyager_upload.php" class="glass-card" style="text-align: center; display: block; text-decoration: none; border-color: var(--primary-neon);">
        <i class="fas fa-microphone-alt text-neon" style="font-size: 3rem; margin-bottom: 20px;"></i>
        <h3 style="margin-bottom: 10px;">Record Journal</h3>
        <p class="text-muted">声で新しい思考を記録する</p>
      </a>

      <a href="shortcut_setup.php" class="glass-card" style="text-align: center; display: block; text-decoration: none; border-color: #007aff;">
        <i class="fab fa-apple" style="font-size: 3rem; margin-bottom: 20px; color: #007aff;"></i>
        <h3 style="margin-bottom: 10px;">iPhone Shortcut</h3>
        <p class="text-muted">共有ボタンから直接アップロード</p>
      </a>

      <a href="thought_quiz.php" class="glass-card" style="text-align: center; display: block; text-decoration: none; border-color: #a855f7; position: relative; overflow: hidden;">
        <div style="position:absolute; top:10px; right:12px; background: rgba(168,85,247,0.2); color:#a855f7; font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:99px;"><?= $quizCount > 0 ? $quizCount . '回目' : 'NEW' ?></div>
        <i class="fas fa-dna" style="font-size: 3rem; margin-bottom: 20px; color: #a855f7;"></i>
        <h3 style="margin-bottom: 10px;">思考DNA診断</h3>
        <p class="text-muted">A/B質問で思考傾向を分析</p>
      </a>
    </div>

    <div style="text-align: right; margin-top: -40px; margin-bottom: 40px;">
      <a href="javascript:void(0)" onclick="checkPostLimit()" style="color: var(--text-muted); font-size: 0.9rem; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='var(--text-white)'" onmouseout="this.style.color='var(--text-muted)'">
        <i class="fas fa-keyboard"></i> 手入力で記録を作成する
      </a>
    </div>

    <!-- Thought Core -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;" class="animate-fadeup delay-100">
      <h2 class="section-title" style="font-size: 1.8rem; margin-bottom: 0;">🧠 Thought Core</h2>
      <button onclick="updateBrain()" class="btn btn-secondary" style="padding: 5px 15px; font-size: 0.8rem; height: fit-content;" id="updateBrainBtn">
        <i class="fas fa-sync-alt"></i> 最新データで分析する
      </button>
    </div>

    <div class="glass-card animate-fadeup delay-100" style="margin-bottom: 30px; padding: 20px;">
      <?php if (empty($thoughtDict)): ?>
        <p class="text-muted" style="text-align: center; margin: 20px 0;">まだ脳内分析データがありません。「最新データで分析する」ボタンを押すとAIがあなたの傾向を分析します。</p>
      <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
        <?php foreach ($thoughtDict as $keyword => $description): ?>
          <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 15px;">
            <h4 style="color: var(--primary-neon); margin-bottom: 10px; font-size: 1.1rem;">#<?= htmlspecialchars($keyword) ?></h4>
            <p style="color: var(--text-white); font-size: 0.9rem; line-height: 1.5; margin: 0;"><?= htmlspecialchars($description) ?></p>
          </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Thought DNA Profile -->
    <?php if ($thoughtProfile && !empty($thoughtProfile['markdown'])): ?>
    <div class="glass-card animate-fadeup" style="margin-bottom: 60px; padding: 20px; border-color: rgba(168,85,247,0.3);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h3 style="margin:0; color:#a855f7;"><i class="fas fa-dna"></i> 思考DNAプロファイル
          <span style="font-size:0.75rem; background:rgba(168,85,247,0.15); padding:2px 8px; border-radius:99px; margin-left:8px;"><?= $quizCount ?>回の診断に基づく</span>
        </h3>
        <a href="thought_quiz.php" class="btn btn-secondary" style="padding:5px 14px; font-size:0.8rem;">
          <i class="fas fa-plus"></i> 追加診断
        </a>
      </div>
      <div style="font-size:0.88rem; color:var(--text-muted); max-height:300px; overflow:hidden; position:relative;">
        <div id="profilePreview" style="line-height:1.7;"><?= nl2br(htmlspecialchars(mb_strimwidth($thoughtProfile['markdown'], 0, 600, '...'))) ?></div>
        <div style="position:absolute; bottom:0; left:0; right:0; height:80px; background:linear-gradient(transparent, var(--bg-card));"></div>
      </div>
      <a href="thought_quiz.php" style="display:block; text-align:center; margin-top:1rem; color:#a855f7; font-size:0.9rem; text-decoration:none;">
        全文を読み・更に診断する →
      </a>
    </div>
    <?php else: ?>
    <div class="glass-card animate-fadeup" style="margin-bottom: 60px; padding: 2rem; text-align:center; border-color: rgba(168,85,247,0.2);">
      <i class="fas fa-dna" style="font-size:2.5rem; color:#a855f7; margin-bottom:1rem;"></i>
      <h3 style="margin-bottom:0.5rem;">思考DNA診断を始める</h3>
      <p class="text-muted" style="margin-bottom:1.5rem; font-size:0.9rem;">10問A/Bクイズに答えるたびに、自分でも気づかなかった思考傾向が分かります。</p>
      <a href="thought_quiz.php" class="btn btn-primary" style="display:inline-block; text-decoration:none;">
        <i class="fas fa-play"></i> 診断を始める
      </a>
    </div>
    <?php endif; ?>

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
              <div class="post-meta" style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                <span><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($post['date']) ?></span>
                <span class="text-teal" style="background: rgba(0, 164, 216, 0.1); padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;"><?= htmlspecialchars($post['category'] ?? 'Uncategorized') ?></span>
                <?php if (($post['status'] ?? '') === '解析中'): ?>
                  <span style="background: rgba(0, 255, 204, 0.2); color: var(--primary-neon); padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;"><i class="fas fa-spinner fa-spin"></i> 解析中...</span>
                <?php elseif (($post['status'] ?? '') === 'エラー'): ?>
                  <span style="background: rgba(255, 68, 68, 0.1); color: var(--warning-red); padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;"><i class="fas fa-exclamation-triangle"></i> 解析エラー</span>
                <?php elseif (($post['status'] ?? '') === '下書き'): ?>
                  <span style="background: rgba(255, 255, 255, 0.1); color: var(--text-muted); padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;">下書き</span>
                <?php elseif (($post['status'] ?? '') === 'My Udastack追加済'): ?>
                  <span style="background: rgba(252, 200, 0, 0.1); color: var(--primary-neon); padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;"><i class="fas fa-check"></i> Stacked</span>
                <?php endif; ?>
              </div>
              <h3 class="post-title" style="min-height: 3em;">
                <a href="view_post.php?index=<?= $i ?>"><?= htmlspecialchars(mb_strimwidth($post['title'], 0, 50, '...')) ?></a>
              </h3>
              
              <?php if (!empty($post['audio_file'])): ?>
                <?php 
                  $af = $post['audio_file'];
                  // Firebase Storage path (audio/uid/...) => go through proxy
                  // Legacy local path (uploads/...) => also go through proxy which handles both
                  $audioSrc = '../audio_proxy.php?path=' . urlencode($af);
                ?>
                <div style="margin-top: 10px; margin-bottom: 10px;">
                  <audio controls style="width: 100%; height: 36px; border-radius: 18px;" src="<?= htmlspecialchars($audioSrc) ?>"></audio>
                </div>
              <?php endif; ?>
              
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

function updateBrain() {
  const btn = document.getElementById('updateBrainBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 分析中(数分かかります)...';
  btn.classList.add('disabled');
  btn.disabled = true;

  fetch('analyze_brain.php')
    .then(res => res.json())
    .then(data => {
      if (data.status === 'async_started') {
        alert("バックグラウンドで分析を開始しました。数分後にリロードすると傾向がアップデートされます！");
        btn.innerHTML = '<i class="fas fa-check"></i> 分析中';
      } else {
        alert(data.message || "エラーが発生しました");
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> 最新データで分析する';
        btn.disabled = false;
      }
    })
    .catch(e => {
        alert("通信エラーが発生しました");
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> 最新データで分析する';
        btn.disabled = false;
    });
}
</script>
</body>
</html>