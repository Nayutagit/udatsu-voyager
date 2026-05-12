<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Session Diagnostic</h3>";
echo "session_save_path: " . session_save_path() . "<br>";
echo "is_writable: " . (is_writable(session_save_path()) ? 'YES' : 'NO') . "<br>";

$res = session_start();
echo "session_start result: " . ($res ? 'SUCCESS' : 'FAILED') . "<br>";
echo "session_id: " . session_id() . "<br>";
echo "SESSION Data: <pre>";
print_r($_SESSION);
echo "</pre>";

echo "COOKIE Data: <pre>";
print_r($_COOKIE);
echo "</pre>";

echo "PHP Version: " . phpversion() . "<br>";
echo "Server Time: " . date('Y-m-d H:i:s') . "<br>";
