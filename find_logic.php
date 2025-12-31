<?php
$file = 'process.php';
$lines = file($file);
foreach ($lines as $i => $line) {
    if (stripos($line, '$_FILES') !== false || stripos($line, 'new Parser') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
