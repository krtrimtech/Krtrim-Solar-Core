<?php
/**
 * Admin and Manager API Class
 * 
 * Handles all admin and area manager AJAX endpoints.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Admin_Manager_API extends KSC_API_Base {
    
    public function __construct() {
        // Dashboard stats
        add_action('wp_ajax_get_area_manager_dashboard_stats', [$this, 'get_area_manager_dashboard_stats']);
        add_action('wp_ajax_get_area_manager_data', [$this, 'get_area_manager_data']);
        
        // Project management
        add_action('wp_ajax_create_solar_project', [$this, 'create_solar_project']);
        add_action('wp_ajax_update_solar_project', [$this, 'update_solar_project']);
        add_action('wp_ajax_get_area_manager_projects', [$this, 'get_area_manager_projects']);
        add_action('wp_ajax_get_area_manager_project_details', [$this, 'get_area_manager_project_details']);
        
        // Client management
        add_action('wp_ajax_create_client_from_dashboard', [$this, 'create_client_from_dashboard']);
        add_action('wp_ajax_get_area_manager_clients', [$this, 'get_area_manager_clients']);
        add_action('wp_ajax_reset_client_password', [$this, 'reset_client_password']);
        add_action('wp_ajax_record_client_payment', [$this, 'record_client_payment']);
        
        // Vendor management
        add_action('wp_ajax_create_vendor_from_dashboard', [$this, 'create_vendor_from_dashboard']);
        add_action('wp_ajax_get_area_manager_vendor_approvals', [$this, 'get_area_manager_vendor_approvals']);
        add_action('wp_ajax_update_vendor_status', [$this, 'update_vendor_status']);
        add_action('wp_ajax_update_vendor_details', [$this, 'update_vendor_details']);
        
        
        // Bid management
        add_action('wp_ajax_award_project_to_vendor', [$this, 'award_project_to_vendor']);
        add_action('wp_ajax_get_area_manager_bids', [$this, 'get_area_manager_bids']);
        
        // Reviews
        add_action('wp_ajax_get_area_manager_reviews', [$this, 'get_area_manager_reviews']);
        add_action('wp_ajax_review_vendor_submission', [$this, 'review_vendor_submission']);
        
        // Lead management
        add_action('wp_ajax_get_area_manager_leads', [$this, 'get_area_manager_leads']);
        add_action('wp_ajax_create_solar_lead', [$this, 'create_solar_lead']);
        add_action('wp_ajax_delete_solar_lead', [$this, 'delete_solar_lead']);
        add_action('wp_ajax_send_lead_message', [$this, 'send_lead_message']);
        
        // Marketplace
        add_action('wp_ajax_filter_marketplace_projects', [$this, 'filter_marketplace_projects']);
        add_action('wp_ajax_nopriv_filter_marketplace_projects', [$this, 'filter_marketplace_projects']);
        
        // Location assignment
        add_action('wp_ajax_assign_area_manager_location', [$this, 'assign_area_manager_location']);
        
        // Team assignment
        add_action('wp_ajax_assign_team_to_area_manager', [$this, 'assign_team_to_area_manager']);
    }
    
    /**
     * Get area manager dashboard statistics
     */
    public function get_area_manager_dashboard_stats() {
        $manager = $this->verify_area_manager_role();
        
        global $wpdb;
        
        // Get projects
        $projects = get_posts([
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'author' => $manager->ID,
            'fields' => 'ids'
        ]);
        
        $total_projects = count($projects);
        $total_revenue = 0;
        $total_costs = 0;
        $total_profit = 0;
        $total_client_payments = 0;
        $total_outstanding = 0;
        
        foreach ($projects as $project_id) {
            $total_cost = floatval(get_post_meta($project_id, '_total_project_cost', true) ?: 0);
            $vendor_paid = floatval(get_post_meta($project_id, '_vendor_paid_amount', true) ?: 0);
            $client_paid = floatval(get_post_meta($project_id, '_paid_amount', true) ?: 0);
            
            $total_revenue += $total_cost;
            $total_costs += $vendor_paid;
            $total_client_payments += $client_paid;
            $total_outstanding += ($total_cost - $client_paid);
        }
        
        $total_profit = $total_revenue - $total_costs;
        $profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;
        $collection_rate = $total_revenue > 0 ? ($total_client_payments / $total_revenue) * 100 : 0;
        
        // Get leads count
        $total_leads = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'solar_lead' AND post_author = %d",
            $manager->ID
        ));
        
        // Get pending reviews count
        $pending_reviews = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}solar_process_steps ps 
             JOIN {$wpdb->posts} p ON ps.project_id = p.ID 
             WHERE p.post_author = %d AND ps.admin_status = 'under_review'",
            $manager->ID
        ));
        
        wp_send_json_success([
            'total_projects' => $total_projects,
            'total_revenue' => round($total_revenue, 2),
            'total_costs' => round($total_costs, 2),
            'total_profit' => round($total_profit, 2),
            'profit_margin' => round($profit_margin, 2),
            'total_client_payments' => round($total_client_payments, 2),
            'total_outstanding' => round($total_outstanding, 2),
            'collection_rate' => round($collection_rate, 2),
            'total_leads' => intval($total_leads),
            'pending_reviews' => intval($pending_reviews)
        ]);
    }
    
    /**
     * Create solar project
     */
    public function create_solar_project() {
        check_ajax_referer('sp_create_project_nonce_field', 'sp_create_project_nonce');
        
        $manager = $this->verify_area_manager_role();
        $data = $_POST;
        
        $project_data = [
            'post_title' => sanitize_text_field($data['project_title']),
            'post_content' => isset($data['project_description']) ? wp_kses_post($data['project_description']) : '',
            'post_status' => 'publish',
            'post_author' => $manager->ID,
            'post_type' => 'solar_project',
        ];
        
        $project_id = wp_insert_post($project_data);
        
        if (is_wp_error($project_id)) {
            wp_send_json_error(['message' => 'Could not create project: ' . $project_id->get_error_message()]);
        }
        
        // Vendor assignment method
        if (isset($data['vendor_assignment_method'])) {
            $method = sanitize_text_field($data['vendor_assignment_method']);
            update_post_meta($project_id, '_vendor_assignment_method', $method);
            
            if ($method === 'manual' && isset($data['assigned_vendor_id']) && !empty($data['assigned_vendor_id'])) {
                update_post_meta($project_id, '_assigned_vendor_id', sanitize_text_field($data['assigned_vendor_id']));
                update_post_meta($project_id, 'project_status', 'assigned');
                
                // Create default process steps (same as bidding flow)
                $this->create_default_process_steps($project_id);
            }
        }
        
        // Save meta fields
        $fields = [
            'project_state', 'project_city', 'project_status', 'client_user_id',
            'solar_system_size_kw', 'client_address', 'client_phone_number',
            'project_start_date', 'paid_amount'
        ];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $meta_key = ($field === 'project_status') ? 'project_status' : ('_' . $field);
        update_post_meta($project_id, $meta_key, sanitize_text_field($data[$field]));
            }
        }
        
        // Financial data
        $total_cost = isset($data['total_project_cost']) ? floatval($data['total_project_cost']) : 0;
        update_post_meta($project_id, '_total_project_cost', $total_cost);
        
        if (isset($data['paid_to_vendor']) && !empty($data['paid_to_vendor'])) {
            $vendor_paid = floatval($data['paid_to_vendor']);
            update_post_meta($project_id, '_vendor_paid_amount', $vendor_paid);
            
            $profit = $total_cost - $vendor_paid;
            $margin = $total_cost > 0 ? ($profit / $total_cost) * 100 : 0;
            update_post_meta($project_id, '_company_profit', $profit);
            update_post_meta($project_id, '_profit_margin_percentage', $margin);
        }
        
        // Create default steps
        global $wpdb;
        $steps_table = $wpdb->prefix . 'solar_process_steps';
        $default_steps = get_option('sp_default_process_steps', [
            'Site Visit', 'Design Approval', 'Material Delivery',
            'Installation', 'Grid Connection', 'Final Inspection'
        ]);
        
        foreach ($default_steps as $index => $step_name) {
            $wpdb->insert($steps_table, [
                'project_id' => $project_id,
                'step_number' => $index + 1,
                'step_name' => $step_name,
                'admin_status' => 'pending',
                'created_at' => current_time('mysql'),
            ]);
        }
        
        // Notify client
        $client_id = isset($data['client_user_id']) ? sanitize_text_field($data['client_user_id']) : '';
        if ($client_id) {
            SP_Notifications_Manager::create_notification([
                'user_id' => $client_id,
                'project_id' => $project_id,
                'message' => sprintf('Your solar project "%s" has been created', $project_data['post_title']),
                'type' => 'project_created',
            ]);
        }
        
        wp_send_json_success(['message' => 'Project created successfully!', 'project_id' => $project_id]);
    }

    /**
     * Update solar project
     */
    public function update_solar_project() {
        check_ajax_referer('sp_update_project_nonce', 'sp_update_project_nonce');
        
        $manager = $this->verify_area_manager_role();
        $data = $_POST;
        
        $project_id = isset($data['project_id']) ? intval($data['project_id']) : 0;
        
        if (!$project_id) {
            wp_send_json_error(['message' => 'Project ID is required']);
        }
        
        // Verify project exists and belongs to this manager
        $project = get_post($project_id);
        if (!$project || $project->post_type !== 'solar_project') {
            wp_send_json_error(['message' => 'Invalid project']);
        }
        
        if ($project->post_author != $manager->ID) {
            wp_send_json_error(['message' => 'You do not have permission to edit this project']);
        }
        
        // Update post data
        $project_data = [
            'ID' => $project_id,
            'post_title' => sanitize_text_field($data['project_title']),
            'post_content' => isset($data['project_description']) ? wp_kses_post($data['project_description']) : '',
        ];
        
        $updated = wp_update_post($project_data);
        
        if (is_wp_error($updated)) {
            wp_send_json_error(['message' => 'Could not update project: ' . $updated->get_error_message()]);
        }
        
        // Update meta fields
        $fields = [
            'project_state', 'project_city', 'project_status', 'client_user_id',
            'solar_system_size_kw', 'client_address', 'client_phone_number',
            'project_start_date', 'paid_amount', 'vendor_assignment_method'
        ];
        
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $meta_key = ($field === 'project_status') ? 'project_status' : ('_' . $field);
                update_post_meta($project_id, $meta_key, sanitize_text_field($data[$field]));
            }
        }
        
        // Financial data
        if (isset($data['total_project_cost'])) {
            $total_cost = floatval($data['total_project_cost']);
            update_post_meta($project_id, '_total_project_cost', $total_cost);
            
            // Recalculate profit if vendor payment exists
            $vendor_paid = floatval(get_post_meta($project_id, '_vendor_paid_amount', true));
            if ($vendor_paid > 0) {
                $profit = $total_cost - $vendor_paid;
                $margin = $total_cost > 0 ? ($profit / $total_cost) * 100 : 0;
                update_post_meta($project_id, '_company_profit', $profit);
                update_post_meta($project_id, '_profit_margin_percentage', $margin);
            }
        }
        
        // Update vendor assignment if changed
        if (isset($data['vendor_assignment_method'])) {
            $method = sanitize_text_field($data['vendor_assignment_method']);
            update_post_meta($project_id, '_vendor_assignment_method', $method);
            
            if ($method === 'manual' && isset($data['assigned_vendor_id']) && !empty($data['assigned_vendor_id'])) {
                update_post_meta($project_id, '_assigned_vendor_id', sanitize_text_field($data['assigned_vendor_id']));
                // Auto-update status to 'assigned' when vendor is manually assigned
                update_post_meta($project_id, 'project_status', 'assigned');
                
                if (isset($data['paid_to_vendor']) && !empty($data['paid_to_vendor'])) {
                    $vendor_paid = floatval($data['paid_to_vendor']);
                    update_post_meta($project_id, '_vendor_paid_amount', $vendor_paid);
                    
                    $total_cost = floatval(get_post_meta($project_id, '_total_project_cost', true));
                    $profit = $total_cost - $vendor_paid;
                    $margin = $total_cost > 0 ? ($profit / $total_cost) * 100 : 0;
                    update_post_meta($project_id, '_company_profit', $profit);
                    update_post_meta($project_id, '_profit_margin_percentage', $margin);
                }
            }
        }
        
        wp_send_json_success(['message' => 'Project updated successfully!', 'project_id' => $project_id]);
    }
    
    /**
     * Award project to vendor (after bid selection)
     */
    public function award_project_to_vendor() {
        check_ajax_referer('award_bid_nonce', 'nonce');
        
        $auth = $this->verify_admin_or_manager();
        $current_user = $auth['user'];
        $is_admin = $auth['is_admin'];
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        $bid_amount = isset($_POST['bid_amount']) ? floatval($_POST['bid_amount']) : 0;
        
        if (empty($project_id) || empty($vendor_id)) {
            wp_send_json_error(['message' => 'Invalid project or vendor ID.']);
        }
        
        $project = get_post($project_id);
        
        if (!$project) {
            wp_send_json_error(['message' => 'Project not found.']);
        }
        
        // Area managers can only award their own projects
        if (!$is_admin && $project->post_author != $current_user->ID) {
            wp_send_json_error(['message' => 'You do not have permission to award this project.']);
        }
        
        update_post_meta($project_id, '_assigned_vendor_id', $vendor_id);
        update_post_meta($project_id, 'winning_bid_amount', $bid_amount);
        update_post_meta($project_id, 'project_status', 'assigned');
        
        // Create default process steps for the project
        $this->create_default_process_steps($project_id);
        
        // Notify vendor
        $vendor = get_userdata($vendor_id);
        $project_title = get_the_title($project_id);
        
        if ($vendor) {
            $notification_options = get_option('sp_notification_options');
            $vendor_phone = get_user_meta($vendor_id, 'phone', true);
            
            $whatsapp_data = null;
            if (isset($notification_options['whatsapp_enable']) && !empty($vendor_phone)) {
                $message = "Congratulations! Your bid of ₹" . number_format($bid_amount, 2) . " for project '" . $project_title . "' has been accepted.";
                $whatsapp_data = [
                    'phone' => '91' . preg_replace('/\D/', '', $vendor_phone),
                    'message' => urlencode($message)
                ];
            }
        }
        
        // Notify client
        $client_id = get_post_meta($project_id, '_client_user_id', true);
        if ($client_id) {
            $vendor_name = $this->get_vendor_display_name($vendor_id);
            SP_Notifications_Manager::create_notification([
                'user_id' => $client_id,
                'project_id' => $project_id,
                'message' => sprintf('Vendor "%s" has been assigned to your project', $vendor_name),
                'type' => 'vendor_assigned',
            ]);
        }

        // Trigger project awarded action (for automated notifications)
        do_action('sp_project_awarded', $project_id, $vendor_id);
        
        wp_send_json_success([
            'message' => 'Project awarded successfully!',
            'whatsapp_data' => $whatsapp_data ?? null
        ]);
    }
    
    /**
     * Create default process steps for a newly assigned project
     * 
     * @param int $project_id
     * @return bool Success status
     */
    public function create_default_process_steps($project_id) {
        // Get default steps from template
        $default_steps = get_option('sp_default_process_steps', [
            'Site Visit',
            'Design Approval',
            'Material Delivery',
            'Installation',
            'Grid Connection',
            'Final Inspection'
        ]);
        
        if (empty($default_steps)) {
            error_log("No default process steps found for project {$project_id}");
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'solar_process_steps';
        
        // Check if steps already exist (prevent duplicates)
        $existing_steps = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE project_id = %d",
            $project_id
        ));
        
        if ($existing_steps > 0) {
            error_log("Steps already exist for project {$project_id}, skipping creation");
            return false;
        }
        
        // Insert each step
        $success_count = 0;
        foreach ($default_steps as $index => $step_name) {
            $result = $wpdb->insert(
                $table,
                [
                    'project_id' => $project_id,
                    'step_number' => $index + 1,
                    'step_name' => sanitize_text_field($step_name),
                    'admin_status' => 'pending',
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%s', '%s']
            );
            
            if ($result) {
                $success_count++;
            } else {
                error_log("Failed to create step: {$step_name} for project {$project_id}");
            }
        }
        
        error_log("Created {$success_count} steps for project {$project_id}");
        
        return $success_count > 0;
    }
    
    /**
     * Review vendor step submission
     */
    public function review_vendor_submission() {
        check_ajax_referer('sp_review_nonce', 'nonce');
        
        $auth = $this->verify_admin_or_manager();
        $manager = $auth['user'];
        
        $step_id = isset($_POST['step_id']) ? intval($_POST['step_id']) : 0;
        $decision = isset($_POST['decision']) && in_array($_POST['decision'], ['approved', 'rejected']) ? $_POST['decision'] : '';
        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
        
        if (empty($step_id) || empty($decision)) {
            wp_send_json_error(['message' => 'Invalid step ID or decision.']);
        }
        
        global $wpdb;
        $steps_table = $wpdb->prefix . 'solar_process_steps';
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT project_id FROM {$steps_table} WHERE id = %d",
            $step_id
        ));
        
        if (!$submission) {
            wp_send_json_error(['message' => 'Invalid submission.']);
        }
        
        $project = get_post($submission->project_id);
        
        if (!$project || $project->post_author != $manager->ID) {
            wp_send_json_error(['message' => 'You do not have permission to review this submission.']);
        }
        
        $result = SP_Process_Steps_Manager::process_step_review($step_id, $decision, $comment, $manager->ID);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Submission status updated successfully.',
                'whatsapp_data' => $result['whatsapp_data']
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
    
    /**
     * Filter marketplace projects (public and AJAX)
     */
    public function filter_marketplace_projects() {
        if (ob_get_length()) ob_clean();
        
        try {
            $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
            $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
            $coverage_only = isset($_POST['coverage_only']) && $_POST['coverage_only'] === '1';
            
            $args = [
                'post_type' => 'solar_project',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_vendor_assignment_method',
                        'value' => 'bidding',
                        'compare' => '='
                    ]
                ]
            ];
            
            if (!empty($state)) {
                $args['meta_query'][] = [
                    'key' => '_project_state',
                    'value' => $state,
                    'compare' => '='
                ];
            }
            
            if (!empty($city)) {
            $args['meta_query'][] = [
                'key' => '_project_city',
                'value' => $city,
                'compare' => '='
            ];
        }
        
        // Only show projects with 'pending' status in marketplace
        // Once project is assigned/in_progress/completed, it should not appear
        $args['meta_query'][] = [
            'key' => 'project_status',
            'value' => 'pending',
            'compare' => '='
        ];
        
        $query = new WP_Query($args);
            
            // Check if vendor and get coverage
            $vendor_id = 0;
            $purchased_states = [];
            $purchased_cities = [];
            
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                if (in_array('solar_vendor', (array) $user->roles)) {
                    $vendor_id = $user->ID;
                    $purchased_states = get_user_meta($vendor_id, 'purchased_states', true) ?: [];
                    $purchased_cities = get_user_meta($vendor_id, 'purchased_cities', true) ?: [];
                }
            }
            
            if ($query->have_posts()) {
                $filtered_projects = [];
                
                while ($query->have_posts()) {
                    $query->the_post();
                    $project_id = get_the_ID();
                    
                    // Check coverage for this project
                    $has_coverage = false;
                    if ($vendor_id) {
                        $project_state = get_post_meta($project_id, '_project_state', true);
                        $project_city = get_post_meta($project_id, '_project_city', true);
                        
                        $has_state = in_array($project_state, $purchased_states);
                        
                        // Check city coverage
                        $has_city = false;
                        if (is_array($purchased_cities)) {
                            foreach ($purchased_cities as $city_obj) {
                                if (is_array($city_obj) && isset($city_obj['city']) && $city_obj['city'] === $project_city) {
                                    $has_city = true;
                                    break;
                                } elseif (is_string($city_obj) && $city_obj === $project_city) {
                                    $has_city = true;
                                    break;
                                }
                            }
                        }
                        
                        $has_coverage = $has_state || $has_city;
                    }
                    
                    // If coverage_only filter is enabled, skip projects outside coverage
                    if ($coverage_only && !$has_coverage) {
                        continue;
                    }
                    
                    // Store project data with coverage status
                    $filtered_projects[] = [
                        'post' => get_post($project_id),
                        'has_coverage' => $has_coverage,
                        'is_vendor' => (bool) $vendor_id
                    ];
                }
                
                // Render filtered projects
                if (!empty($filtered_projects)) {
                    ob_start();
                    foreach ($filtered_projects as $project_data) {
                        global $post;
                        $post = $project_data['post'];
                        setup_postdata($post);
                        
                        // Make coverage data available to template
                        set_query_var('has_coverage', $project_data['has_coverage']);
                        set_query_var('is_vendor', $project_data['is_vendor']);
                        
                        // Render project card HTML
                        require plugin_dir_path(dirname(__FILE__)) . '../public/views/partials/marketplace-card.php';
                    }
                    wp_reset_postdata();
                    $html = ob_get_clean();
                    wp_send_json_success(['html' => $html, 'count' => count($filtered_projects)]);
                } else {
                    wp_send_json_success(['html' => '', 'count' => 0]);
                }
            } else {
                wp_send_json_success(['html' => '', 'count' => 0]);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'An error occurred while filtering projects.']);
        }
    }
    
    /**
     * Get area manager data (wrapper for dashboard stats)
     */
    public function get_area_manager_data() {
        return $this->get_area_manager_dashboard_stats();
    }
    
    /**
     * Get all projects for area manager
     */
    public function get_area_manager_projects() {
        check_ajax_referer('get_projects_nonce', 'nonce');
        
        $manager = $this->verify_area_manager_role();
        
        $args = [
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'author' => $manager->ID,
            'post_status' => 'publish'
        ];
        
        $query = new WP_Query($args);
        $projects = [];
        
        if ($query->have_posts()) {
            global $wpdb;
            
            while ($query->have_posts()) {
                $query->the_post();
                $project_id = get_the_ID();
                
                // Get pending submissions count
                $pending_submissions = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}solar_process_steps 
                     WHERE project_id = %d AND admin_status = 'under_review'",
                    $project_id
                ));
                
                $vendor_id = get_post_meta($project_id, '_assigned_vendor_id', true);
                $vendor_name = '';
                if ($vendor_id) {
                    $vendor_name = $this->get_vendor_display_name($vendor_id);
                }
                
                $projects[] = [
                    'id' => $project_id,
                    'title' => get_the_title(),
                    'status' => get_post_meta($project_id, 'project_status', true) ?: 'pending',
                    'project_city' => get_post_meta($project_id, '_project_city', true),
                    'project_state' => get_post_meta($project_id, '_project_state', true),
                    'solar_system_size_kw' => get_post_meta($project_id, '_solar_system_size_kw', true),
                    'total_cost' => get_post_meta($project_id, '_total_project_cost', true) ?: 0,
                    'paid_amount' => get_post_meta($project_id, '_paid_amount', true) ?: 0,
                    'vendor_name' => $vendor_name,
                    'pending_submissions' => intval($pending_submissions),
                    'created_at' => get_the_date('Y-m-d H:i:s'),
                    'start_date' => get_post_meta($project_id, '_project_start_date', true),
                ];
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success(['projects' => $projects]);
    }
    
    /**
     * Get detailed project information
     */
    public function get_area_manager_project_details() {
        check_ajax_referer('sp_project_details_nonce', 'nonce');
        
        $manager = $this->verify_area_manager_role();
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$project_id) {
            wp_send_json_error(['message' => 'Project ID required']);
        }
        
        $project = get_post($project_id);
        
        if (!$project || $project->post_type !== 'solar_project') {
            wp_send_json_error(['message' => 'Invalid project']);
        }
        
        if ($project->post_author != $manager->ID) {
            wp_send_json_error(['message' => 'You do not have permission to view this project']);
        }
        
        // Get all project metadata
        $meta_data = [
            'project_status' => get_post_meta($project_id, 'project_status', true),
            'project_state' => get_post_meta($project_id, '_project_state', true),
            'project_city' => get_post_meta($project_id, '_project_city', true),
            'client_user_id' => get_post_meta($project_id, '_client_user_id', true),
            'solar_system_size_kw' => get_post_meta($project_id, '_solar_system_size_kw', true),
            'client_address' => get_post_meta($project_id, '_client_address', true),
            'client_phone_number' => get_post_meta($project_id, '_client_phone_number', true),
            'total_project_cost' => get_post_meta($project_id, '_total_project_cost', true),
            'paid_amount' => get_post_meta($project_id, '_paid_amount', true),
            'vendor_paid_amount' => get_post_meta($project_id, '_vendor_paid_amount', true),
            'assigned_vendor_id' => get_post_meta($project_id, '_assigned_vendor_id', true),
            'vendor_assignment_method' => get_post_meta($project_id, '_vendor_assignment_method', true),
            'project_start_date' => get_post_meta($project_id, '_project_start_date', true),
        ];
        
        // Get process steps
        global $wpdb;
        $steps = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}solar_process_steps WHERE project_id = %d ORDER BY step_number ASC",
            $project_id
        ), ARRAY_A);
        
        // Get bids
        $bids = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}project_bids WHERE project_id = %d ORDER BY created_at DESC",
            $project_id
        ), ARRAY_A);
        
        // Add vendor names to bids
        foreach ($bids as &$bid) {
            $bid['vendor_name'] = $this->get_vendor_display_name($bid['vendor_id']);
        }
        
        // Get assigned vendor details
        $assigned_vendor = null;
        if (!empty($meta_data['assigned_vendor_id'])) {
            $vendor = get_userdata($meta_data['assigned_vendor_id']);
            if ($vendor) {
                $assigned_vendor = [
                    'id' => $vendor->ID,
                    'name' => $this->get_vendor_display_name($vendor->ID),
                    'email' => $vendor->user_email,
                    'company_name' => get_user_meta($vendor->ID, 'company_name', true),
                    'phone' => get_user_meta($vendor->ID, 'phone', true),
                ];
            }
        }
        
        // Get client details
        $client_data = null;
        if (!empty($meta_data['client_user_id'])) {
            $client = get_userdata($meta_data['client_user_id']);
            if ($client) {
                $client_data = [
                    'id' => $client->ID,
                    'name' => $client->display_name,
                    'email' => $client->user_email,
                ];
            }
        }
        
        wp_send_json_success([
            'project' => [
                'id' => $project->ID,
                'title' => $project->post_title,
                'description' => $project->post_content,
                'created_at' => $project->post_date,
            ],
            'meta' => $meta_data,
            'steps' => $steps,
            'bids' => $bids,
            'assigned_vendor' => $assigned_vendor,
            'client' => $client_data,
        ]);
    }
    
    /**
     * Create client from dashboard
     */
    public function create_client_from_dashboard() {
        check_ajax_referer('create_client_nonce', 'nonce');
        
        $manager = $this->verify_area_manager_role();
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $username = isset($_POST['username']) ? sanitize_user($_POST['username']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($name) || empty($username) || empty($email) || empty($password)) {
            wp_send_json_error(['message' => 'All fields are required']);
        }
        
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email address']);
        }
        
        if (username_exists($username)) {
            wp_send_json_error(['message' => 'Username already exists']);
        }
        
        if (email_exists($email)) {
            wp_send_json_error(['message' => 'Email already registered']);
        }
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }
        
        $user = new WP_User($user_id);
        $user->set_role('solar_client');
        
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $name,
        ]);
        
        // Store who created this client
        update_user_meta($user_id, 'created_by_manager', $manager->ID);
        
        wp_send_json_success(['message' => 'Client created successfully', 'user_id' => $user_id]);
    }
    
    /**
     * Get clients created by area manager
     */
    public function get_area_manager_clients() {
        check_ajax_referer('get_clients_nonce', 'nonce');
        
        $manager = $this->verify_area_manager_role();
        
        // Get all solar_client users created by this manager
        $args = [
            'role' => 'solar_client',
            'meta_query' => [
                [
                    'key' => 'created_by_manager',
                    'value' => $manager->ID,
                    'compare' => '='
                ]
            ]
        ];
        
        $user_query = new WP_User_Query($args);
        $clients = [];
        
        if (!empty($user_query->results)) {
            foreach ($user_query->results as $user) {
                $clients[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                ];
            }
        }
        
        wp_send_json_success(['clients' => $clients]);
    }
    
    /**
     * Reset client password
     */
    public function reset_client_password() {
        check_ajax_referer('reset_password_nonce', 'nonce');
        
        $manager = $this->verify_area_manager_role();
        
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        
        if (!$client_id || empty($new_password)) {
            wp_send_json_error(['message' => 'Client ID and new password are required']);
        }
        
        // Verify this client was created by this manager
        $created_by = get_user_meta($client_id, 'created_by_manager', true);
        if ($created_by != $manager->ID) {
            wp_send_json_error(['message' => 'You do not have permission to reset this password']);
        }
        
        wp_set_password($new_password, $client_id);
        
        wp_send_json_success(['message' => 'Password reset successfully']);
    }
    
    /**
     * Record client payment
     */
    public function record_client_payment() {
        // Note: No specific nonce for this yet - relying on permission checks
        // check_ajax_referer('record_payment_nonce', 'nonce');
        
        $auth = $this->verify_admin_or_manager();
        $manager = $auth['user'];
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $payment_date = isset($_POST['payment_date']) ? sanitize_text_field($_POST['payment_date']) : current_time('Y-m-d');
        
        if (!$project_id || $amount <= 0) {
            wp_send_json_error(['message' => 'Invalid project ID or amount']);
        }
        
        $project = get_post($project_id);
        
        if (!$project || $project->post_type !== 'solar_project') {
            wp_send_json_error(['message' => 'Invalid project']);
        }
        
        // Area managers can only record payments for their own projects
        if (!$auth['is_admin'] && $project->post_author != $manager->ID) {
            wp_send_json_error(['message' => 'You do not have permission to record payments for this project']);
        }
        
        $current_paid = floatval(get_post_meta($project_id, '_paid_amount', true) ?: 0);
        $new_paid = $current_paid + $amount;
        
        update_post_meta($project_id, '_paid_amount', $new_paid);
        
        // Create notification for client
        $client_id = get_post_meta($project_id, '_client_user_id', true);
        if ($client_id) {
            SP_Notifications_Manager::create_notification([
                'user_id' => $client_id,
                'project_id' => $project_id,
                'message' => sprintf('Payment of ₹%s recorded for your project', number_format($amount, 2)),
                'type' => 'payment_recorded',
            ]);
        }
        
        wp_send_json_success(['message' => 'Payment recorded successfully', 'new_total' => $new_paid]);
    }
    
    /**
     * Create vendor from dashboard
     */
    public function create_vendor_from_dashboard() {
        // Note: No specific nonce required - admin/manager only function
        // check_ajax_referer('create_vendor_nonce', 'nonce');
        
        $this->verify_admin_or_manager();
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $username = isset($_POST['username']) ? sanitize_user($_POST['username']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $company_name = isset($_POST['company_name']) ? sanitize_text_field($_POST['company_name']) : '';
        
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error(['message' => 'Username, email, and password are required']);
        }
        
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email address']);
        }
        
        if (username_exists($username)) {
            wp_send_json_error(['message' => 'Username already exists']);
        }
        
        if (email_exists($email)) {
            wp_send_json_error(['message' => 'Email already registered']);
        }
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }
        
        $user = new WP_User($user_id);
        $user->set_role('solar_vendor');
        
        if (!empty($name)) {
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $name,
            ]);
        }
        
        if (!empty($company_name)) {
            update_user_meta($user_id, 'company_name', $company_name);
        }
        
        // Auto-approve vendor
        update_user_meta($user_id, 'account_approved', 'yes');
        update_user_meta($user_id, 'email_verified', 'yes');
        
        wp_send_json_success(['message' => 'Vendor created successfully', 'user_id' => $user_id]);
    }
    
    /**
     * Get vendors awaiting approval
     */
    public function get_area_manager_vendor_approvals() {
        check_ajax_referer('get_vendor_approvals_nonce', 'nonce');
        
        $this->verify_admin_or_manager();
        
        $args = [
            'role' => 'solar_vendor',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'account_approved',
                    'value' => 'pending',
                    'compare' => '='
                ],
                [
                    'key' => 'account_approved',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ];
        
        $user_query = new WP_User_Query($args);
        $vendors = [];
        
        if (!empty($user_query->results)) {
            foreach ($user_query->results as $user) {
                $vendors[] = [
                    'ID' => $user->ID,
                    'display_name' => $user->display_name,
                    'user_email' => $user->user_email,
                    'company_name' => get_user_meta($user->ID, 'company_name', true),
                    'email_verified' => get_user_meta($user->ID, 'email_verified', true),
                ];
            }
        }
        
        wp_send_json_success(['vendors' => $vendors]);
    }
    
    /**
     * Update vendor approval status
     */
    public function update_vendor_status() {
        // check_ajax_referer('update_vendor_status_nonce', 'nonce');
        
        $this->verify_admin_or_manager();
        
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        if (!$vendor_id || !in_array($status, ['approved', 'denied', 'yes', 'no'])) {
            wp_send_json_error(['message' => 'Invalid vendor ID or status']);
        }
        
        // Normalize status
        $approved = in_array($status, ['approved', 'yes']) ? 'yes' : 'no';
        
        update_user_meta($vendor_id, 'account_approved', $approved);
        
        if ($approved === 'yes') {
            update_user_meta($vendor_id, 'account_approved_date', current_time('mysql'));
            update_user_meta($vendor_id, 'account_approved_by', get_current_user_id());
        }
        
        $message = $approved === 'yes' ? 'Vendor approved successfully' : 'Vendor denied';
        wp_send_json_success(['message' => $message]);
    }
    
    /**
     * Update vendor details
     */
    public function update_vendor_details() {
        check_ajax_referer('sp_vendor_approval_nonce', 'nonce');
        
        $this->verify_admin_or_manager();
        
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'Vendor ID required']);
        }
        
        $vendor = get_userdata($vendor_id);
        if (!$vendor || !in_array('solar_vendor', (array)$vendor->roles)) {
            wp_send_json_error(['message' => 'Invalid vendor']);
        }
        
        // Update allowed fields
        if (isset($_POST['company_name'])) {
            update_user_meta($vendor_id, 'company_name', sanitize_text_field($_POST['company_name']));
        }
        
        if (isset($_POST['phone'])) {
            update_user_meta($vendor_id, 'phone', sanitize_text_field($_POST['phone']));
        }
        
        if (isset($_POST['display_name'])) {
            wp_update_user([
                'ID' => $vendor_id,
                'display_name' => sanitize_text_field($_POST['display_name'])
            ]);
        }
        
        // Update coverage states and cities
        if (isset($_POST['states']) && is_array($_POST['states'])) {
            $states = array_map('sanitize_text_field', $_POST['states']);
            update_user_meta($vendor_id, 'purchased_states', $states);
        }
        
        if (isset($_POST['cities']) && is_array($_POST['cities'])) {
            $cities = array_map('sanitize_text_field', $_POST['cities']);
            update_user_meta($vendor_id, 'purchased_cities', $cities);
        }
        
        wp_send_json_success(['message' => 'Vendor details updated successfully']);
    }
    
    /**
     * Get reviews/submissions pending approval
     */
    public function get_area_manager_reviews() {
        check_ajax_referer('get_reviews_nonce', 'nonce');
        
        $manager = $this->verify_area_manager_role();
        
        global $wpdb;

        $steps_table = $wpdb->prefix . 'solar_process_steps';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        $reviews = $wpdb->get_results($wpdb->prepare(
            "SELECT ps.*, p.post_title, p.ID as project_id
             FROM {$steps_table} ps
             JOIN {$wpdb->posts} p ON ps.project_id = p.ID
             WHERE p.post_author = %d 
             AND ps.admin_status = 'under_review'
             ORDER BY ps.updated_at DESC
             LIMIT %d OFFSET %d",
            $manager->ID, $limit, $offset
        ), ARRAY_A);
        
        wp_send_json_success(['reviews' => $reviews]);
    }
    
    /**
     * Get leads for area manager
     */
    public function get_area_manager_leads() {
        check_ajax_referer('get_leads_nonce', 'nonce');
        
        $manager = $this->verify_area_manager_role();
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $lead_type = isset($_POST['lead_type']) ? sanitize_text_field($_POST['lead_type']) : '';
        
        $args = [
            'post_type' => 'solar_lead',
            'posts_per_page' => -1,
            'author' => $manager->ID,
            'post_status' => 'any'
        ];
        
        // Add meta query for status filter
        $meta_query = [];
        if (!empty($status)) {
            $meta_query[] = [
                'key' => '_lead_status',
                'value' => $status,
            ];
        }
        if (!empty($lead_type)) {
            $meta_query[] = [
                'key' => '_lead_type',
                'value' => $lead_type,
            ];
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        
        // Add search
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        $query = new WP_Query($args);
        $leads = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $lead_id = get_the_ID();
                
                $leads[] = [
                    'id' => $lead_id,
                    'name' => get_the_title(),
                    'phone' => get_post_meta($lead_id, '_lead_phone', true),
                    'email' => get_post_meta($lead_id, '_lead_email', true),
                    'status' => get_post_meta($lead_id, '_lead_status', true) ?: 'new',
                    'lead_type' => get_post_meta($lead_id, '_lead_type', true) ?: 'solar_project',
                    'project_type' => get_post_meta($lead_id, '_lead_project_type', true),
                    'system_size' => get_post_meta($lead_id, '_lead_system_size', true),
                    'source' => get_post_meta($lead_id, '_lead_source', true),
                    'address' => get_post_meta($lead_id, '_lead_address', true),
                    'notes' => get_the_content(),
                    'created_date' => get_the_date('Y-m-d'),
                ];
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success(['leads' => $leads]);
    }
    
    /**
     * Create new lead
     */
    public function create_solar_lead() {
        check_ajax_referer('sp_lead_nonce', 'lead_nonce');
        
        $manager = $this->verify_area_manager_role();
        
        $name = isset($_POST['lead_name']) ? sanitize_text_field($_POST['lead_name']) : '';
        $phone = isset($_POST['lead_phone']) ? sanitize_text_field($_POST['lead_phone']) : '';
        $email = isset($_POST['lead_email']) ? sanitize_email($_POST['lead_email']) : '';
        $status = isset($_POST['lead_status']) ? sanitize_text_field($_POST['lead_status']) : 'new';
        $notes = isset($_POST['lead_notes']) ? sanitize_textarea_field($_POST['lead_notes']) : '';
        $lead_type = isset($_POST['lead_type']) ? sanitize_text_field($_POST['lead_type']) : 'solar_project';
        $project_type = isset($_POST['lead_project_type']) ? sanitize_text_field($_POST['lead_project_type']) : '';
        $system_size = isset($_POST['lead_system_size']) ? floatval($_POST['lead_system_size']) : 0;
        $source = isset($_POST['lead_source']) ? sanitize_text_field($_POST['lead_source']) : '';
        $address = isset($_POST['lead_address']) ? sanitize_textarea_field($_POST['lead_address']) : '';
        
        if (empty($name)) {
            wp_send_json_error(['message' => 'Lead name is required']);
        }
        
        $lead_id = wp_insert_post([
            'post_title' => $name,
            'post_content' => $notes,
            'post_type' => 'solar_lead',
            'post_status' => 'publish',
            'post_author' => $manager->ID,
        ]);
        
        if (is_wp_error($lead_id)) {
            wp_send_json_error(['message' => $lead_id->get_error_message()]);
        }
        
        update_post_meta($lead_id, '_lead_phone', $phone);
        update_post_meta($lead_id, '_lead_email', $email);
        update_post_meta($lead_id, '_lead_status', $status);
        update_post_meta($lead_id, '_lead_type', $lead_type);
        update_post_meta($lead_id, '_lead_project_type', $project_type);
        update_post_meta($lead_id, '_lead_system_size', $system_size);
        update_post_meta($lead_id, '_lead_source', $source);
        update_post_meta($lead_id, '_lead_address', $address);
        
        wp_send_json_success(['message' => 'Lead created successfully', 'lead_id' => $lead_id]);
    }
    
    /**
     * Delete lead
     */
    public function delete_solar_lead() {
        check_ajax_referer('delete_lead_nonce', 'nonce');
        
        $manager = $this->verify_area_manager_role();
        
        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        
        if (!$lead_id) {
            wp_send_json_error(['message' => 'Lead ID required']);
        }
        
        $lead = get_post($lead_id);
        
        if (!$lead || $lead->post_type !== 'solar_lead') {
            wp_send_json_error(['message' => 'Invalid lead']);
        }
        
        if ($lead->post_author != $manager->ID) {
            wp_send_json_error(['message' => 'You do not have permission to delete this lead']);
        }
        
        $deleted = wp_delete_post($lead_id, true);
        
        if (!$deleted) {
            wp_send_json_error(['message' => 'Failed to delete lead']);
        }
        
        wp_send_json_success(['message' => 'Lead deleted successfully']);
    }
    
    /**
     * Send message to lead (email or WhatsApp)
     */
    public function send_lead_message() {
        check_ajax_referer('send_message_nonce', 'nonce');
        
        $manager = $this->verify_area_manager_role();
        
        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        $message_type = isset($_POST['message_type']) ? sanitize_text_field($_POST['message_type']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        if (!$lead_id || empty($message_type) || empty($message)) {
            wp_send_json_error(['message' => 'Lead ID, message type, and message are required']);
        }
        
        $lead = get_post($lead_id);
        
        if (!$lead || $lead->post_type !== 'solar_lead' || $lead->post_author != $manager->ID) {
            wp_send_json_error(['message' => 'Invalid lead or permission denied']);
        }
        
        if ($message_type === 'email') {
            $email = get_post_meta($lead_id, '_lead_email', true);
            if (empty($email)) {
                wp_send_json_error(['message' => 'Lead has no email address']);
            }
            
            $subject = 'Message from Solar Company';
            $sent = wp_mail($email, $subject, $message);
            
            if ($sent) {
                wp_send_json_success(['message' => 'Email sent successfully']);
            } else {
                wp_send_json_error(['message' => 'Failed to send email']);
            }
        } elseif ($message_type === 'whatsapp') {
            $phone = get_post_meta($lead_id, '_lead_phone', true);
            if (empty($phone)) {
                wp_send_json_error(['message' => 'Lead has no phone number']);
            }
            
            // Return WhatsApp URL for client-side opening
            $whatsapp_url = 'https://wa.me/91' . preg_replace('/\D/', '', $phone) . '?text=' . urlencode($message);
            wp_send_json_success(['message' => 'WhatsApp link generated', 'whatsapp_url' => $whatsapp_url]);
        } else {
            wp_send_json_error(['message' => 'Invalid message type']);
        }
    }
    
    /**
     * Assign location to area manager
     */
    public function assign_area_manager_location() {
        // check_ajax_referer('assign_location_nonce', 'nonce');
        
        // Only admins can assign locations
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied. Admin access required.']);
        }
        
        $manager_id = isset($_POST['manager_id']) ? intval($_POST['manager_id']) : 0;
        $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        
        if (!$manager_id) {
            wp_send_json_error(['message' => 'Manager ID required']);
        }
        
        if (!$state || !$city) {
            wp_send_json_error(['message' => 'Both state and city are required']);
        }
        
        $manager = get_userdata($manager_id);
        if (!$manager || !in_array('area_manager', (array)$manager->roles)) {
            wp_send_json_error(['message' => 'Invalid area manager']);
        }
        
        // Use singular meta keys: 'state' and 'city'
        update_user_meta($manager_id, 'state', $state);
        update_user_meta($manager_id, 'city', $city);
        
        wp_send_json_success(['message' => 'Location assigned successfully']);
    }
    
    /**
     * Get bids for area manager's projects
     */
    public function get_area_manager_bids() {
        check_ajax_referer('get_projects_nonce', 'nonce');
        
        $manager = $this->verify_area_manager_role();
        
        global $wpdb;
        $bids_table = $wpdb->prefix . 'project_bids';
        
        // Get manager's projects
        $args = [
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'author' => $manager->ID,
            'post_status' => 'publish'
        ];
        
        $query = new WP_Query($args);
        $projects_with_bids = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $project_id = get_the_ID();
                
                // Get bids for this project
                $bids = $wpdb->get_results($wpdb->prepare(
                    "SELECT b.*, u.display_name as vendor_name, u.user_email as vendor_email
                     FROM {$bids_table} b
                     JOIN {$wpdb->users} u ON b.vendor_id = u.ID
                     WHERE b.project_id = %d
                     ORDER BY b.bid_amount ASC, b.created_at DESC",
                    $project_id
                ), ARRAY_A);
                
                // Only include projects that have bids
                if (!empty($bids)) {
                    $assigned_vendor_id = get_post_meta($project_id, '_assigned_vendor_id', true);
                    $winning_bid_amount = get_post_meta($project_id, 'winning_bid_amount', true);
                    
                    $assigned_vendor_name = '';
                    if ($assigned_vendor_id) {
                        $vendor = get_userdata($assigned_vendor_id);
                        $assigned_vendor_name = $vendor ? $vendor->display_name : '';
                    }
                    
                    $projects_with_bids[] = [
                        'id' => $project_id,
                        'title' => get_the_title(),
                        'project_state' => get_post_meta($project_id, '_project_state', true),
                        'project_city' => get_post_meta($project_id, '_project_city', true),
                        'assigned_vendor_id' => $assigned_vendor_id,
                        'assigned_vendor_name' => $assigned_vendor_name,
                        'winning_bid_amount' => $winning_bid_amount,
                        'bids' => $bids
                    ];
                }
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success(['projects' => $projects_with_bids]);
    }

    /**
     * Assign Sales Team (Sales Managers) to an Area Manager
     */
    public function assign_team_to_area_manager() {
        check_ajax_referer('assign_team_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $manager_id = intval($_POST['manager_id'] ?? 0);
        $team_ids = $_POST['team_ids'] ?? [];

        if (!$manager_id) {
            wp_send_json_error(['message' => 'Invalid area manager ID']);
        }

        // Validate manager is an Area Manager
        $manager = get_userdata($manager_id);
        if (!$manager || !in_array('area_manager', (array)$manager->roles)) {
            wp_send_json_error(['message' => 'User is not an Area Manager']);
        }

        // 1. Get all Sales Managers currently assigned to this Area Manager
        $current_team = get_users([
            'role' => 'sales_manager',
            'meta_key' => '_assigned_area_manager',
            'meta_value' => $manager_id,
            'fields' => 'ID'
        ]);
        
        // 2. Clear assignment for removed members
        $removed_ids = array_diff($current_team, $team_ids);
        foreach ($removed_ids as $removed_id) {
            delete_user_meta($removed_id, '_assigned_area_manager');
        }

        // 3. Assign new members
        $count = 0;
        if (is_array($team_ids)) {
            foreach ($team_ids as $sm_id) {
                $sm_id = intval($sm_id);
                $sm_user = get_userdata($sm_id);
                if ($sm_user && in_array('sales_manager', (array)$sm_user->roles)) {
                    update_user_meta($sm_id, '_assigned_area_manager', $manager_id);
                    $count++;
                }
            }
        }

        wp_send_json_success([
            'message' => "Team updated successfully. {$count} Sales Managers assigned."
        ]);
    }
}
