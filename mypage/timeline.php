<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (empty($uid)) {
    header("Location: ../index.php");
    exit();
}

$userDir = __DIR__ . '/../users/';

function getList($filePath) {
    if (!file_exists($filePath)) return [];
    $data = json_decode(file_get_contents($filePath), true);
    return is_array($data) ? $data : [];
}

function getUserProfile($targetUid, $userDir) {
    $profileFile = $userDir . $targetUid . '_profile.json';
    $profile = ["display_name" => 'Unknown User', "title" => '', "bio" => '', "image" => ''];
    if (file_exists($profileFile)) {
        $profile = array_merge($profile, json_decode(file_get_contents($profileFile), true) ?: []);
    }
    return $profile;
}

$myFollowing = getList($userDir . $uid . '_following.json');
$myFollowers = getList($userDir . $uid . '_followers.json');

// profiles cache to speed up loading
$profilesCache = [];

// Collect shared posts
$timelinePosts = [];

// 1. Collect from mutual followers
foreach ($myFollowing as $followUid) {
    if (!in_array($followUid, $myFollowers)) {
        continue; // Only mutual followers
    }

    $postsFile = $userDir . $followUid . '_posts.json';
    if (file_exists($postsFile)) {
        $followPosts = json_decode(file_get_contents($postsFile), true) ?: [];
        
        if (!isset($profilesCache[$followUid])) {
            $profilesCache[$followUid] = getUserProfile($followUid, $userDir);
        }
        $profile = $profilesCache[$followUid];
        
        foreach ($followPosts as $idx => $post) {
            $status = $post['status'] ?? '';
            $isShared = !empty($post['is_shared']);
            
            // Allow 'エラー' only for Admin
            $allowedStatus = ['My Udastack追加済', '', 'Inbox'];
            if ($userPlan === 'admin') {
                $allowedStatus[] = 'エラー';
            }
            
            if ($isShared && (in_array($status, $allowedStatus) || $status === '')) {
                $post['author_uid'] = $followUid;
                $post['author_profile'] = $profile;
                $post['original_index'] = $idx;
                $timelinePosts[] = $post;
            }
        }
    }
}

// 2. Include my own posts
$myPostsFile = $userDir . $uid . '_posts.json';
if (file_exists($myPostsFile)) {
    $myPosts = json_decode(file_get_contents($myPostsFile), true) ?: [];
    if (!isset($profilesCache[$uid])) {
        $profilesCache[$uid] = getUserProfile($uid, $userDir);
    }
    $myProfile = $profilesCache[$uid];
    
    foreach ($myPosts as $idx => $post) {
        $status = $post['status'] ?? '';
        $allowedStatus = ['My Udastack追加済', '', 'Inbox'];
        if ($userPlan === 'admin') $allowedStatus[] = 'エラー';

        if (!empty($post['is_shared']) && (in_array($status, $allowedStatus) || $status === '')) {
            $post['author_uid'] = $uid;
            $post['author_profile'] = $myProfile;
            $post['original_index'] = $idx;
            $timelinePosts[] = $post;
        }
    }
}

// 3. Sort by date descending
usort($timelinePosts, function($a, $b) {
    $timeA = strtotime($a['date'] ?? '1970-01-01');
    $timeB = strtotime($b['date'] ?? '1970-01-01');
    if ($timeA === $timeB) {
        $idA = (int)preg_replace('/[^0-9]/', '', $a['id'] ?? '0');
        $idB = (int)preg_replace('/[^0-9]/', '', $b['id'] ?? '0');
        return $idB <=> $idA;
    }
    return $timeB <=> $timeA;
});

// 4. Limit to latest 50 to improve loading speed
$timelinePosts = array_slice($timelinePosts, 0, 50);

?>
<!DOCTYPE html>
<html lang="ja" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Udatsu | タイムライン</title>
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
</head>
<body>

<!-- ============================================================
     APP HEADER
     ============================================================ -->
<?php include __DIR__ . '/header.php'; ?>

<!-- ============================================================
     MAIN FEED (TIMELINE)
     ============================================================ -->
