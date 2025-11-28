<?php
/**
 * Simple Syntax Check for Dashboard Files
 * Add this as first line in wp-config.php to see errors:
 * ini_set('display_errors', 1); error_reporting(E_ALL);
 */

$files_to_check = [
    'Client Dashboard' => __DIR__ . '/public/views/view-client-dashboard.php',
    'Vendor Dashboard' => __DIR__ . '/public/views/view-vendor-dashboard.php',
    'Unified Dashboard' => __DIR__ . '/unified-solar-dashboard.php',
];

foreach ($files_to_check as $name => $file) {
    if (file_exists($file)) {
        $output = shell_exec("php -l " . escapeshellarg($file) . " 2>&1");
        echo "$name: ";
        if (strpos($output, 'No syntax errors') !== false) {
            echo "✓ OK\n";
        } else {
            echo "✗ ERROR\n$output\n";
        }
    } else {
        echo "$name: ✗ FILE NOT FOUND\n";
    }
}
?>
