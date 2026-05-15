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

// profiles cache to speed up loading
$profilesCache = [];

// Collect shared posts
$timelinePosts = [];

// 1. Collect from following
foreach ($myFollowing as $followUid) {
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
            $allowedStatus = ['My Udastack追加済', ''];
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
        $allowedStatus = ['My Udastack追加済', ''];
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
            background: var(--bg-panel);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 0;
            margin-bottom: 35px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease, border-color 0.3s ease;
            animation: fadeInUp 0.8s ease both;
        }
        .timeline-post:hover {
            transform: translateY(-5px);
            border-color: var(--primary-neon);
        }
        .post-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .post-header img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-neon);
            box-shadow: 0 0 10px rgba(252, 200, 0, 0.3);
        }
        .author-name {
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--text-white);
        }
        .author-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .post-eyecatch {
            width: 100%;
            height: auto;
            max-height: 400px;
            object-fit: cover;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .post-body {
            padding: 20px;
        }
        .post-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 12px;
            line-height: 1.3;
            color: var(--text-white);
        }
        .post-text {
            font-size: 1rem;
            line-height: 1.7;
            color: #ddd;
            margin-bottom: 20px;
        }
        .audio-player {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50px;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .audio-player audio {
            flex: 1;
            height: 35px;
        }
        .social-actions {
            display: flex;
            gap: 25px;
            padding: 15px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(255, 255, 255, 0.02);
        }
        .action-btn {
            color: var(--text-muted);
            font-size: 1.1rem;
            cursor: pointer;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .action-btn:hover {
            color: var(--primary-neon);
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
        <div class="glass-card animate-fadeup" style="text-align: center; padding: 60px; border-radius: 20px;">
            <i class="fas fa-users-slash" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 20px;"></i>
            <h3 style="color: var(--text-white);">タイムラインはまだ空です</h3>
            <p style="font-size: 1rem; color: var(--text-muted); margin-top: 15px; line-height: 1.6;">
                あなたがフォローしているユーザーが音声をシェアするとここに表示されます。<br>
                まずはネットワークから気になるユーザーをフォローしてみましょう！
            </p>
            <a href="network.php" class="btn btn-primary" style="margin-top: 30px; padding: 12px 30px; border-radius: 50px;">ユーザーを探しに行く</a>
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
            <div class="timeline-post">
                <div class="post-header">
                    <img src="<?= htmlspecialchars($authorImg) ?>" alt="Author">
                    <div>
                        <div class="author-name"><?= htmlspecialchars($post['author_profile']['display_name']) ?></div>
                        <div class="author-meta"><?= htmlspecialchars($post['author_profile']['title']) ?> • <?= htmlspecialchars($post['date']) ?></div>
                    </div>
                </div>

                <?php if (!empty($post['thumbnail'])): ?>
                    <a href="view_post.php?uid=<?= urlencode($post['author_uid']) ?>&index=<?= $post['original_index'] ?>">
                        <img src="../<?= htmlspecialchars($post['thumbnail']) ?>" class="post-eyecatch" alt="Eyecatch">
                    </a>
                <?php endif; ?>
                
                <div class="post-body">
                    <a href="view_post.php?uid=<?= urlencode($post['author_uid']) ?>&index=<?= $post['original_index'] ?>" style="text-decoration: none; color: inherit; display: block;">
                        <div class="post-title"><?= htmlspecialchars($post['title']) ?></div>
                    </a>
                    
                    <?php 
                        $fullText = $post['text'] ?? '';
                        $summary = $post['summary'] ?? '';
                        $displayContent = !empty($summary) ? $summary : $fullText;
                        $snippet = mb_strimwidth(strip_tags($displayContent), 0, 150, '...');
                    ?>

                    <div class="post-text" id="snippet-<?= $timelineIndex ?>">
                        <?= nl2br(htmlspecialchars($snippet)) ?>
                        <?php if (mb_strlen(strip_tags($displayContent)) > 150): ?>
                            <a href="javascript:void(0)" onclick="document.getElementById('snippet-<?= $timelineIndex ?>').style.display='none'; document.getElementById('full-<?= $timelineIndex ?>').style.display='block';" style="color: var(--primary-neon); text-decoration: none; font-size: 0.9rem; font-weight: bold;">もっと見る</a>
                        <?php endif; ?>
                    </div>

                    <div class="post-text" id="full-<?= $timelineIndex ?>" style="display: none;">
                        <?= nl2br(htmlspecialchars($displayContent)) ?>
                        <div style="margin-top: 10px;">
                            <a href="javascript:void(0)" onclick="document.getElementById('full-<?= $timelineIndex ?>').style.display='none'; document.getElementById('snippet-<?= $timelineIndex ?>').style.display='block';" style="color: var(--text-muted); text-decoration: none; font-size: 0.85rem;">閉じる</a>
                        </div>
                    </div>

                    <?php if ($audioUrl): ?>
                    <div class="audio-player">
                        <audio controls <?= ($userPlan === 'admin') ? '' : 'controlsList="nodownload"' ?>>
                            <source src="<?= htmlspecialchars($audioUrl) ?>" type="audio/mp4">
                        </audio>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="social-actions">
                    <?php 
                        $likes = $post['likes'] ?? [];
                        $likeCount = count($likes);
                        $isLiked = in_array($uid, $likes);
                    ?>
                    <div class="action-btn" onclick="handleLike('<?= $post['author_uid'] ?>', <?= $post['original_index'] ?>, this)">
                        <i class="<?= $isLiked ? 'fas' : 'far' ?> fa-heart" style="<?= $isLiked ? 'color: #ff4444;' : '' ?>"></i> 
                        <span class="like-count"><?= $likeCount ?></span>
                    </div>
                    <div class="action-btn" onclick="alert('コメント機能は現在準備中です！')"><i class="far fa-comment"></i> Comment</div>
                    <div class="action-btn" onclick="copyPostLink('<?= $post['author_uid'] ?>', <?= $post['original_index'] ?>)"><i class="fas fa-share-nodes"></i> Share</div>
                </div>

                <?php if ((($post['status'] ?? '') === 'エラー' || strpos($post['title'] ?? '', '解析エラー') !== false) && $userPlan === 'admin'): ?>
                <div style="padding: 15px; text-align: right; border-top: 1px solid rgba(255,255,255,0.05);">
                    <button type="button" class="btn btn-primary" onclick="handleRetry('<?= htmlspecialchars($post['author_uid']) ?>', <?= $post['original_index'] ?>, this)" style="padding: 5px 15px; font-size: 0.85rem; background: var(--warning-red); color: white; border-color: var(--warning-red);">
                        <i class="fas fa-sync-alt"></i> 管理者として再試行
                    </button>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
function handleLike(authorUid, postIndex, btn) {
    const icon = btn.querySelector('i');
    const countSpan = btn.querySelector('.like-count');
    
    const formData = new FormData();
    formData.append('action', 'like');
    formData.append('target_uid', authorUid);
    formData.append('post_index', postIndex);

    fetch('submit_interaction.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') {
            if (data.interaction === 'liked') {
                icon.classList.remove('far');
                icon.classList.add('fas');
                icon.style.color = '#ff4444';
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
                icon.style.color = '';
            }
            countSpan.textContent = data.count;
        }
    })
    .catch(e => console.error(e));
}

function copyPostLink(uid, index) {
    const url = window.location.origin + '/mypage/view_post.php?uid=' + uid + '&index=' + index;
    navigator.clipboard.writeText(url).then(() => {
        alert('投稿のリンクをコピーしました！');
    });
}
function handleRetry(authorUid, postIndex, btn) {
    if (!confirm('管理者としてこの投稿の解析を再試行しますか？')) return;
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 実行中...';

    const formData = new FormData();
    formData.append('target_uid', authorUid);
    formData.append('index', postIndex);

    fetch('retry_analysis.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert('解析をバックグラウンドで開始しました。完了まで数分かかる場合があります。');
            btn.innerHTML = '<i class="fas fa-check"></i> 開始済み';
        } else {
            alert('エラー: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(e => {
        console.error(e);
        alert('通信エラーが発生しました');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}
</script>
</body>
</html>
