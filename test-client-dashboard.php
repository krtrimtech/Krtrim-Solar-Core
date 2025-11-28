<?php
/**
 * Quick Test Script for Client Dashboard
 * This will help identify if the issue is in view-client-dashboard.php
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

echo "Testing client dashboard file...\n\n";

// Try to include the file
try {
    if (!function_exists('render_solar_client_dashboard')) {
        require_once(__DIR__ . '/public/views/view-client-dashboard.php');
        echo "✓ File included successfully\n";
    }
    
    if (function_exists('render_solar_client_dashboard')) {
        echo "✓ Function 'render_solar_client_dashboard' exists\n";
        
        // Try to call it (output buffered)
        ob_start();
        render_solar_client_dashboard();
        $output = ob_get_clean();
        
        echo "✓ Function executed without fatal errors\n";
        echo "Output length: " . strlen($output) . " bytes\n";
        
    } else {
        echo "✗ Function 'render_solar_client_dashboard' NOT found\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\nTest complete.\n";
