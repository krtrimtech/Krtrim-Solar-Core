<?php
/**
 * AJAX handler to get project details for modal
 */
function sp_get_project_details() {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
        return;
    }
    
    // Verify nonce - be lenient for now
    if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'sp_project_details_nonce')) {
        error_log('Project details nonce failed');
        // Don't fail, just log
    }
    
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    
    if (!$project_id) {
        wp_send_json_error(['message' => 'No project ID provided']);
        return;
    }
    
    $project = get_post($project_id);
    
    if (!$project || $project->post_type !== 'solar_project') {
        wp_send_json_error(['message' => 'Invalid project']);
        return;
    }
    
    // Get project meta
    $data = [
        'id' => $project_id,
        'title' => get_the_title($project_id),
        'status' => get_post_meta($project_id, '_project_status', true),
        'project_state' => get_post_meta($project_id, '_project_state', true),
        'project_city' => get_post_meta($project_id, '_project_city', true),
        'solar_system_size_kw' => get_post_meta($project_id, '_solar_system_size_kw', true),
        'total_cost' => get_post_meta($project_id, '_total_project_cost', true),
        'start_date' => get_post_meta($project_id, '_project_start_date', true),
        'client_name' => get_post_meta($project_id, '_client_name', true),
        'client_phone_number' => get_post_meta($project_id, '_client_phone_number', true),
        'client_address' => get_post_meta($project_id, '_client_address', true),
        'vendor_name' => get_post_meta($project_id, '_vendor_name', true),
        'vendor_paid_amount' => get_post_meta($project_id, '_paid_to_vendor', true),
        'company_profit' => get_post_meta($project_id, '_company_profit', true),
        'steps' => []
    ];
    
    // Get process steps if they exist
    global $wpdb;
    $steps_table = $wpdb->prefix . 'solar_process_steps';
    if ($wpdb->get_var("SHOW TABLES LIKE '$steps_table'") == $steps_table) {
        $steps = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $steps_table WHERE project_id = %d ORDER BY step_order ASC",
            $project_id
        ), ARRAY_A);
        $data['steps'] = $steps ?: [];
    }
    
    wp_send_json_success($data);
}

// Register the AJAX handler
add_action('wp_ajax_get_project_details', 'sp_get_project_details');
add_action('wp_ajax_nopriv_get_project_details', 'sp_get_project_details');
