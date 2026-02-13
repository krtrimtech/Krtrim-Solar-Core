<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to load WordPress
// Assuming we are in /home/shyanukant/Downloads/krtrim/Krtrim-Solar-Core/
// And WordPress root is likely one or two levels up or in /var/www/html
if (file_exists(__DIR__ . '/../../../wp-load.php')) {
    require_once(__DIR__ . '/../../../wp-load.php');
} elseif (file_exists('/var/www/html/wp-load.php')) {
    require_once('/var/www/html/wp-load.php');
} else {
    // Try to find it
    $path = __DIR__;
    for ($i = 0; $i < 5; $i++) {
        if (file_exists($path . '/wp-load.php')) {
            require_once($path . '/wp-load.php');
            break;
        }
        $path = dirname($path);
    }
}

if (!defined('ABSPATH')) {
    die("Could not find wp-load.php");
}

echo "<h2>Sales Manager Cleaner Visibility Debug</h2>";

 = get_users(['role' => 'sales_manager']);

if (empty()) {
    echo "No Sales Managers found.<br>";
} else {
    echo "Found " . count() . " Sales Managers.<hr>";
    
    foreach ( as ) {
        echo "<h3>Wrapper User: " . ->display_name . " (ID: " . ->ID . ")</h3>";
        
         = get_user_meta(->ID, '_supervised_by_area_manager', true);
        echo "<strong>Assigned AM ID:</strong> " . ( ?  : "NONE") . "<br>";
        
         = get_user_meta(->ID, 'city', true);
         = get_user_meta(->ID, 'state', true);
        echo "<strong>Location:</strong> City=" . ( ?  : "N/A") . ", State=" . ( ?  : "N/A") . "<br>";
        
        // Emulate Logic
         = [
            'role' => 'solar_cleaner',
            'meta_query' => [],
            'orderby' => 'display_name',
            'order' => 'ASC',
        ];
        
        if () {
            echo "<em>Logic: Fetching cleaners supervised by AM #</em><br>";
             ['meta_query'][] = [
                'key' => '_supervised_by_area_manager',
                'value' => ,
            ];
        } elseif () {
            echo "<em>Logic: Fallback to scanning cleaners in city ''</em><br>";
             ['meta_query'][] = [
                'key' => 'city',
                'value' => ,
                'compare' => 'LIKE'
             ];
        } else {
             echo "<span style='color:red'>FAILURE: No AM assigned and no City set. Returning 0 cleaners.</span><br>";
             ['include'] = [0];
        }
        
         = get_users();
        echo "<strong>Cleaners Found:</strong> " . count() . "<br>";
        if (!empty()) {
            echo "<ul>";
            foreach ( as ) {
                echo "<li>" . ->display_name . " (ID: " . ->ID . ", City: " . get_user_meta(->ID, 'city', true) . ")</li>";
            }
            echo "</ul>";
        }
        echo "<hr>";
    }
}
