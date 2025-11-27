<?php
/**
 * Diagnostic Script: Check Project Permalinks and Post Status
 * 
 * This script helps diagnose why project permalinks are returning 404
 */

// Load WordPress
require_once('../../../wp-load.php');

// Query for projects with bidding assignment method
$args = [
    'post_type' => 'solar_project',
    'post_status' => 'any', // Check ALL statuses
    'posts_per_page' => 10,
    'meta_query' => [
        [
            'key' => '_vendor_assignment_method',
            'value' => 'bidding',
            'compare' => '='
        ]
    ]
];

$query = new WP_Query($args);

echo "<h1>Project Permalink Diagnostic</h1>";
echo "<p><strong>Total Projects Found:</strong> " . $query->found_posts . "</p>";

if ($query->have_posts()) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<thead><tr>";
    echo "<th>ID</th>";
    echo "<th>Title</th>";
    echo "<th>Status</th>";
    echo "<th>Post Name (Slug)</th>";
    echo "<th>Permalink</th>";
    echo "<th>Publicly Queryable</th>";
    echo "</tr></thead>";
    echo "<tbody>";
    
    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $post_status = get_post_status();
        $post_name = get_post()->post_name;
        $permalink = get_permalink();
        
        // Check if post type is publicly queryable
        $post_type_obj = get_post_type_object('solar_project');
        $publicly_queryable = $post_type_obj->publicly_queryable ? 'Yes' : 'No';
        
        $status_color = ($post_status === 'publish') ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td>" . $post_id . "</td>";
        echo "<td>" . esc_html(get_the_title()) . "</td>";
        echo "<td style='color: $status_color; font-weight: bold;'>" . $post_status . "</td>";
        echo "<td>" . esc_html($post_name) . "</td>";
        echo "<td><a href='" . esc_url($permalink) . "' target='_blank'>" . esc_html($permalink) . "</a></td>";
        echo "<td>" . $publicly_queryable . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
    wp_reset_postdata();
} else {
    echo "<p style='color: red;'>No projects found with bidding assignment method!</p>";
}

// Check rewrite rules
echo "<h2>Rewrite Rules for solar_project</h2>";
global $wp_rewrite;
$rules = get_option('rewrite_rules');

if ($rules) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<thead><tr><th>Pattern</th><th>Replacement</th></tr></thead>";
    echo "<tbody>";
    
    $found_rules = false;
    foreach ($rules as $pattern => $replacement) {
        if (strpos($pattern, 'solar_project') !== false || strpos($pattern, 'projects') !== false) {
            $found_rules = true;
            echo "<tr>";
            echo "<td>" . esc_html($pattern) . "</td>";
            echo "<td>" . esc_html($replacement) . "</td>";
            echo "</tr>";
        }
    }
    
    if (!$found_rules) {
        echo "<tr><td colspan='2' style='color: red; font-weight: bold;'>⚠️ NO REWRITE RULES FOUND FOR SOLAR_PROJECT!</td></tr>";
        echo "<tr><td colspan='2' style='color: orange;'>This is likely why you're getting 404 errors. You need to flush permalinks!</td></tr>";
    }
    
    echo "</tbody></table>";
} else {
    echo "<p style='color: red;'>No rewrite rules found in database!</p>";
}

// Check post type registration
echo "<h2>Post Type Registration Check</h2>";
$post_type_obj = get_post_type_object('solar_project');
if ($post_type_obj) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Property</th><th>Value</th></tr>";
    echo "<tr><td>Name</td><td>" . $post_type_obj->name . "</td></tr>";
    echo "<tr><td>Label</td><td>" . $post_type_obj->label . "</td></tr>";
    echo "<tr><td>Public</td><td>" . ($post_type_obj->public ? 'Yes' : 'No') . "</td></tr>";
    echo "<tr><td>Publicly Queryable</td><td>" . ($post_type_obj->publicly_queryable ? 'Yes' : 'No') . "</td></tr>";
    echo "<tr><td>Has Archive</td><td>" . ($post_type_obj->has_archive ? $post_type_obj->has_archive : 'No') . "</td></tr>";
    echo "<tr><td>Rewrite</td><td>" . print_r($post_type_obj->rewrite, true) . "</td></tr>";
    echo "</table>";
} else {
    echo "<p style='color: red;'>Post type 'solar_project' not registered!</p>";
}

echo "<hr>";
echo "<h2>Actions to Fix 404</h2>";
echo "<ol>";
echo "<li><strong>Go to WordPress Admin → Settings → Permalinks</strong></li>";
echo "<li><strong>Click 'Save Changes' (don't change anything)</strong></li>";
echo "<li><strong>Refresh this page to verify rewrite rules are created</strong></li>";
echo "</ol>";
