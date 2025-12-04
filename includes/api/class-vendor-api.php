<?php
/**
 * Vendor API Class
 * 
 * Handles all vendor-specific AJAX endpoints.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Vendor_API extends KSC_API_Base {
    
    public function __construct() {
        // Bid management
        add_action('wp_ajax_submit_project_bid', [$this, 'submit_project_bid']);
        
        // Step management
        add_action('wp_ajax_vendor_upload_step', [$this, 'vendor_upload_step']);
        add_action('wp_ajax_nopriv_vendor_upload_step', [$this, 'vendor_upload_step']);
        add_action('wp_ajax_vendor_submit_step', [$this, 'vendor_submit_step']);
        
        // Profile & coverage
        add_action('wp_ajax_update_vendor_profile', [$this, 'update_vendor_profile']);
        add_action('wp_ajax_add_vendor_coverage', [$this, 'add_vendor_coverage']);
        
        // Earnings
        add_action('wp_ajax_get_vendor_earnings_chart_data', [$this, 'get_vendor_earnings_chart_data']);
    }
    
    /**
     * Submit project bid with coverage validation
     */
    public function submit_project_bid() {
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        check_ajax_referer('submit_bid_nonce_' . $project_id, 'submit_bid_nonce');
        
        $vendor_id = $this->verify_vendor_role();
        
        $bid_amount = isset($_POST['bid_amount']) ? floatval($_POST['bid_amount']) : 0;
        $bid_details = isset($_POST['bid_details']) ? sanitize_textarea_field($_POST['bid_details']) : '';
        $bid_type = isset($_POST['bid_type']) && in_array($_POST['bid_type'], ['open', 'hidden']) ? $_POST['bid_type'] : 'open';
        
        if (empty($project_id) || empty($bid_amount)) {
            wp_send_json_error(['message' => 'Project ID and bid amount are required.']);
        }
        
        // Check coverage area
        $coverage = $this->check_vendor_coverage($vendor_id, $project_id);
        
        if (!$coverage['has_coverage']) {
            wp_send_json_error([
                'message' => 'You can only submit bids for projects in your coverage area.',
                'coverage_needed' => true,
                'project_state' => $coverage['state'],
                'project_city' => $coverage['city']
            ]);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'project_bids';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'project_id' => $project_id,
                'vendor_id' => $vendor_id,
                'bid_amount' => $bid_amount,
                'bid_details' => $bid_details,
                'bid_type' => $bid_type,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%f', '%s', '%s', '%s']
        );
        
        if ($result) {
            $bid_id = $wpdb->insert_id;
            $project_title = get_the_title($project_id);
            $display_name = $this->get_vendor_display_name($vendor_id);
            
            // Notify admin
            $admin_users = get_users(['role' => 'administrator']);
            foreach ($admin_users as $admin) {
                SP_Notifications_Manager::create_notification([
                    'user_id' => $admin->ID,
                    'project_id' => $project_id,
                    'message' => sprintf('New bid received from %s on project "%s" - Amount: ₹%s', $display_name, $project_title, number_format($bid_amount, 2)),
                    'type' => 'bid_received',
                ]);
            }
            
            // Notify area manager
            $project = get_post($project_id);
            if ($project) {
                $author_id = $project->post_author;
                $author = get_userdata($author_id);
                if ($author && in_array('area_manager', (array)$author->roles)) {
                    SP_Notifications_Manager::create_notification([
                        'user_id' => $author_id,
                        'project_id' => $project_id,
                        'message' => sprintf('New bid from %s on your project "%s" - Amount: ₹%s', $display_name, $project_title, number_format($bid_amount, 2)),
                        'type' => 'bid_received',
                    ]);
                }
            }
            
            do_action('sp_bid_submitted', $bid_id, $project_id, $vendor_id, $bid_amount);
            
            wp_send_json_success(['message' => 'Bid submitted successfully!']);
        } else {
            wp_send_json_error(['message' => 'Failed to save bid to the database.']);
        }
    }
    
    /**
     * Upload step proof image and comment
     */
    public function vendor_upload_step() {
        check_ajax_referer('solar_upload_' . $_POST['step_id'], 'solar_nonce');
        
        $vendor_id = $this->verify_vendor_role();
        
        $step_id = intval($_POST['step_id']);
        $project_id = intval($_POST['project_id']);
        
        if (!isset($_FILES['step_image'])) {
            wp_send_json_error(['message' => 'No image uploaded']);
        }
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $attachment_id = media_handle_upload('step_image', $project_id);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => 'Upload failed: ' . $attachment_id->get_error_message()]);
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        $vendor_comment = sanitize_textarea_field($_POST['vendor_comment']);
        
        if (empty($vendor_comment)) {
            wp_send_json_error(['message' => 'Comment required']);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'solar_process_steps';
        
        $wpdb->update(
            $table,
            [
                'image_url' => $image_url,
                'vendor_comment' => $vendor_comment,
                'admin_status' => 'under_review',  // Changed from 'pending' to show it's submitted
                'updated_at' => current_time('mysql')
            ],
            ['id' => $step_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        do_action('sp_vendor_step_submitted', $step_id, $project_id);
        
        $this->check_and_update_project_status($project_id);
        
        wp_send_json_success(['message' => 'Step submitted successfully! The page will now reload.']);
    }
    
    /**
     * Vendor submit step (alias for vendor_upload_step)
     */
    public function vendor_submit_step() {
        return $this->vendor_upload_step();
    }
    
    /**
     * Update vendor profile (company name, phone)
     */
    public function update_vendor_profile() {
        $vendor_id = $this->verify_vendor_role();
        
        $company_name = isset($_POST['company_name']) ? sanitize_text_field($_POST['company_name']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        
        update_user_meta($vendor_id, 'company_name', $company_name);
        update_user_meta($vendor_id, 'phone', $phone);
        
        wp_send_json_success(['message' => 'Profile updated successfully.']);
    }
    
    /**
     * Add vendor coverage area (after payment)
     */
    public function add_vendor_coverage() {
        $vendor_id = $this->verify_vendor_role();
        
        $payment_response = json_decode(stripslashes($_POST['payment_response']), true);
        $states = isset($_POST['states']) && is_array($_POST['states']) ? $_POST['states'] : [];
        $cities = isset($_POST['cities']) && is_array($_POST['cities']) ? $_POST['cities'] : [];
        
        if (empty($payment_response) || !isset($payment_response['razorpay_payment_id'])) {
            wp_send_json_error(['message' => 'Invalid payment data']);
        }
        
        // Store payment record
        global $wpdb;
        $table = $wpdb->prefix . 'solar_vendor_payments';
        $wpdb->insert($table, [
            'vendor_id' => $vendor_id,
            'razorpay_payment_id' => $payment_response['razorpay_payment_id'],
            'razorpay_order_id' => $payment_response['razorpay_order_id'],
            'amount' => floatval($_POST['amount']),
            'states_purchased' => json_encode($states),
            'cities_purchased' => json_encode($cities),
            'payment_status' => 'completed',
            'payment_date' => current_time('mysql')
        ]);
        
        // Update user meta - append new coverage
        $current_states = get_user_meta($vendor_id, 'purchased_states', true) ?: [];
        $current_cities = get_user_meta($vendor_id, 'purchased_cities', true) ?: [];
        
        // Normalize states to string array (handle both object and string formats)
        $current_states_normalized = array_map(function($state) {
            return is_array($state) && isset($state['state']) ? $state['state'] : $state;
        }, $current_states);
        
        // Normalize cities to string array (handle both object and string formats)
        $current_cities_normalized = array_map(function($city) {
            if (is_array($city) && isset($city['city'])) {
                return $city['city'];  // Extract city name from object
            }
            return $city;  // Already a string
        }, $current_cities);
        
        // Merge and deduplicate (array_unique works properly with strings)
        $new_states = array_values(array_unique(array_merge($current_states_normalized, $states)));
        $new_cities = array_values(array_unique(array_merge($current_cities_normalized, $cities)));
        
        update_user_meta($vendor_id, 'purchased_states', $new_states);
        update_user_meta($vendor_id, 'purchased_cities', $new_cities);
        
        wp_send_json_success(['message' => 'Coverage added successfully.']);
    }
    
    /**
     * Get vendor earnings chart data
     */
    public function get_vendor_earnings_chart_data() {
        $vendor_id = $this->verify_vendor_role();
        
        global $wpdb;
        
        // Get projects assigned to this vendor
        $projects = get_posts([
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_assigned_vendor_id',
                    'value' => $vendor_id,
                    'compare' => '='
                ]
            ]
        ]);
        
        $earnings_by_month = [];
        
        foreach ($projects as $project) {
            $project_id = $project->ID;
            $vendor_paid = floatval(get_post_meta($project_id, '_vendor_paid_amount', true) ?: 0);
            $month = date('Y-m', strtotime($project->post_date));
            
            if (!isset($earnings_by_month[$month])) {
                $earnings_by_month[$month] = 0;
            }
            $earnings_by_month[$month] += $vendor_paid;
        }
        
        // Sort by month
        ksort($earnings_by_month);
        
        $labels = [];
        $data = [];
        
        foreach ($earnings_by_month as $month => $amount) {
            $labels[] = date('M Y', strtotime($month . '-01'));
            $data[] = $amount;
        }
        
        wp_send_json_success([
            'labels' => $labels,
            'data' => $data
        ]);
    }
}
