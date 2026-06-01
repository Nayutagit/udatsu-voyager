<?php
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../plan_limits.php';

if (empty($uid) || empty($userName) || empty($userPlan)) {
  header('Location: /index.php'); // ルートへリダイレクト
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

$profile = ["display_name" => $userName, "title" => '', "bio" => '', "image" => '', "network_id" => ''];
if (file_exists($profileFile)) {
  $profile = array_merge($profile, json_decode(file_get_contents($profileFile), true) ?: []);
}
$displayName = $profile['display_name'] ?? $userName;
$networkId   = $profile['network_id'] ?? '';
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

$visiblePosts = array_filter($posts, fn($p) => ($p['status'] ?? '') !== '削除済');
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
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px; flex-wrap: wrap;">
          <h2 style="font-size: 1.3rem; margin: 0;"><?= htmlspecialchars($displayName) ?></h2>
          <?php if (!empty($networkId)): ?>
            <span style="background: rgba(252,200,0,0.1); border: 1px solid rgba(252,200,0,0.4); color: var(--primary-neon); padding: 2px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.05em;">@ <?= htmlspecialchars($networkId) ?></span>
          <?php endif; ?>
        </div>
        <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; gap: 15px;">
          <span><i class="fas fa-layer-group"></i> Stacked: <strong><?= $stackedCount ?></strong></span>
          <span><i class="fas fa-pen-nib"></i> Posts: <strong><?= count($visiblePosts) ?></strong></span>
        </div>
      </div>
      <a href="edit_profile.php" style="color: var(--text-muted); font-size: 0.8rem; white-space: nowrap;"><i class="fas fa-cog"></i></a>
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
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;" class="animate-fadeup delay-100">
      <h2 class="section-title" style="font-size: 1.5rem; margin: 0; color: #fff;">Recent Posts</h2>
      <button onclick="location.reload()" class="btn btn-secondary" style="padding: 6px 16px; font-size: 0.8rem;">
        <i class="fas fa-sync-alt"></i> 更新
      </button>
    </div>
    
    <style>
      .dashboard-grid {
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)) !important;
        gap: 20px !important;
      }
      .post-card {
        transition: transform 0.4s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 0.3s ease, border-color 0.3s ease !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25) !important;
        background: rgba(10, 10, 10, 0.75) !important;
      }
      .post-card:hover {
        transform: translateY(-5px) scale(1.01) !important;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35) !important;
        border-color: var(--accent-teal) !important;
      }
      .post-card.moving-active {
        border-color: var(--primary-neon) !important;
        box-shadow: 0 0 15px rgba(252, 200, 0, 0.3) !important;
      }
      .post-content {
        padding: 16px !important;
      }
      .post-eyecatch {
        margin: -16px -16px 12px -16px !important;
        height: 140px !important;
      }
      .post-title {
        margin-bottom: 8px !important;
      }
      .post-title a {
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        padding-right: 25px;
        display: block;
        font-size: 1.05rem !important;
        font-weight: bold;
        color: var(--text-white);
      }
      .post-title a:hover {
        color: var(--primary-neon);
        text-decoration: underline;
      }
      /* Compact buttons */
      .post-card .btn {
        padding: 6px 12px !important;
        font-size: 0.75rem !important;
        border-radius: 8px !important;
        gap: 6px !important;
      }
    </style>
    
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
          <div class="post-card" id="post-card-<?= $i ?>" data-id="<?= htmlspecialchars($post['id'] ?? '') ?>">
            <div class="post-content">
              <div class="post-meta" style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;" id="post-meta-<?= $i ?>">
                <span class="sort-actions" style="display: inline-flex; gap: 8px; align-items: center; margin-right: 5px; user-select: none; -webkit-user-select: none;">
                  <a href="javascript:void(0)" onclick="movePost(<?= $i ?>, 'up')" style="color: var(--text-muted); font-size: 0.9rem; text-decoration: none; padding: 2px;" title="上に移動" onmouseover="this.style.color='var(--primary-neon)'" onmouseout="this.style.color='var(--text-muted)'">
                    <i class="fas fa-chevron-up"></i>
                  </a>
                  <a href="javascript:void(0)" onclick="movePost(<?= $i ?>, 'down')" style="color: var(--text-muted); font-size: 0.9rem; text-decoration: none; padding: 2px;" title="下に移動" onmouseover="this.style.color='var(--primary-neon)'" onmouseout="this.style.color='var(--text-muted)'">
                    <i class="fas fa-chevron-down"></i>
                  </a>
                </span>
                <span style="display: flex; align-items: center; gap: 5px;">
                  <i class="far fa-calendar-alt"></i> 
                  <span id="date-text-<?= $i ?>" onclick="editPostDate(<?= $i ?>, '<?= htmlspecialchars($post['date']) ?>')" style="cursor: pointer; border-bottom: 1px dashed rgba(255,255,255,0.3);">
                    <?= htmlspecialchars($post['date']) ?>
                  </span>
                </span>
                <?php
                  $postStatus = $post['status'] ?? '';
                  $postTitle = $post['title'] ?? '';
                  $isRawTitle = preg_match('/^新規録音/', $postTitle) || $postTitle === '🎙';
                  $isFailed = ($postStatus === 'エラー' || strpos($postTitle, '解析エラー') !== false || $isRawTitle || (empty($post['summary']) && empty($post['text']) && (!empty($post['audio_file']) || !empty($post['audio'])) && $postStatus !== '解析中'));
                ?>
                <?php if ($postStatus === '解析中'): ?>
                  <span style="background: rgba(0, 255, 204, 0.2); color: var(--primary-neon); padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;"><i class="fas fa-spinner fa-spin"></i> 解析中...</span>
                <?php elseif ($postStatus === 'エラー' || strpos($postTitle, '解析エラー') !== false || $isRawTitle): ?>
                  <span id="retry-badge-<?= $i ?>" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; cursor: pointer; border: 1px solid rgba(245, 158, 11, 0.3);" onclick="handlePostAction(<?= $i ?>, 'retry')">
                    <i class="fas fa-redo"></i> <?= $isRawTitle && $postStatus !== 'エラー' ? '🔄 AI解析する' : '🔄 AI解析を再試行' ?>
                  </span>
                <?php elseif ($postStatus === 'My Udastack追加済'): ?>
                  <span id="stack-badge-<?= $i ?>" style="background: rgba(252, 200, 0, 0.1); color: var(--primary-neon); padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;"><i class="fas fa-check"></i> Stacked</span>
                <?php endif; ?>
                <?php if (!empty($post['is_shared'])): ?>
                  <span style="background: rgba(168, 85, 247, 0.1); color: #a855f7; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;"><i class="fas fa-share-alt"></i> Shared</span>
                <?php endif; ?>
              </div>
              <?php if (!empty($post['thumbnail'])): ?>
                <div class="post-eyecatch" style="margin: -15px -15px 15px -15px; overflow: hidden; border-radius: 12px 12px 0 0; height: 180px;">
                  <img src="../<?= htmlspecialchars($post['thumbnail']) ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                </div>
              <?php endif; ?>

              <h3 class="post-title" style="min-height: auto; margin-bottom: 10px;">
                <a href="view_post.php?index=<?= $i ?>"><?= htmlspecialchars(mb_strimwidth($post['title'], 0, 50, '...')) ?></a>
              </h3>
              
              <?php if (!empty($post['original_title'])): ?>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 10px; display: flex; align-items: center; gap: 5px;">
                  <i class="fas fa-file-audio"></i> 元ファイル名: <span><?= htmlspecialchars(mb_strimwidth($post['original_title'], 0, 50, '...')) ?></span>
                </div>
              <?php endif; ?>
              
              <?php
                $hasSummary = !empty($post['summary']) && $post['summary'] !== '(要約解析失敗)';
                $hasTranscription = !empty($post['original_text']) || (!empty($post['text']) && strlen($post['text'] ?? '') > 50);
                $hasAnyText = $hasSummary || (!empty($post['text']) && $postStatus !== '解析中') || $hasTranscription;
              ?>
              <?php if ($hasAnyText): ?>
                <div id="summary-wrapper-<?= $i ?>" style="display: none;">
                  <?php if ($hasSummary): ?>
                    <div id="summary-box-<?= $i ?>" class="post-summary" style="margin: 10px 0; font-size: 0.85rem; color: var(--text-white); line-height: 1.6; background: rgba(255,255,255,0.05); padding: 12px; border-radius: 8px; border-left: 3px solid var(--primary-neon);">
                      <?= nl2br(htmlspecialchars($post['summary'])) ?>
                    </div>
                  <?php elseif (!empty($post['text']) && $postStatus !== '解析中'): ?>
                    <div id="summary-box-<?= $i ?>" class="post-summary" style="margin: 10px 0; font-size: 0.85rem; color: var(--text-muted); line-height: 1.6; background: rgba(255,255,255,0.02); padding: 12px; border-radius: 8px;">
                      <?= nl2br(htmlspecialchars(mb_strimwidth(strip_tags($post['text']), 0, 160, '...'))) ?>
                      <div style="margin-top: 8px;">
                        <button type="button" onclick="regenSummary(<?= $i ?>)" class="btn btn-secondary" style="padding: 3px 10px; font-size: 0.7rem; border-color: #f59e0b; color: #f59e0b;">
                          <i class="fas fa-magic"></i> 要約を生成
                        </button>
                      </div>
                    </div>
                  <?php elseif ($hasTranscription): ?>
                    <div id="summary-box-<?= $i ?>" style="margin: 10px 0;">
                      <button type="button" onclick="regenSummary(<?= $i ?>)" class="btn btn-secondary" style="padding: 5px 14px; font-size: 0.8rem; border-color: #f59e0b; color: #f59e0b;">
                        <i class="fas fa-magic"></i> 要約を生成する
                      </button>
                    </div>
                  <?php endif; ?>
                </div>
                <div style="margin: 8px 0;">
                  <a href="javascript:void(0)" onclick="toggleSummary(<?= $i ?>)" id="summary-toggle-<?= $i ?>" style="font-size: 0.85rem; color: var(--accent-teal); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-weight: 500;">
                    <i class="fas fa-chevron-down" style="font-size: 0.75rem;"></i> 続きを読む
                  </a>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($post['audio_file'])): ?>
                <?php 
                  $af = $post['audio_file'];
                  $audioSrc = '../audio_proxy.php?target_uid=' . urlencode($uid) . '&path=' . urlencode($af);
                ?>
                <div style="margin-top: 10px; margin-bottom: 10px;">
                  <audio controls style="width: 100%; height: 36px; border-radius: 18px;" src="<?= htmlspecialchars($audioSrc) ?>"></audio>
                </div>
              <?php endif; ?>
              
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 20px;" id="post-actions-<?= $i ?>">
                <a href="edit_post.php?index=<?= $i ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem; flex: 1;">
                  <i class="fas fa-edit"></i> Edit
                </a>
                
                <button type="button" onclick="handlePostAction(<?= $i ?>, 'delete')" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem; width: 100%; color: var(--warning-red); border-color: var(--warning-red);">
                  <i class="fas fa-trash"></i> Delete
                </button>
                
                <?php if ($isFailed): ?>
                  <div id="retry-btn-container-<?= $i ?>" style="grid-column: span 2; display: flex; margin-top: 5px;">
                    <button type="button" onclick="handlePostAction(<?= $i ?>, 'retry')" class="btn btn-primary" style="padding: 8px 15px; font-size: 0.85rem; width: 100%; background: #ffda44; color: #000; font-weight: bold; border-radius: 8px; border: none; box-shadow: 0 0 10px rgba(255, 218, 68, 0.4); cursor: pointer; transition: 0.3s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                      <i class="fas fa-redo"></i> AI解析を再試行する
                    </button>
                  </div>
                <?php else: ?>
                  <div id="stack-btn-container-<?= $i ?>" style="display: flex;">
                    <?php if (($post['status'] ?? '') === 'My Udastack追加済'): ?>
                      <button type="button" onclick="handlePostAction(<?= $i ?>, 'unstack')" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem; flex: 1; border-color: var(--primary-neon); background: rgba(252, 200, 0, 0.1); color: var(--primary-neon);">
                        <i class="fas fa-check"></i> Stacked
                      </button>
                    <?php else: ?>
                      <button type="button" onclick="handlePostAction(<?= $i ?>, 'stack')" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem; width: 100%;">
                        <i class="fas fa-plus"></i> Stack
                      </button>
                    <?php endif; ?>
                  </div>
                  
                  <div id="share-btn-container-<?= $i ?>" style="display: flex;">
                    <?php if (!empty($post['is_shared'])): ?>
                      <button type="button" onclick="handlePostAction(<?= $i ?>, 'unshare')" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem; width: 100%; border-color: #a855f7; color: #a855f7;">
                        <i class="fas fa-share-alt"></i> Shared
                      </button>
                    <?php else: ?>
                      <button type="button" onclick="handlePostAction(<?= $i ?>, 'share')" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem; width: 100%;">
                        <i class="fas fa-share-alt"></i> Share
                      </button>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($post['audio_file']) || !empty($post['audio'])): ?>
                    <div id="retry-btn-container-<?= $i ?>" style="grid-column: span 2; display: flex; margin-top: 6px;">
                      <button type="button" onclick="handlePostAction(<?= $i ?>, 'retry')" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.75rem; width: 100%; border-color: rgba(245,158,11,0.4); color: #f59e0b; background: rgba(245,158,11,0.05); border-radius: 8px; transition: 0.3s;" onmouseover="this.style.background='rgba(245,158,11,0.12)'" onmouseout="this.style.background='rgba(245,158,11,0.05)'">
                        <i class="fas fa-redo"></i> 音声から再解析する
                      </button>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Thought Core -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 15px; margin-top: 60px;" class="animate-fadeup delay-100">
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

