<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$path = realpath(__DIR__ . '/../users');
echo "Path: " . $path . "\n";
$files = glob($path . '/*.json');
print_r($files);
