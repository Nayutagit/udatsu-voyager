<?php
session_start();

// Set dummy session data for preview
$_SESSION['uid'] = 'preview_user_001';
$_SESSION['user_name'] = 'プレビュー太郎';
$_SESSION['user_plan'] = 'standard';
$_SESSION['email'] = 'preview@example.com';

// Redirect to the upload page
header("Location: voyager_upload.php");
exit();
?>
