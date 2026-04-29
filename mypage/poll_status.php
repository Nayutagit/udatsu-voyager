<?php
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['uid'])) { echo json_encode(['has_processing' => false]); exit; }

$uid = $_SESSION['uid'];
$postsFile = __DIR__ . '/../users/' . $uid . '_posts.json';
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : [];
$hasProcessing = is_array($posts) && count(array_filter($posts, fn($p) => ($p['status'] ?? '') === '解析中')) > 0;
echo json_encode(['has_processing' => $hasProcessing]);