<main class="app-main">
  <div class="feed" id="feed">
    
    <div style="padding: 0 15px; margin-bottom: 20px;">
      <h2 style="font-size: 1.2rem; margin-bottom: 5px; color: var(--text-primary);"><i class="fas fa-compass" style="color: var(--primary-neon);"></i> タイムライン</h2>
      <p style="font-size: 0.85rem; color: var(--text-secondary);">フォロー中のユーザーの投稿</p>
    </div>

    <?php if (empty($timelinePosts)): ?>
    <div class="empty-state">
      <i class="fas fa-users-slash"></i>
      <h3>タイムラインはまだ空です</h3>
      <p>フォローしているユーザーが投稿をシェアするとここに表示されます。</p>
      <a href="network.php" class="btn btn-primary btn-sm" style="margin-top: 15px;">
        <i class="fas fa-search"></i> ユーザーを探す
      </a>
    </div>
    <?php else: ?>
    <?php foreach ($timelinePosts as $post): ?>
    <?php
      $authorUid = $post['author_uid'];
      $authorName = $post['author_profile']['display_name'];
      $authorTitle = $post['author_profile']['title'];
      $authorImg = !empty($post['author_profile']['image']) ? '../uploads/' . $authorUid . '/' . $post['author_profile']['image'] : '../img/default-icon.png';
      
      $postId     = $post['id'] ?? uniqid();
      $postTitle  = $post['title'] ?? '';
      $postDate   = $post['date'] ?? '';
      $postText   = $post['text'] ?? '';
      $postSummary= $post['summary'] ?? '';
      
      $af         = $post['audio_file'] ?? $post['audio'] ?? '';
      $audioSrc   = !empty($af) ? '../audio_proxy.php?target_uid=' . urlencode($authorUid) . '&path=' . urlencode($af) : '';
      
      $likes      = $post['likes'] ?? [];
      $likeCount  = count($likes);
      $isLiked    = in_array($uid, $likes);
      $commentCount = $post['comment_count'] ?? 0;
      
      $displayCaption = $postSummary ?: mb_strimwidth(strip_tags($postText), 0, 120, '…');
      $hasFullText = !empty($postText) && mb_strlen(strip_tags($postText)) > 50;
    ?>
    <article class="post-card" id="post-card-<?= htmlspecialchars($postId) ?>">

      <!-- Post header -->
      <div class="post-header">
        <a href="profile.php?uid=<?= urlencode($authorUid) ?>">
            <img src="<?= htmlspecialchars($authorImg) ?>" alt="<?= htmlspecialchars($authorName) ?>" class="post-avatar">
        </a>
        <div class="post-header__info">
          <div class="post-username">
              <a href="profile.php?uid=<?= urlencode($authorUid) ?>" style="color:inherit; text-decoration:none;">
                  <?= htmlspecialchars($authorName) ?>
              </a>
          </div>
          <div class="post-date"><?= htmlspecialchars($postDate) ?> <?= !empty($authorTitle) ? '• ' . htmlspecialchars($authorTitle) : '' ?></div>
        </div>
      </div>

      <!-- Title / Summary -->
      <?php if (!empty($displayCaption)): ?>
      <div class="post-caption collapsed" id="caption-<?= htmlspecialchars($postId) ?>">
        <strong><?= htmlspecialchars($postTitle) ?></strong><br>
        <?= nl2br(htmlspecialchars($displayCaption)) ?>
      </div>
      <?php if ($hasFullText): ?>
      <button class="post-read-more" id="readmore-<?= htmlspecialchars($postId) ?>"
              onclick="expandPost('<?= htmlspecialchars($postId) ?>')">
        続きを読む
      </button>
      <?php endif; ?>

      <!-- Full text (hidden initially) -->
      <?php if ($hasFullText): ?>
      <div class="post-full-text" id="fulltext-<?= htmlspecialchars($postId) ?>" style="display:none;">
        <strong><?= htmlspecialchars($postTitle) ?></strong><br>
        <?= nl2br(htmlspecialchars(strip_tags($postText))) ?>
        <div style="margin-top:12px;">
          <a href="view_post.php?uid=<?= urlencode($authorUid) ?>&index=<?= $post['original_index'] ?>" class="btn btn-ghost btn-sm" style="padding-left:0;">
            <i class="fas fa-external-link-alt"></i> 詳細ページを開く
          </a>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <!-- Audio player -->
      <?php if (!empty($audioSrc)): ?>
      <div class="post-audio" id="player-wrap-<?= htmlspecialchars($postId) ?>">
        <button class="audio-play-btn" onclick="toggleAudio('<?= htmlspecialchars($postId) ?>')" id="play-btn-<?= htmlspecialchars($postId) ?>" aria-label="再生">
          <i class="fas fa-play" id="play-icon-<?= htmlspecialchars($postId) ?>"></i>
        </button>
        <div class="audio-progress-wrapper">
          <input type="range" class="audio-progress" id="progress-<?= htmlspecialchars($postId) ?>" value="0" min="0" max="100" step="0.1"
                 oninput="seekAudio('<?= htmlspecialchars($postId) ?>', this.value)">
          <span class="audio-time" id="time-<?= htmlspecialchars($postId) ?>">0:00</span>
        </div>
        <audio id="audio-<?= htmlspecialchars($postId) ?>"
               src="<?= htmlspecialchars($audioSrc) ?>"
               preload="none"
               ontimeupdate="updateProgress('<?= htmlspecialchars($postId) ?>')"
               onended="audioEnded('<?= htmlspecialchars($postId) ?>')">
        </audio>
      </div>
      <?php endif; ?>

      <!-- Actions -->
      <div class="post-actions">
        <button class="action-btn <?= $isLiked ? 'liked' : '' ?>" onclick="toggleLike('<?= htmlspecialchars($authorUid) ?>', <?= $post['original_index'] ?>, this)" id="like-btn-<?= htmlspecialchars($postId) ?>">
          <i class="<?= $isLiked ? 'fas' : 'far' ?> fa-heart"></i>
          <span class="like-count"><?= $likeCount > 0 ? $likeCount : '' ?></span>
        </button>
        <a href="view_post.php?uid=<?= urlencode($authorUid) ?>&index=<?= $post['original_index'] ?>#comments" class="action-btn">
          <i class="far fa-comment"></i>
          <span><?= $commentCount > 0 ? $commentCount : '' ?></span>
        </a>
        <button class="action-btn" onclick="copyPostLink('<?= htmlspecialchars($authorUid) ?>', <?= $post['original_index'] ?>)">
          <i class="fas fa-share-nodes"></i>
        </button>
      </div>

    </article>
    <?php endforeach; ?>
    <?php endif; ?>

  </div><!-- /feed -->
