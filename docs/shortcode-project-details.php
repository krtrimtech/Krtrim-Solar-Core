<?php
/**
 * Project Details Shortcode
 * Displays location, system size, status, and description for solar projects
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode: [project_details]
 * Usage: Add this shortcode to Elementor loop grid items
 */
function ksc_project_details_shortcode($atts) {
    // Get current post ID (works in loop)
    $post_id = get_the_ID();
    
    // Return empty if not a solar_project
    if (get_post_type($post_id) !== 'solar_project') {
        return '';
    }
    
    // Get meta data
    $city = get_post_meta($post_id, '_project_city', true);
    $state = get_post_meta($post_id, '_project_state', true);
    $system_size = get_post_meta($post_id, '_solar_system_size_kw', true);
    $status = get_post_meta($post_id, 'project_status', true);
    $description = get_the_excerpt($post_id); // or use get_the_content() for full content
    
    // Format status for display
    $status_display = ucwords(str_replace('_', ' ', $status));
    
    // Build output
    ob_start();
    ?>
    
    <div class="ksc-project-details">
        
        <?php if ($city || $state): ?>
        <div class="ksc-detail-location">
            <strong>Location:</strong> <?php echo esc_html($city . ', ' . $state); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($system_size): ?>
        <div class="ksc-detail-size">
            <strong>System Size:</strong> <?php echo esc_html($system_size); ?> kW
        </div>
        <?php endif; ?>
        
        <?php if ($status): ?>
        <div class="ksc-detail-status">
            <strong>Status:</strong> <span class="status-<?php echo esc_attr($status); ?>"><?php echo esc_html($status_display); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($description): ?>
        <div class="ksc-detail-description">
            <strong>Description:</strong>
            <p><?php echo wp_kses_post($description); ?></p>
        </div>
        <?php endif; ?>
        
    </div>
    
    <?php
    return ob_get_clean();
}
add_shortcode('project_details', 'ksc_project_details_shortcode');


/**
 * Individual field shortcodes (if you want separate control)
 */

// [project_location]
function ksc_project_location_shortcode() {
    $post_id = get_the_ID();
    $city = get_post_meta($post_id, '_project_city', true);
    $state = get_post_meta($post_id, '_project_state', true);
    return esc_html($city . ', ' . $state);
}
add_shortcode('project_location', 'ksc_project_location_shortcode');

// [project_size]
function ksc_project_size_shortcode() {
    $post_id = get_the_ID();
    $system_size = get_post_meta($post_id, '_solar_system_size_kw', true);
    return esc_html($system_size) . ' kW';
}
add_shortcode('project_size', 'ksc_project_size_shortcode');

// [project_status]
function ksc_project_status_shortcode() {
    $post_id = get_the_ID();
    $status = get_post_meta($post_id, 'project_status', true);
    $status_display = ucwords(str_replace('_', ' ', $status));
    return '<span class="status-' . esc_attr($status) . '">' . esc_html($status_display) . '</span>';
}
add_shortcode('project_status', 'ksc_project_status_shortcode');

// [project_description]
function ksc_project_description_shortcode() {
    $post_id = get_the_ID();
    return get_the_excerpt($post_id);
}
add_shortcode('project_description', 'ksc_project_description_shortcode');
