<?php
/**
 * Flush Permalinks Script
 * 
 * Run this script ONCE to flush WordPress permalink rewrite rules
 * This will fix 404 errors on solar_project single pages
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user has permission (optional, but safer)
if (!current_user_can('manage_options')) {
    die('Error: You do not have permission to flush permalinks. Please log in as an administrator.');
}

echo "<h1>Flushing Permalinks...</h1>";

// Flush rewrite rules
flush_rewrite_rules(false);

echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
echo "<h2>✓ Permalinks Successfully Flushed!</h2>";
echo "<p>The rewrite rules have been regenerated. Your solar_project single pages should now work.</p>";
echo "</div>";

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Go back to your <a href='" . home_url('/project-marketplace/') . "'>Project Marketplace</a></li>";
echo "<li>Click 'View Details' on any project</li>";
echo "<li>Verify the project page loads (no 404)</li>";
echo "</ol>";

echo "<hr>";
echo "<p><a href='diagnostic-permalinks.php'>Run Diagnostic Script</a> to verify the fix</p>";
