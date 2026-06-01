<?php
// test_cli.php
if (($_GET['key'] ?? '') !== 'udatsu2026test') {
    exit('Forbidden');
}

echo "<pre>";
echo "Current PHP web version: " . phpversion() . "\n\n";

$binaries = [
    '/usr/bin/php',
    '/usr/bin/php8.3',
    '/usr/bin/php8.2',
    '/usr/bin/php8.1',
    '/usr/bin/php8.0',
    '/usr/bin/php7.4',
    '/usr/local/bin/php',
];

foreach ($binaries as $bin) {
    if (file_exists($bin)) {
        echo "Found: $bin\n";
        $out = [];
        exec("$bin -v 2>&1", $out);
        echo "  Version: " . ($out[0] ?? 'Unknown') . "\n";
    } else {
        echo "Not found: $bin\n";
    }
}

echo "</pre>";
