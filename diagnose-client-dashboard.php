<?php
/**
 * Client Dashboard Diagnostic Script
 * Run this to check why the client dashboard isn't working
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

echo "=== CLIENT DASHBOARD DIAGNOSTIC ===\n\n";

// 1. Check current user
$current_user = wp_get_current_user();
echo "1. CURRENT USER:\n";
echo "   - ID: " . $current_user->ID . "\n";
echo "   - Username: " . $current_user->user_login . "\n";
echo "   - Roles: " . implode(', ', $current_user->roles) . "\n\n";

// 2. Check if solar_client role exists
echo "2. SOLAR_CLIENT ROLE:\n";
if (get_role('solar_client')) {
    echo "   ✓ Role exists\n";
    $role = get_role('solar_client');
    echo "   - Capabilities: " . implode(', ', array_keys($role->capabilities)) . "\n";
} else {
    echo "   ✗ Role does NOT exist!\n";
}
echo "\n";

// 3. Check post type registration
echo "3. POST TYPE 'solar_project':\n";
if (post_type_exists('solar_project')) {
    echo "   ✓ Registered as 'solar_project' (underscore)\n";
    $post_type = get_post_type_object('solar_project');
    echo "   - Public: " . ($post_type->public ? 'Yes' : 'No') . "\n";
    echo "   - Has Archive: " . ($post_type->has_archive ? 'Yes' : 'No') . "\n";
} else {
    echo "   ✗ NOT registered as 'solar_project'\n";
}

echo "\n4. POST TYPE 'solar-project' (with hyphen):\n";
if (post_type_exists('solar-project')) {
    echo "   ✓ Registered as 'solar-project' (hyphen)\n";
} else {
    echo "   ✗ NOT registered as 'solar-project'\n";
}
echo "\n";

// 4. Query projects for current user
echo "5. QUERY PROJECTS FOR CURRENT USER:\n";

// Try with underscore
$args_underscore = [
    'post_type' => 'solar_project',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'meta_query' => [
        [
            'key' => 'client_user_id',
            'value' => $current_user->ID,
        ]
    ]
];

$query_underscore = new WP_Query($args_underscore);
echo "   A. Query with 'solar_project' (underscore):\n";
echo "      - Found: " . $query_underscore->found_posts . " projects\n";
if ($query_underscore->have_posts()) {
    while ($query_underscore->have_posts()) {
        $query_underscore->the_post();
        echo "      • " . get_the_title() . " (ID: " . get_the_ID() . ")\n";
        echo "        client_user_id: " . get_post_meta(get_the_ID(), 'client_user_id', true) . "\n";
    }
    wp_reset_postdata();
}
echo "\n";

// Try with hyphen
$args_hyphen = [
    'post_type' => 'solar-project',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'meta_query' => [
        [
            'key' => 'client_user_id',
            'value' => $current_user->ID,
        ]
    ]
];

$query_hyphen = new WP_Query($args_hyphen);
echo "   B. Query with 'solar-project' (hyphen):\n";
echo "      - Found: " . $query_hyphen->found_posts . " projects\n";
if ($query_hyphen->have_posts()) {
    while ($query_hyphen->have_posts()) {
        $query_hyphen->the_post();
        echo "      • " . get_the_title() . " (ID: " . get_the_ID() . ")\n";
    }
    wp_reset_postdata();
}
echo "\n";

// 5. Check all projects
echo "6. ALL SOLAR PROJECTS:\n";
$all_projects = new WP_Query([
    'post_type' => 'solar_project',
    'posts_per_page' => 10,
    'post_status' => 'any'
]);

echo "   Total found: " . $all_projects->found_posts . "\n";
if ($all_projects->have_posts()) {
    while ($all_projects->have_posts()) {
        $all_projects->the_post();
        $client_id = get_post_meta(get_the_ID(), 'client_user_id', true);
        echo "   • " . get_the_title() . " (ID: " . get_the_ID() . ")\n";
        echo "     client_user_id meta: '" . $client_id . "'\n";
        echo "     Status: " . get_post_status() . "\n";
    }
    wp_reset_postdata();
} else {
    echo "   No projects found in database!\n";
}
echo "\n";

// 6. Check shortcode
echo "7. SHORTCODE CHECK:\n";
global $shortcode_tags;
if (isset($shortcode_tags['unified_solar_dashboard'])) {
    echo "   ✓ 'unified_solar_dashboard' shortcode is registered\n";
} else {
    echo "   ✗ 'unified_solar_dashboard' shortcode NOT registered\n";
}

echo "\n=== END DIAGNOSTIC ===\n";