function editPostDate(index, currentDate) {
  const newDate = prompt("収録日を編集 (YYYY-MM-DD)", currentDate);
  if (newDate === null || newDate === currentDate) return;
  
  if (!/^\d{4}-\d{2}-\d{2}$/.test(newDate)) {
    alert("形式が正しくありません (YYYY-MM-DD)");
    return;
  }

  const formData = new FormData();
  formData.append('index', index);
  formData.append('date', newDate);
  formData.append('action', 'update_date');

  fetch('submit_post.php', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'ok' || data.status === 'success') {
      document.getElementById('date-text-' + index).textContent = newDate;
    } else {
      alert(data.message || "更新に失敗しました");
    }
  })
  .catch(() => alert("通信エラーが発生しました"));
}
function handlePostAction(index, action) {
  if (action === 'delete') {
    if (!confirm('本当にこの投稿を削除しますか？')) return;
  }
  if (action === 'share') {
    if (!confirm('タイムラインにこの投稿を共有しますか？')) return;
  }
  if (action === 'unshare') {
    if (!confirm('タイムラインからこの投稿を非表示にしますか？')) return;
  }

  const btn = event.currentTarget;
  const originalContent = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  btn.disabled = true;

  const formData = new FormData();
  formData.append('index', index);
  formData.append('action', action);

  const endpoint = (action === 'retry') ? 'retry_analysis.php' : 'submit_post.php';

  fetch(endpoint, {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'ok' || data.status === 'success' || data.status === 'async_started') {
      if (action === 'delete') {
        const card = document.getElementById('post-card-' + index);
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.9)';
        setTimeout(() => card.remove(), 500);
      } else if (action === 'stack' || action === 'unstack') {
        const container = document.getElementById('stack-btn-container-' + index);
        const meta = document.getElementById('post-meta-' + index);
        
        if (action === 'stack') {
          container.innerHTML = `<button type="button" onclick="handlePostAction(${index}, 'unstack')" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem; flex: 1; border-color: var(--primary-neon); background: rgba(252, 200, 0, 0.1); color: var(--primary-neon);"><i class="fas fa-check"></i> Stacked</button>`;
          // Remove any existing badge before adding a new one
          const existingBadge = document.getElementById('stack-badge-' + index);
          if (existingBadge) existingBadge.remove();
          const badge = document.createElement('span');
          badge.id = 'stack-badge-' + index;
          badge.style.cssText = 'background: rgba(252, 200, 0, 0.1); color: var(--primary-neon); padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;';
          badge.innerHTML = '<i class="fas fa-check"></i> Stacked';
          meta.appendChild(badge);
          alert("完了しました！My Udastackに追加しました。");
        } else {
          container.innerHTML = `<button type="button" onclick="handlePostAction(${index}, 'stack')" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem; width: 100%;"><i class="fas fa-plus"></i> Stack</button>`;
          const badge = document.getElementById('stack-badge-' + index);
          if (badge) badge.remove();
        }
      } else if (action === 'share' || action === 'unshare') {
        const container = document.getElementById('share-btn-container-' + index);
        const meta = document.getElementById('post-meta-' + index);
        
        if (action === 'share') {
          container.innerHTML = `<button type="button" onclick="handlePostAction(${index}, 'unshare')" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem; width: 100%; border-color: #a855f7; color: #a855f7;"><i class="fas fa-share-alt"></i> Shared</button>`;
          // Remove any existing badge before adding a new one
          const existingShareBadge = document.getElementById('share-badge-' + index);
          if (existingShareBadge) existingShareBadge.remove();
          const badge = document.createElement('span');
          badge.id = 'share-badge-' + index;
          badge.style.cssText = 'background: rgba(168, 85, 247, 0.1); color: #a855f7; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;';
          badge.innerHTML = '<i class="fas fa-share-alt"></i> Shared';
          meta.appendChild(badge);
        } else {
          container.innerHTML = `<button type="button" onclick="handlePostAction(${index}, 'share')" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem; width: 100%;"><i class="fas fa-share-alt"></i> Share</button>`;
          const badge = document.getElementById('share-badge-' + index);
          if (badge) badge.remove();
        }
      } else if (action === 'retry') {
        const meta = document.getElementById('post-meta-' + index);
        // Find the retry badge and update it
        const retryBadge = meta ? meta.querySelector('[onclick*="retry"]') : null;
        if (retryBadge) {
          retryBadge.style.background = 'rgba(0, 255, 204, 0.2)';
          retryBadge.style.color = 'var(--primary-neon)';
          retryBadge.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 解析中...';
          retryBadge.onclick = null;
        }
        
        // Also update the big Retry button
        const retryBtn = document.querySelector(`#retry-btn-container-${index} button`);
        if (retryBtn) {
          retryBtn.style.background = 'rgba(0, 255, 204, 0.2)';
          retryBtn.style.color = 'var(--primary-neon)';
          retryBtn.style.boxShadow = 'none';
          retryBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 解析を開始しました...';
          retryBtn.disabled = true;
        }
        alert("再試行を開始しました。数分後に自動で反映されます。");
        startPolling();
      }
    } else {
      alert(data.message || 'エラーが発生しました');
      btn.innerHTML = originalContent;
      btn.disabled = false;
    }
  })
  .catch(e => {
    console.error(e);
    alert('通信エラーが発生しました');
    btn.innerHTML = originalContent;
    btn.disabled = false;
  });
}

