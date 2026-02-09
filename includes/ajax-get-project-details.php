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
        // error_log('Project details nonce failed');
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
    $client_user_id = get_post_meta($project_id, '_client_user_id', true);
    
    // Initialize client data
    $client_name = 'N/A';
    $client_email = 'N/A';
    $client_phone = get_post_meta($project_id, '_client_phone_number', true) ?: 'N/A';
    $client_address = get_post_meta($project_id, '_client_address', true) ?: 'N/A';
    
    // Get client details from WordPress user if available
    if ($client_user_id) {
        $client_user = get_userdata($client_user_id);
        if ($client_user) {
            // Try to get first name from user meta first
            $first_name = get_user_meta($client_user_id, 'first_name', true);
            $last_name = get_user_meta($client_user_id, 'last_name', true);
            
            if ($first_name) {
                $client_name = $first_name . ($last_name ? ' ' . $last_name : '');
            } else {
                // Fallback to display name or username
                $client_name = $client_user->display_name ?: $client_user->user_login;
            }
            
            $client_email = $client_user->user_email;
            
            // Try to get phone from user meta if not in project meta
            if ($client_phone === 'N/A') {
                $user_phone = get_user_meta($client_user_id, 'phone', true);
                if ($user_phone) {
                    $client_phone = $user_phone;
                }
            }
        }
    }
    
    // Get vendor details
    $vendor_id = get_post_meta($project_id, '_assigned_vendor_id', true);
    $vendor_name = 'Not assigned';
    if ($vendor_id) {
        $vendor_user = get_userdata($vendor_id);
        if ($vendor_user) {
            $vendor_name = $vendor_user->display_name;
        }
    }
    
    $data = [
        'id' => $project_id,
        'project_id' => $project_id,  // Add this for modal navigation
        'title' => get_the_title($project_id),
        'status' => get_post_meta($project_id, 'project_status', true),
        'project_state' => get_post_meta($project_id, '_project_state', true),
        'project_city' => get_post_meta($project_id, '_project_city', true),
        'solar_system_size_kw' => get_post_meta($project_id, '_solar_system_size_kw', true),
        'total_cost' => get_post_meta($project_id, '_total_project_cost', true),
        'start_date' => get_post_meta($project_id, '_project_start_date', true),
        'client_name' => $client_name,
        'client_email' => $client_email,
        'client_phone_number' => $client_phone,
        'client_address' => $client_address,
        'vendor_name' => $vendor_name,
        'vendor_paid_amount' => get_post_meta($project_id, '_vendor_paid_amount', true) ?: '0',
        'company_profit' => get_post_meta($project_id, '_company_profit', true) ?: '0',
        'steps' => []
    ];
    
    // Get process steps if they exist
    global $wpdb;
    $steps_table = $wpdb->prefix . 'solar_process_steps';
    if ($wpdb->get_var("SHOW TABLES LIKE '$steps_table'") == $steps_table) {
        $steps = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $steps_table WHERE project_id = %d ORDER BY id ASC",
            $project_id
        ), ARRAY_A);
        $data['steps'] = $steps ?: [];
        // error_log('Project ' . $project_id . ' steps count: ' . count($data['steps']));
    }
    
    wp_send_json_success($data);
}

// Register the AJAX handler
add_action('wp_ajax_get_project_details', 'sp_get_project_details');
add_action('wp_ajax_nopriv_get_project_details', 'sp_get_project_details');
