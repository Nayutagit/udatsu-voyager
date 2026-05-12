<?php
session_start();
session_unset();
session_destroy();

// 永続ログインCookieも削除
setcookie('udatsu_auth', '', time() - 3600, '/');

header("Location: /index.php");
exit();