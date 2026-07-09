<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (empty($uid)) {
    header('Location: ../index.php');
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

// Mutuals
$mutualUids = array_intersect($myFollowing, $myFollowers);

// Get my custom network_id if set
$myProfileFile = $userDir . $uid . '_profile.json';
$myNetworkId = $uid;
if (file_exists($myProfileFile)) {
    $myProfileData = json_decode(file_get_contents($myProfileFile), true);
    if (!empty($myProfileData['network_id'])) {
        $myNetworkId = $myProfileData['network_id'];
    }
}

// Search
$searchResult = null;
$searchError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['search_uid'])) {
    $searchInput = trim($_POST['search_uid']);
    $targetUid = $searchInput;

    // Check if search input matches a custom network_id
    $networkIdsFile = $userDir . 'network_ids.json';
    if (file_exists($networkIdsFile)) {
        $networkIds = json_decode(file_get_contents($networkIdsFile), true) ?: [];
        if (isset($networkIds[$searchInput])) {
            $targetUid = $networkIds[$searchInput];
        }
    }

    if ($targetUid === $uid) {
        $searchError = "自分のIDは検索できません。";
    } elseif (!file_exists($userDir . $targetUid . '_profile.json')) {
        // Also check if they just haven't edited profile but have posts or exist
        if (!file_exists($userDir . $targetUid . '_posts.json')) {
             $searchError = "指定されたユーザーIDが見つかりませんでした。";
        } else {
            $searchResult = ['uid' => $targetUid, 'profile' => getUserProfile($targetUid, $userDir)];
        }
    } else {
        $searchResult = ['uid' => $targetUid, 'profile' => getUserProfile($targetUid, $userDir)];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ネットワーク | Udatsu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
    <style>
        .user-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        .user-card img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body>

<!-- ============================================================
     APP HEADER
     ============================================================ -->
<header class="app-header">
  <a href="mypage.php" class="app-header__logo" style="text-decoration: none; display: flex; align-items: center;">
    <img src="../img/udatsu-logo.png" alt="Udatsu" style="height: 32px; filter: drop-shadow(0 0 5px rgba(252,200,0,0.5));">
  </a>
  <div class="app-header__actions">
    <!-- AI Chat Bot Link -->
    <a href="bot.php" class="icon-btn" aria-label="AIチャット" title="思考の分身 AIチャット">
      <i class="fas fa-brain" style="color: var(--brand); text-shadow: 0 0 8px var(--brand-glow);"></i>
    </a>
  </div>
</header>

<div class="container" style="padding-top: 100px; padding-bottom: 60px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
        <h2 class="section-title" style="margin-bottom: 0; flex-shrink: 0;"><i class="fas fa-globe" style="color: var(--primary-neon);"></i> ネットワーク</h2>
        <span class="text-muted" style="font-size: 0.9rem;">My ID: <strong style="color:var(--text-white); background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; user-select: all;"><?= htmlspecialchars($myNetworkId) ?></strong></span>
    </div>

    <div class="glass-card" style="margin-bottom: 30px; padding: 20px;">
        <h3>ユーザー検索</h3>
        <p class="text-muted" style="font-size: 0.9rem; margin-bottom: 15px;">友達のユーザーIDを入力してフォローしましょう。</p>
        <form method="POST" style="display: flex; gap: 10px;">
            <input type="text" name="search_uid" class="form-control" placeholder="User IDを入力..." required style="flex: 1;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 検索</button>
        </form>
        <?php if ($searchError): ?>
            <p style="color: var(--warning-red); margin-top: 10px; font-size: 0.9rem;"><?= htmlspecialchars($searchError) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($searchResult): ?>
        <div class="glass-card" style="margin-bottom: 40px; padding: 20px; border-color: var(--primary-neon);">
            <h3>検索結果</h3>
            <?php 
                $suid = $searchResult['uid'];
                $sprof = $searchResult['profile'];
                $simg = !empty($sprof['image']) ? '../uploads/' . $suid . '/' . $sprof['image'] : '../img/default-icon.png';
                $isFollowing = in_array($suid, $myFollowing);
            ?>
            <div class="user-card">
                <img src="<?= htmlspecialchars($simg) ?>" alt="Profile">
                <div style="flex: 1;">
                    <div style="font-weight: bold; font-size: 1.1rem;"><?= htmlspecialchars($sprof['display_name']) ?></div>
                    <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($sprof['title']) ?></div>
                </div>
                <button onclick="toggleFollow('<?= htmlspecialchars($suid) ?>', <?= $isFollowing ? 'true' : 'false' ?>)" class="btn <?= $isFollowing ? 'btn-secondary' : 'btn-primary' ?>" id="btn-<?= htmlspecialchars($suid) ?>" style="padding: 5px 15px; font-size: 0.9rem;">
                    <?= $isFollowing ? 'フォロー解除' : 'フォローする' ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <h3 style="margin-bottom: 15px;">フォロー中 (<?= count($myFollowing) ?>)</h3>
    <?php if (empty($myFollowing)): ?>
        <p class="text-muted">誰もフォローしていません。</p>
    <?php else: ?>
        <div style="margin-bottom: 40px;">
        <?php foreach ($myFollowing as $fuid): 
            $fprof = getUserProfile($fuid, $userDir);
            $fimg = !empty($fprof['image']) ? '../uploads/' . $fuid . '/' . $fprof['image'] : '../img/default-icon.png';
            $isMutual = in_array($fuid, $mutualUids);
        ?>
            <div class="user-card">
                <img src="<?= htmlspecialchars($fimg) ?>" alt="Profile">
                <div style="flex: 1;">
                    <div style="font-weight: bold; font-size: 1.1rem;"><?= htmlspecialchars($fprof['display_name']) ?>
                        <?php if ($isMutual): ?>
                            <span style="font-size: 0.7rem; background: rgba(0,255,204,0.2); color: var(--primary-neon); padding: 2px 6px; border-radius: 10px; margin-left: 5px;"><i class="fas fa-handshake"></i> 相互</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($fprof['title']) ?></div>
                </div>
                <button onclick="toggleFollow('<?= htmlspecialchars($fuid) ?>', true)" class="btn btn-secondary" id="btn-<?= htmlspecialchars($fuid) ?>" style="padding: 5px 15px; font-size: 0.9rem;">
                    フォロー解除
                </button>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h3 style="margin-bottom: 15px;">フォロワー (<?= count($myFollowers) ?>)</h3>
    <?php if (empty($myFollowers)): ?>
        <p class="text-muted">フォロワーはいません。</p>
    <?php else: ?>
        <div>
        <?php foreach ($myFollowers as $fuid): 
            $fprof = getUserProfile($fuid, $userDir);
            $fimg = !empty($fprof['image']) ? '../uploads/' . $fuid . '/' . $fprof['image'] : '../img/default-icon.png';
            $isMutual = in_array($fuid, $mutualUids);
            $isFollowing = in_array($fuid, $myFollowing);
        ?>
            <div class="user-card">
                <img src="<?= htmlspecialchars($fimg) ?>" alt="Profile">
                <div style="flex: 1;">
                    <div style="font-weight: bold; font-size: 1.1rem;"><?= htmlspecialchars($fprof['display_name']) ?></div>
                    <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($fprof['title']) ?></div>
                </div>
                <?php if (!$isFollowing): ?>
                    <button onclick="toggleFollow('<?= htmlspecialchars($fuid) ?>', false)" class="btn btn-primary" id="btn-<?= htmlspecialchars($fuid) ?>" style="padding: 5px 15px; font-size: 0.9rem;">
                        フォローバック
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- ============================================================
     BOTTOM NAV
     ============================================================ -->
<nav class="app-nav">
  <a href="mypage.php" class="nav-item">
    <i class="fas fa-home"></i>
    <span>ホーム</span>
  </a>
  <a href="timeline.php" class="nav-item">
    <i class="fas fa-compass"></i>
    <span>タイムライン</span>
  </a>
  <a href="../voyager_upload.php" class="nav-item" aria-label="録音">
    <div class="nav-record">
      <i class="fas fa-microphone"></i>
    </div>
  </a>
  <a href="network.php" class="nav-item active">
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
   Theme setting (load-only)
   ============================================================= */
(function() {
  const saved = localStorage.getItem('udatsu_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);
})();

function toggleFollow(targetUid, isCurrentlyFollowing) {
    const action = isCurrentlyFollowing ? 'unfollow' : 'follow';
    fetch('api_follow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: action, targetUid: targetUid })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => alert('Communication error'));
}
</script>
</body>
</html>