function regenSummary(index) {
  const box = document.getElementById('summary-box-' + index);
  const originalContent = box ? box.innerHTML : '';
  if (box) {
    box.innerHTML = '<span style="color: var(--text-muted); font-size: 0.8rem;"><i class="fas fa-spinner fa-spin"></i> AIが要約を生成中...</span>';
  }

  const formData = new FormData();
  formData.append('index', index);

  fetch('regen_post_summary.php', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'ok' && data.summary) {
      if (box) {
        box.className = 'post-summary';
        box.style.cssText = 'margin: 10px 0; font-size: 0.85rem; color: var(--text-white); line-height: 1.6; background: rgba(255,255,255,0.05); padding: 12px; border-radius: 8px; border-left: 3px solid var(--primary-neon);';
        box.innerHTML = data.summary.replace(/\n/g, '<br>');
      }
    } else {
      alert(data.message || 'エラーが発生しました');
      if (box) box.innerHTML = originalContent;
    }
  })
  .catch(e => {
    console.error(e);
    alert('通信エラーが発生しました');
    if (box) box.innerHTML = originalContent;
  });
}

function toggleSummary(index) {
  const wrapper = document.getElementById('summary-wrapper-' + index);
  const toggle = document.getElementById('summary-toggle-' + index);
  if (!wrapper || !toggle) return;
  if (wrapper.style.display === 'none') {
    wrapper.style.display = 'block';
    toggle.innerHTML = '<i class="fas fa-chevron-up" style="font-size: 0.75rem;"></i> 閉じる';
  } else {
    wrapper.style.display = 'none';
    toggle.innerHTML = '<i class="fas fa-chevron-down" style="font-size: 0.75rem;"></i> 続きを読む';
  }
}

