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
uasort($visiblePosts, function($a, $b) {
    $timeA = strtotime($a['date'] ?? '1970-01-01');
    $timeB = strtotime($b['date'] ?? '1970-01-01');
    if ($timeA === $timeB) {
        // Fallback to ID which contains timestamp 'job_123456_789'
        return strcmp($b['id'] ?? '', $a['id'] ?? ''); 
    }
    return $timeB <=> $timeA;
});
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
  <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
</head>
<body>

  <?php include __DIR__ . '/header.php'; ?>

  <div class="container" style="padding-top: 100px; padding-bottom: 60px;">

    <!-- Profile Section (Compact) -->
    <div class="glass-card animate-fadeup" style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding: 15px 25px;">
      <div style="position: relative;">
        <img src="<?= htmlspecialchars($imagePath) ?>" alt="Profile" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-neon); box-shadow: 0 0 10px rgba(252, 200, 0, 0.2);">
      </div>
      <div style="flex: 1;">
        <h2 style="font-size: 1.3rem; margin-bottom: 5px;"><?= htmlspecialchars($displayName) ?></h2>
        <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; gap: 15px;">
          <span><i class="fas fa-layer-group"></i> Stacked: <strong><?= $stackedCount ?></strong></span>
          <span><i class="fas fa-pen-nib"></i> Posts: <strong><?= count($visiblePosts) ?></strong></span>
        </div>
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

    <!-- Main Actions (SNS Style) -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;" class="animate-fadeup delay-100">
      <a href="../voyager_upload.php" class="btn btn-primary" style="padding: 15px; text-align: center; border-radius: 12px; display: flex; flex-direction: column; gap: 5px;">
        <i class="fas fa-microphone-alt" style="font-size: 1.5rem;"></i>
        <span style="font-size: 0.9rem;">Record Voice</span>
      </a>
      <a href="timeline.php" class="btn btn-secondary" style="padding: 15px; text-align: center; border-radius: 12px; display: flex; flex-direction: column; gap: 5px; border-color: #f59e0b; color: #f59e0b;">
        <i class="fas fa-hourglass-half" style="font-size: 1.5rem;"></i>
        <span style="font-size: 0.9rem;">Timeline</span>
      </a>
    </div>

    <!-- Secondary Links -->
    <div style="display: flex; gap: 10px; margin-bottom: 40px; flex-wrap: wrap; justify-content: center;" class="animate-fadeup delay-100">
      <a href="line_setup.php" class="user-badge" style="text-decoration: none; border-color: #06C755; color: #06C755; background: rgba(6, 199, 85, 0.1);"><i class="fab fa-line"></i> LINEアップロード</a>
      <a href="my_udastack.php" class="user-badge" style="text-decoration: none; border-color: var(--accent-teal); color: var(--accent-teal); background: rgba(0, 164, 216, 0.1);"><i class="fas fa-layer-group"></i> My Udastack</a>
      <a href="thought_quiz.php" class="user-badge" style="text-decoration: none; border-color: #a855f7; color: #a855f7; background: rgba(168, 85, 247, 0.1);"><i class="fas fa-sliders-h"></i> 思考チューニング</a>
      <a href="javascript:void(0)" onclick="checkPostLimit()" class="user-badge" style="text-decoration: none; border-color: var(--text-muted); color: var(--text-gray);"><i class="fas fa-keyboard"></i> 手入力作成</a>
    </div>

    <!-- Thought Core -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 15px;" class="animate-fadeup delay-100">
      <h2 class="section-title" style="font-size: 1.4rem; margin-bottom: 0;"><i class="fas fa-brain" style="color: var(--primary-neon);"></i> Thought Core</h2>
      <button onclick="updateBrain()" class="btn btn-secondary" style="padding: 5px 15px; font-size: 0.8rem; height: fit-content;" id="updateBrainBtn">
        <i class="fas fa-sync-alt"></i> 分析を更新
      </button>
    </div>

    <div class="glass-card animate-fadeup delay-100" style="margin-bottom: 40px; padding: 20px;">
      <?php if (empty($thoughtDict)): ?>
        <p class="text-muted" style="text-align: center; margin: 20px 0;">まだ脳内分析データがありません。「分析を更新」ボタンを押すとAIがあなたの傾向を分析します。</p>
      <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
        <?php foreach ($thoughtDict as $keyword => $description): ?>
          <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 15px;">
            <h4 style="color: var(--primary-neon); margin-bottom: 5px; font-size: 1rem;">#<?= htmlspecialchars($keyword) ?></h4>
            <p style="color: var(--text-white); font-size: 0.85rem; line-height: 1.5; margin: 0;"><?= htmlspecialchars($description) ?></p>
          </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
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
                <?php if (!empty($post['is_shared'])): ?>
                  <span style="background: rgba(168, 85, 247, 0.1); color: #a855f7; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;"><i class="fas fa-share-alt"></i> Shared</span>
                <?php endif; ?>
              </div>
              <h3 class="post-title" style="min-height: auto; margin-bottom: 10px;">
                <a href="view_post.php?index=<?= $i ?>"><?= htmlspecialchars(mb_strimwidth($post['title'], 0, 50, '...')) ?></a>
              </h3>
              
              <?php if (!empty($post['summary'])): ?>
                <div class="post-summary" style="margin: 10px 0; font-size: 0.85rem; color: var(--text-white); line-height: 1.6; background: rgba(255,255,255,0.05); padding: 12px; border-radius: 8px; border-left: 3px solid var(--primary-neon);">
                  <?= nl2br(htmlspecialchars($post['summary'])) ?>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($post['audio_file'])): ?>
                <?php 
                  $af = $post['audio_file'];
                  // Firebase Storage path (audio/uid/...) => go through proxy
                  // Legacy local path (uploads/...) => also go through proxy which handles both
                  $audioSrc = '../audio_proxy.php?target_uid=' . urlencode($uid) . '&path=' . urlencode($af);
                ?>
                <div style="margin-top: 10px; margin-bottom: 10px;">
                  <audio controls style="width: 100%; height: 36px; border-radius: 18px;" src="<?= htmlspecialchars($audioSrc) ?>"></audio>
                </div>
              <?php endif; ?>
              
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 20px;">
                <a href="edit_post.php?index=<?= $i ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem; flex: 1;">
                  <i class="fas fa-edit"></i> Edit
                </a>
                <form method="POST" action="submit_post.php" style="flex: 1; display: flex;" onsubmit="return confirm('本当にこの投稿を削除しますか？');">
                  <input type="hidden" name="index" value="<?= $i ?>">
                  <input type="hidden" name="action" value="delete">
                  <button type="submit" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem; width: 100%; color: var(--warning-red); border-color: var(--warning-red);">
                    <i class="fas fa-trash"></i> Delete
                  </button>
                </form>
                
                <!-- Actions -->
                <?php if (($post['status'] ?? '') === 'エラー'): ?>
                  <form method="POST" action="retry_analysis.php" style="flex: 1; display: flex;">
                    <input type="hidden" name="index" value="<?= $i ?>">
                    <button type="submit" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem; width: 100%; background: var(--warning-red); color: white;">
                      <i class="fas fa-redo"></i> 再試行
                    </button>
                  </form>
                <?php elseif ($post['status'] === 'My Udastack追加済'): ?>
                  <form method="POST" action="submit_post.php" style="flex: 1; display: flex;">
                    <input type="hidden" name="index" value="<?= $i ?>">
                    <input type="hidden" name="action" value="unstack">
                    <button type="submit" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem; flex: 1; border-color: var(--primary-neon); background: rgba(252, 200, 0, 0.1); color: var(--primary-neon);">
                      <i class="fas fa-check"></i> Stacked
                    </button>
                  </form>
                <?php else: ?>
                  <form method="POST" action="submit_post.php" style="flex: 1; display: flex;">
                    <input type="hidden" name="index" value="<?= $i ?>">
                    <input type="hidden" name="action" value="stack">
                    <button type="submit" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem; width: 100%;">
                      <i class="fas fa-plus"></i> Stack
                    </button>
                  </form>
                <?php endif; ?>
                
                <!-- Share Button -->
                <?php if (!empty($post['is_shared'])): ?>
                  <form id="unshareForm<?= $i ?>" method="POST" action="submit_post.php" style="flex: 1; display: flex;">
                    <input type="hidden" name="index" value="<?= $i ?>">
                    <input type="hidden" name="action" value="unshare">
                    <button type="button" onclick="confirmUnshare(<?= $i ?>)" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem; width: 100%; border-color: #a855f7; color: #a855f7;">
                      <i class="fas fa-share-alt"></i> Shared
                    </button>
                  </form>
                <?php else: ?>
                  <form id="shareForm<?= $i ?>" method="POST" action="submit_post.php" style="flex: 1; display: flex;">
                    <input type="hidden" name="index" value="<?= $i ?>">
                    <input type="hidden" name="action" value="share">
                    <button type="button" onclick="confirmShare(<?= $i ?>)" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem; width: 100%;">
                      <i class="fas fa-share-alt"></i> Share
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

function confirmShare(index) {
  if (confirm("相互フォローのタイムラインにこの投稿の音声と文字起こしを共有します。\n\n※共有すると相手にLINE通知が届きます。よろしいですか？")) {
    document.getElementById('shareForm' + index).submit();
  }
}

function confirmUnshare(index) {
  if (confirm("相互フォローのタイムラインからこの投稿を非表示にしますか？")) {
    document.getElementById('unshareForm' + index).submit();
  }
}
</script>
</body>
</html>