</main>

<!-- ============================================================
     BOTTOM NAV
     ============================================================ -->
<nav class="app-nav">
  <a href="mypage.php" class="nav-item">
    <i class="fas fa-home"></i>
    <span>ホーム</span>
  </a>
  <a href="timeline.php" class="nav-item active">
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
  <a href="profile.php" class="nav-item">
    <i class="fas fa-user"></i>
    <span>自分</span>
  </a>
</nav>

<script>
/* =============================================================
   Audio player (timeline)
   ============================================================= */
let currentAudioId = null;

function toggleAudio(postId) {
  const audio = document.getElementById('audio-' + postId);
  const icon  = document.getElementById('play-icon-' + postId);
  if (!audio) return;

  if (currentAudioId && currentAudioId !== postId) {
    const prev = document.getElementById('audio-' + currentAudioId);
    const prevIcon = document.getElementById('play-icon-' + currentAudioId);
    if (prev) prev.pause();
    if (prevIcon) prevIcon.className = 'fas fa-play';
    currentAudioId = null;
  }

  if (audio.paused) {
    audio.addEventListener('loadedmetadata', function onMeta() {
      updateProgress(postId);
      audio.removeEventListener('loadedmetadata', onMeta);
    });
    audio.play().catch(e => console.warn(e));
    icon.className = 'fas fa-pause';
    currentAudioId = postId;
  } else {
    audio.pause();
    icon.className = 'fas fa-play';
    currentAudioId = null;
  }
}

function updateProgress(postId) {
  const audio    = document.getElementById('audio-' + postId);
  const progress = document.getElementById('progress-' + postId);
  const timeEl   = document.getElementById('time-' + postId);
  if (!audio || !progress || !timeEl) return;

  const pct = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
  progress.value = pct;

  const cur = formatTime(audio.currentTime);
  const dur = (audio.duration && !isNaN(audio.duration)) ? formatTime(audio.duration) : '--:--';
  timeEl.textContent = cur + ' / ' + dur;
}

function seekAudio(postId, value) {
  const audio = document.getElementById('audio-' + postId);
  if (!audio || !audio.duration) return;
  audio.currentTime = (value / 100) * audio.duration;
}

function audioEnded(postId) {
  const icon = document.getElementById('play-icon-' + postId);
  const progress = document.getElementById('progress-' + postId);
  if (icon) icon.className = 'fas fa-play';
  if (progress) progress.value = 0;
  currentAudioId = null;
}

function formatTime(sec) {
  if (isNaN(sec)) return '0:00';
  const m = Math.floor(sec / 60);
  const s = Math.floor(sec % 60).toString().padStart(2, '0');
  return m + ':' + s;
}

/* =============================================================
   Read more
   ============================================================= */
function expandPost(postId) {
  const caption  = document.getElementById('caption-' + postId);
  const fulltext = document.getElementById('fulltext-' + postId);
  const btn      = document.getElementById('readmore-' + postId);
  if (!fulltext) return;

  const isOpen = fulltext.style.display !== 'none';
  if (isOpen) {
    fulltext.style.display = 'none';
    if (caption) caption.classList.add('collapsed');
    if (btn) btn.textContent = '続きを読む';
  } else {
    fulltext.style.display = 'block';
    if (caption) caption.classList.remove('collapsed');
    if (btn) btn.textContent = '閉じる';
  }
}

/* =============================================================
   Like / Share Actions
   ============================================================= */
function toggleLike(authorUid, postIndex, btn) {
  const icon = btn.querySelector('i');
  const countSpan = btn.querySelector('.like-count');
  
  const fd = new FormData();
  fd.append('action', 'like');
  fd.append('target_uid', authorUid);
  fd.append('post_index', postIndex);

  fetch('submit_interaction.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'ok') {
        const liked = (data.interaction === 'liked');
        btn.classList.toggle('liked', liked);
        icon.className = liked ? 'fas fa-heart' : 'far fa-heart';
        countSpan.textContent = data.count > 0 ? data.count : '';
      }
    }).catch(e => console.error(e));
}

function copyPostLink(uid, index) {
  const url = window.location.origin + '/mypage/view_post.php?uid=' + uid + '&index=' + index;
  navigator.clipboard.writeText(url).then(() => {
    alert('投稿のリンクをコピーしました！');
  });
}
</script>
</body>
</html>