function movePost(cardId, direction) {
  const card = document.getElementById('post-card-' + cardId);
  if (!card) return;
  
  const parent = card.parentNode;
  let target = null;
  if (direction === 'up') {
    target = card.previousElementSibling;
  } else if (direction === 'down') {
    target = card.nextElementSibling;
  }
  
  if (target && target.classList.contains('post-card')) {
    const rectCardStart = card.getBoundingClientRect();
    const rectTargetStart = target.getBoundingClientRect();
    
    if (direction === 'up') {
      parent.insertBefore(card, target);
    } else {
      parent.insertBefore(target, card);
    }
    
    const rectCardEnd = card.getBoundingClientRect();
    const rectTargetEnd = target.getBoundingClientRect();
    
    const deltaCardX = rectCardStart.left - rectCardEnd.left;
    const deltaCardY = rectCardStart.top - rectCardEnd.top;
    const deltaTargetX = rectTargetStart.left - rectTargetEnd.left;
    const deltaTargetY = rectTargetStart.top - rectTargetEnd.top;
    
    card.style.transition = 'none';
    card.style.transform = `translate(${deltaCardX}px, ${deltaCardY}px)`;
    target.style.transition = 'none';
    target.style.transform = `translate(${deltaTargetX}px, ${deltaTargetY}px)`;
    
    card.offsetHeight; // Force reflow
    
    card.style.transition = 'transform 0.4s cubic-bezier(0.2, 0.8, 0.2, 1)';
    card.style.transform = 'translate(0, 0)';
    target.style.transition = 'transform 0.4s cubic-bezier(0.2, 0.8, 0.2, 1)';
    target.style.transform = 'translate(0, 0)';
    
    card.classList.add('moving-active');
    
    setTimeout(() => {
      card.style.transition = '';
      card.style.transform = '';
      target.style.transition = '';
      target.style.transform = '';
      card.classList.remove('moving-active');
    }, 400);
    
    saveNewOrder();
  }
}

function saveNewOrder() {
  const postIds = Array.from(document.querySelectorAll('.post-card')).map(card => card.getAttribute('data-id'));
  
  const formData = new FormData();
  formData.append('order', JSON.stringify(postIds));
  
  fetch('save_order_ajax.php', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(response => response.json())
  .then(data => {
    if (data.status === 'ok') {
      console.log('Order saved successfully');
    } else {
      console.error('Failed to save order:', data.message);
    }
  })
  .catch(err => {
    console.error('Error saving order:', err);
  });
}

let pollInterval = null;
function startPolling() {
  if (pollInterval) return;
  pollInterval = setInterval(function() {
    fetch('poll_status.php')
      .then(r => r.json())
      .then(d => { if (!d.has_processing) { clearInterval(pollInterval); location.reload(); } })
      .catch(() => {});
  }, 5000);
}

// Auto-poll: 解析中の投稿があれば5秒ごとに確認
const hasProcessing = <?php echo (isset($posts) && count(array_filter($posts, fn($p) => ($p['status'] ?? '') === '解析中')) > 0) ? 'true' : 'false'; ?>;
if (hasProcessing) {
  startPolling();
}
</script>
</body>
</html>