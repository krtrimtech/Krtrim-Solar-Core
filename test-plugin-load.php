<?php
/**
 * Test file to detect fatal errors in plugin
 * Run this from command line or browser to see exact error
 */

// Simulate WordPress environment minimally
define('ABSPATH', '/home/shyanukant/Downloads/wordpress/');
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting plugin test...\n\n";

// Set plugin directory
$plugin_dir = __DIR__ . '/';

try {
    echo "Loading dependencies...\n";
    
    // Core dependencies
    if (file_exists($plugin_dir . 'includes/class-post-types-taxonomies.php')) {
        require_once $plugin_dir . 'includes/class-post-types-taxonomies.php';
        echo "✓ Loaded class-post-types-taxonomies.php\n";
    }
    
    if (file_exists($plugin_dir . 'includes/class-admin-menus.php')) {
        require_once $plugin_dir . 'includes/class-admin-menus.php';
        echo "✓ Loaded class-admin-menus.php\n";
    }
    
    // Check for API base before API handlers
    if (file_exists($plugin_dir . 'includes/api/class-api-base.php')) {
        require_once $plugin_dir . 'includes/api/class-api-base.php';
        echo "✓ Loaded class-api-base.php\n";
    }
    
    if (file_exists($plugin_dir . 'includes/class-api-handlers.php')) {
        require_once $plugin_dir . 'includes/class-api-handlers.php';
        echo "✓ Loaded class-api-handlers.php\n";
    }
    
    if (file_exists($plugin_dir . 'includes/ajax-get-project-details.php')) {
        require_once $plugin_dir . 'includes/ajax-get-project-details.php';
        echo "✓ Loaded ajax-get-project-details.php\n";
    }
    
    if (file_exists($plugin_dir . 'includes/class-admin-widgets.php')) {
        require_once $plugin_dir . 'includes/class-admin-widgets.php';
        echo "✓ Loaded class-admin-widgets.php\n";
    }
    
    if (file_exists($plugin_dir . 'includes/class-process-steps-manager.php')) {
        require_once $plugin_dir . 'includes/class-process-steps-manager.php';
        echo "✓ Loaded class-process-steps-manager.php\n";
    }
    
    if (file_exists($plugin_dir . 'includes/class-notifications-manager.php')) {
        require_once $plugin_dir . 'includes/class-notifications-manager.php';
        echo "✓ Loaded class-notifications-manager.php\n";
    }
    
    // Admin views
    echo "\nLoading admin views...\n";
    $admin_views = [
        'view-vendor-approval.php',
        'view-project-reviews.php',
        'view-bid-management.php',
        'view-general-settings.php',
        'view-team-analysis.php',
        'view-process-step-template.php'
    ];
    
    foreach ($admin_views as $view) {
        if (file_exists($plugin_dir . 'admin/views/' . $view)) {
            require_once $plugin_dir . 'admin/views/' . $view;
            echo "✓ Loaded $view\n";
        } else {
            echo "✗ Missing $view\n";
        }
    }
    
    // Public views
    echo "\nLoading public views...\n";
    $public_views = [
        'view-client-dashboard.php',
        'view-vendor-dashboard.php',
        'view-area-manager-dashboard.php',
        'view-marketplace.php',
        'view-vendor-registration.php',
        'view-vendor-status.php'
    ];
    
    foreach ($public_views as $view) {
        if (file_exists($plugin_dir . 'public/views/' . $view)) {
            require_once $plugin_dir . 'public/views/' . $view;
            echo "✓ Loaded $view\n";
        } else {
            echo "✗ Missing $view\n";
        }
    }
    
    echo "\n✅ All files loaded successfully! No fatal errors detected.\n";
    echo "\nIf WordPress still shows fatal error, the issue is:\n";
    echo "1. A WordPress-specific function call failing\n";
    echo "2. Database table missing\n";
    echo "3. Plugin conflict\n";
    echo "\nCheck WordPress debug.log for the actual error.\n";
    
} catch (Error $e) {
    echo "\n❌ FATAL ERROR DETECTED:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "\n❌ EXCEPTION DETECTED:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
