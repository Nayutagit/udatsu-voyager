<?php
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "\n";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'N/A') . "\n";
echo "isLocal (bootstrap logic): " . (($_SERVER['HTTP_HOST'] === '127.0.0.1:8000' || $_SERVER['HTTP_HOST'] === 'localhost:8000') ? 'YES' : 'NO') . "\n";
