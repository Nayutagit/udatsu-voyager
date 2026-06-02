<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$path = __DIR__ . '/../users';
echo "Path: " . $path . "\n";
echo "Exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";
$files = glob($path . '/*.json');
print_r($files);
