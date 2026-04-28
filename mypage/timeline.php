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

// Mutuals
$mutualUids = array_intersect($myFollowing, $myFollowers);

// Collect shared posts
$timelinePosts = [];

foreach ($mutualUids as $mutualUid) {
    $postsFile = $userDir . $mutualUid . '_posts.json';
    if (file_exists($postsFile)) {
        $mutualPosts = json_decode(file_get_contents($postsFile), true) ?: [];
        $profile = getUserProfile($mutualUid, $userDir);
        
        foreach ($mutualPosts as $idx => $post) {
            if (!empty($post['is_shared'])) {
                $post['author_uid'] = $mutualUid;
                $post['author_profile'] = $profile;
                $post['original_index'] = $idx;
                $timelinePosts[] = $post;
            }
        }
    }
}

// Sort by date descending
usort($timelinePosts, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>タイムライン | Udatsu</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css?v=<?= time() ?>">
    <style>
        .timeline-post {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }
        .timeline-post:hover {
            border-color: rgba(0, 255, 204, 0.5);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        .author-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 15px;
        }
        .author-info img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-neon);
        }
        .post-title {
            font-size: 1.4rem;
            color: var(--text-white);
            margin-bottom: 10px;
            font-weight: bold;
        }
        .post-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 15px;
        }
        .post-content {
            font-size: 0.95rem;
            line-height: 1.7;
            color: #ddd;
            background: rgba(0,0,0,0.3);
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid var(--primary-neon);
            white-space: pre-wrap;
        }
        .post-summary {
            font-size: 0.95rem;
            color: #ccc;
            line-height: 1.6;
            margin-bottom: 5px;
        }
        .audio-player-container {
            margin-top: 15px;
            background: rgba(0,0,0,0.4);
            padding: 10px;
            border-radius: 50px;
        }
        .audio-player-container audio {
            width: 100%;
            height: 40px;
            outline: none;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/header.php'; ?>

<div class="container" style="padding-top: 100px; padding-bottom: 60px; max-width: 800px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 40px;">
        <h2 class="section-title" style="margin-bottom: 0; flex-shrink: 0;"><i class="fas fa-hourglass-half" style="color: var(--primary-neon);"></i> タイムライン</h2>
        <span class="text-muted" style="font-size: 0.9rem;">相互フォローの最新の投稿が表示されます</span>
    </div>

    <?php if (empty($timelinePosts)): ?>
        <div class="glass-card" style="text-align: center; padding: 50px;">
            <i class="fas fa-inbox" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 20px;"></i>
            <h3 style="color: var(--text-muted);">まだ投稿がありません</h3>
            <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 10px;">ネットワークから友達を検索して相互フォローになりましょう！</p>
            <a href="network.php" class="btn btn-primary" style="margin-top: 20px; display: inline-block;">ネットワークを開く</a>
        </div>
    <?php else: ?>
        <?php $timelineIndex = 0; foreach ($timelinePosts as $post): 
            $timelineIndex++;
            $authorImg = !empty($post['author_profile']['image']) ? '../uploads/' . $post['author_uid'] . '/' . $post['author_profile']['image'] : '../img/default-icon.png';
            $audioUrl = '';
            $audioSource = $post['audio_file'] ?? $post['audio'] ?? '';
            if (!empty($audioSource)) {
                if (strpos($audioSource, 'audio/') === 0 || strpos($audioSource, 'uploads/') === 0) {
                    $audioUrl = "../audio_proxy.php?target_uid=" . urlencode($post['author_uid']) . "&path=" . urlencode($audioSource);
                } else {
                    $audioUrl = "../users/{$post['author_uid']}/posts/" . urlencode($audioSource);
                }
            }
        ?>
            <div class="timeline-post animate-fadeup">
                <div class="author-info">
                    <img src="<?= htmlspecialchars($authorImg) ?>" alt="Author">
                    <div>
                        <div style="font-weight: bold; font-size: 1.1rem; color: var(--primary-neon);"><?= htmlspecialchars($post['author_profile']['display_name']) ?></div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($post['author_profile']['title']) ?></div>
                    </div>
                </div>
                
                <div class="post-title"><?= htmlspecialchars($post['title']) ?></div>
                
                <div class="post-meta">
                    <i class="far fa-calendar-alt"></i> <?= htmlspecialchars($post['date']) ?>
                </div>

                <?php 
                    $fullText = $post['text'] ?? '';
                    $cleanText = str_replace(["\r", "\n"], " ", $fullText);
                    $snippet = mb_strimwidth($cleanText, 0, 100, '...');
                ?>
                <div class="post-summary" id="snippet-<?= $timelineIndex ?>">
                    <?= htmlspecialchars($snippet) ?>
                    <?php if (mb_strlen($cleanText) > 100): ?>
                        <a href="javascript:void(0)" onclick="document.getElementById('snippet-<?= $timelineIndex ?>').style.display='none'; document.getElementById('full-<?= $timelineIndex ?>').style.display='block';" style="color: var(--primary-neon); text-decoration: none; font-size: 0.85rem; margin-left: 10px;">続きを読む <i class="fas fa-chevron-down"></i></a>
                    <?php endif; ?>
                </div>

                <div class="post-content" id="full-<?= $timelineIndex ?>" style="display: none;">
                    <?= htmlspecialchars($fullText) ?>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="javascript:void(0)" onclick="document.getElementById('full-<?= $timelineIndex ?>').style.display='none'; document.getElementById('snippet-<?= $timelineIndex ?>').style.display='block';" style="color: var(--text-muted); text-decoration: none; font-size: 0.85rem;">閉じる <i class="fas fa-chevron-up"></i></a>
                    </div>
                </div>

                <?php if ($audioUrl): ?>
                <div class="audio-player-container">
                    <audio controls controlsList="nodownload">
                        <source src="<?= htmlspecialchars($audioUrl) ?>" type="audio/mp4">
                        Your browser does not support the audio element.
                    </audio>
                </div>
                <?php endif; ?>

                <?php if ((($post['status'] ?? '') === 'エラー' || strpos($post['title'], '解析エラー') !== false) && $userPlan === 'admin'): ?>
                <div style="margin-top: 15px; text-align: right;">
                    <form method="POST" action="retry_analysis.php" style="display: inline;">
                        <input type="hidden" name="index" value="<?= $post['original_index'] ?>">
                        <input type="hidden" name="target_uid" value="<?= htmlspecialchars($post['author_uid']) ?>">
                        <button type="submit" class="btn btn-primary" style="padding: 5px 15px; font-size: 0.85rem; background: var(--warning-red); color: white; border-color: var(--warning-red);">
                            <i class="fas fa-sync-alt"></i> 管理者として再試行
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

</body>
</html>
