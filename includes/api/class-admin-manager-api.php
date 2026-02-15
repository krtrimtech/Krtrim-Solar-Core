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
        add_action('wp_ajax_update_solar_lead_status', [$this, 'update_solar_lead_status']);
        add_action('wp_ajax_delete_solar_lead', [$this, 'delete_solar_lead']);
        add_action('wp_ajax_send_lead_message', [$this, 'send_lead_message']);
        
        // Marketplace
        add_action('wp_ajax_filter_marketplace_projects', [$this, 'filter_marketplace_projects']);
        add_action('wp_ajax_nopriv_filter_marketplace_projects', [$this, 'filter_marketplace_projects']);
        
        // Location assignment
        add_action('wp_ajax_assign_area_manager_location', [$this, 'assign_area_manager_location']);
        
        // Team assignment
        add_action('wp_ajax_assign_team_to_area_manager', [$this, 'assign_team_to_area_manager']);

        // Manager State Assignment (Admin)
        add_action('wp_ajax_update_manager_assigned_states', [$this, 'update_manager_assigned_states']);
        
        // Manager Dashboard APIs (multi-state managers)
        add_action('wp_ajax_get_manager_team_data', [$this, 'get_manager_team_data']);
        add_action('wp_ajax_get_team_analysis_data', [$this, 'get_team_analysis_data']);
        add_action('wp_ajax_get_unassigned_area_managers', [$this, 'get_unassigned_area_managers']);
        add_action('wp_ajax_get_am_location_assignments', [$this, 'get_am_location_assignments']);
        add_action('wp_ajax_remove_area_manager_location', [$this, 'remove_area_manager_location']);
        add_action('wp_ajax_get_cities_for_state', [$this, 'get_cities_for_state']);
        
        // Area Manager Team API (for AMs to see their Sales Managers)
        add_action('wp_ajax_get_am_team_data', [$this, 'get_am_team_data']);
        add_action('wp_ajax_get_sm_leads_for_am', [$this, 'get_sm_leads_for_am']);
        add_action('wp_ajax_get_sm_leads_for_am', [$this, 'get_sm_leads_for_am']);
        add_action('wp_ajax_get_lead_followup_history', [$this, 'get_lead_followup_history']);
        
        // Activity Feed
        add_action('wp_ajax_get_team_activity', [$this, 'get_team_activity']);
        
        // Team Member Detail Modal
        add_action('wp_ajax_get_team_member_details', [$this, 'get_team_member_details']);
    }
    
    /**
     * Get project IDs visible to an Area Manager
     * Logic: 
     * - If project has _assigned_area_manager set → show to that specific AM
     * - If not assigned → show to AM whose location matches project's city/state
     * 
     * @param int $manager_id The Area Manager's user ID
     * @return array Array of project IDs
     */
    /**
     * Get project IDs visible to an Area Manager or Manager
     * Logic: 
     * - If project has _assigned_area_manager set → show to that specific AM
     * - If not assigned → show to AM whose location matches project's city/state
     * - If Manager: Show projects from ALL assigned Area Managers and their regions
     * 
     * @param int $user_id The User ID (Manager or Area Manager)
     * @return array Array of project IDs
     */
    private function get_am_visible_project_ids($user_id) {
        $project_ids = [];
        
        // 0. Projects created by this user (Manager/AM)
        $own_projects = get_posts([
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'author' => $user_id,
            'fields' => 'ids'
        ]);
        $project_ids = array_merge($project_ids, $own_projects);

        $user = get_userdata($user_id);
        $is_manager = in_array('manager', (array)$user->roles);
        
        $am_ids = [];
        
        if ($is_manager) {
            // 1. Get projects by State (or Global if no state assigned)
            $manager_projects = $this->get_manager_visible_project_ids($user_id);
            $project_ids = array_merge($project_ids, $manager_projects);
            
            // 2. Get all assigned AMs
            $am_ids = get_users([
                'role' => 'area_manager',
                'meta_key' => '_supervised_by_manager',
                'meta_value' => $user_id,
                'fields' => 'ID'
            ]);
            
            // If no AMs assigned, proceed with empty array (don't return early)
            if (empty($am_ids)) {
                $am_ids = []; 
            }
        } else {
            // Single AM
            $am_ids = [$user_id];
        }
        
        // Loop through relevant AMs to gather projects
        foreach ($am_ids as $am_id) {
            // Get AM's assigned location
            $am_state = get_user_meta($am_id, 'state', true);
            $am_city = get_user_meta($am_id, 'city', true);
            
            // 1. Projects explicitly assigned to this AM
            $assigned_args = [
                'post_type' => 'solar_project',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_assigned_area_manager',
                        'value' => $am_id,
                        'compare' => '='
                    ]
                ]
            ];
            $assigned_ids = get_posts($assigned_args);
            $project_ids = array_merge($project_ids, $assigned_ids);
            
            // 2. Projects in AM's area that are NOT assigned to another AM
            if (!empty($am_state) && !empty($am_city)) {
                $location_args = [
                    'post_type' => 'solar_project',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'fields' => 'ids',
                    'meta_query' => [
                        'relation' => 'AND',
                        [
                            'key' => '_project_state',
                            'value' => $am_state,
                            'compare' => '='
                        ],
                        [
                            'key' => '_project_city',
                            'value' => $am_city,
                            'compare' => '='
                        ]
                    ]
                ];
                $location_projects = get_posts($location_args);
                
                foreach ($location_projects as $pid) {
                    $assigned_am = get_post_meta($pid, '_assigned_area_manager', true);
                    // Include if not assigned to anyone, or assigned to this AM
                    if (empty($assigned_am) || $assigned_am == $am_id) {
                        $project_ids[] = $pid;
                    }
                }
            }
        }
        
        return array_unique($project_ids);
    }
    
    /**
     * Get project IDs visible to a Manager (multi-state access)
     * Returns all projects in the manager's assigned states
     * 
     * @param int $manager_id The Manager's user ID
     * @return array Array of project IDs
     */
    private function get_manager_visible_project_ids($manager_id) {
        $user = get_userdata($manager_id);
        
        // Admin sees all projects
        if ($user && in_array('administrator', (array)$user->roles)) {
            $args = [
                'post_type' => 'solar_project',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'fields' => 'ids'
            ];
            return get_posts($args);
        }
        
        $assigned_states = get_user_meta($manager_id, '_assigned_states', true);
        
        if (empty($assigned_states) || !is_array($assigned_states)) {
            // Fallback: If no states assigned, Manager sees EVERYTHING (Global View)
            $args = [
                'post_type' => 'solar_project',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'fields' => 'ids'
            ];
            return get_posts($args);
        }
        
        // Get all projects in manager's assigned states
        $args = [
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_project_state',
                    'value' => $assigned_states,
                    'compare' => 'IN'
                ]
            ]
        ];
        
        return get_posts($args);
    }
    
    /**
     * Get area manager dashboard statistics
     */
    public function get_area_manager_dashboard_stats() {
        $user_data = $this->verify_admin_or_manager();
        $manager = $user_data['user'];
        
        global $wpdb;
        
        // Get projects using visibility logic (handles AM & Manager)
        $project_ids = $this->get_am_visible_project_ids($manager->ID);
        
        $total_projects = count($project_ids);
        $total_revenue = 0;
        $total_costs = 0;
        $total_profit = 0;
        $total_client_payments = 0;
        $total_outstanding = 0;
        
        foreach ($project_ids as $project_id) {
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
        if (in_array('manager', (array)$manager->roles)) {
             // Manager: Get leads from self AND team
             $team_ids = [$manager->ID];
             
             // Get AMs
             $ams = get_users([
                'role' => 'area_manager',
                'meta_key' => '_supervised_by_manager',
                'meta_value' => $manager->ID,
                'fields' => 'ID'
             ]);
             if (!empty($ams)) {
                 $team_ids = array_merge($team_ids, $ams);
                 
                 // Get SMs
                 $sms = get_users([
                    'role' => 'sales_manager',
                    'meta_query' => [
                        ['key' => '_assigned_area_manager', 'value' => $ams, 'compare' => 'IN']
                    ],
                    'fields' => 'ID'
                 ]);
                 if (!empty($sms)) {
                     $team_ids = array_merge($team_ids, $sms);
                 }
             }
             
             $total_leads = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'solar_lead' AND post_author IN (" . implode(',', array_map('intval', $team_ids)) . ")");
        } else {
             // AM: Own leads + SM leads (already logic exists? No, generic dashboard usually shows own leads or assigned)
             // Default behavior:
             $total_leads = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'solar_lead' AND post_author = %d",
                $manager->ID
            ));
        }


        // Get pending reviews count from ALL visible projects
        $pending_reviews = 0;
        if (!empty($project_ids)) {
            $placeholders = implode(',', array_fill(0, count($project_ids), '%d'));
            $pending_reviews = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}solar_process_steps 
                 WHERE project_id IN ($placeholders) AND admin_status = 'under_review'",
                ...$project_ids
            ));
        }
        
        // ========================================
        // Chart Data Preparation
        // ========================================
        
        // 1. Project Status Breakdown
        $status_counts = ['pending' => 0, 'in_progress' => 0, 'completed' => 0];
        foreach ($project_ids as $project_id) {
            $status = get_post_meta($project_id, 'project_status', true) ?: 'pending';
            if (isset($status_counts[$status])) {
                $status_counts[$status]++;
            }
        }
        
        // 2. Monthly Trend Data (Last 6 months)
        $months = [];
        $month_labels = [];
        $current_month = strtotime('first day of this month');
        
        for ($i = 5; $i >= 0; $i--) {
            $month_timestamp = strtotime("-$i month", $current_month);
            $month_key = date('Y-m', $month_timestamp);
            $months[$month_key] = 0;
            $month_labels[] = date('M', $month_timestamp);
        }
        
        // Count projects by month
        foreach ($project_ids as $project_id) {
            $project = get_post($project_id);
            if ($project) {
                $project_month = date('Y-m', strtotime($project->post_date));
                if (isset($months[$project_month])) {
                    $months[$project_month]++;
                }
            }
        }
        
        // 3. Financial Data by Month
        $financial_months = [];
        foreach (array_keys($months) as $month_key) {
            $financial_months[$month_key] = [
                'revenue' => 0,
                'payments' => 0,
                'costs' => 0
            ];
        }
        
        foreach ($project_ids as $project_id) {
            $project = get_post($project_id);
            if ($project) {
                $project_month = date('Y-m', strtotime($project->post_date));
                if (isset($financial_months[$project_month])) {
                    $financial_months[$project_month]['revenue'] += floatval(get_post_meta($project_id, '_total_project_cost', true) ?: 0);
                    $financial_months[$project_month]['payments'] += floatval(get_post_meta($project_id, '_paid_amount', true) ?: 0);
                    $financial_months[$project_month]['costs'] += floatval(get_post_meta($project_id, '_vendor_paid_amount', true) ?: 0);
                }
            }
        }
        
        // Extract arrays for chart
        $revenue_data = [];
        $payment_data = [];
        $costs_data = [];
        foreach ($financial_months as $data) {
            $revenue_data[] = round($data['revenue'], 2);
            $payment_data[] = round($data['payments'], 2);
            $costs_data[] = round($data['costs'], 2);
        }
        
        // 4. Lead Status Breakdown
        $lead_counts = ['converted' => 0, 'pending' => 0, 'lost' => 0];
        
        if (isset($team_ids) && !empty($team_ids)) {
            // Get all leads for team
            $leads_query = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'solar_lead' AND post_author IN (" . implode(',', array_map('intval', $team_ids)) . ")";
            $lead_ids = $wpdb->get_col($leads_query);
            
            foreach ($lead_ids as $lead_id) {
                $lead_status = get_post_meta($lead_id, '_lead_status', true) ?: 'new';
                
                if ($lead_status === 'converted') {
                    $lead_counts['converted']++;
                } elseif (in_array($lead_status, ['lost', 'dead', 'rejected'])) {
                    $lead_counts['lost']++;
                } else {
                    // new, contacted, qualified, proposal_sent, etc
                    $lead_counts['pending']++;
                }
            }
        }
        
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
            'pending_reviews' => intval($pending_reviews),
            
            // Chart Data
            'project_status' => [
                'pending' => $status_counts['pending'],
                'in_progress' => $status_counts['in_progress'],
                'completed' => $status_counts['completed']
            ],
            'monthly_data' => [
                'labels' => $month_labels,
                'values' => array_values($months)
            ],
            'financial_data' => [
                'revenue' => $revenue_data,
                'payments' => $payment_data,
                'costs' => $costs_data
            ],
            'lead_data' => [
                'converted' => $lead_counts['converted'],
                'pending' => $lead_counts['pending'],
                'lost' => $lead_counts['lost']
            ]
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
        
        // Permission check: Admin, project author, OR Manager supervising the project's AM
        $can_award = false;
        
        if ($is_admin) {
            $can_award = true;
        } elseif ($project->post_author == $current_user->ID) {
            // Project author (AM) can award their own project
            $can_award = true;
        } else {
            // Check if current user is a Manager supervising the AM who created this project
            $is_manager = in_array('manager', (array)$current_user->roles);
            if ($is_manager) {
                // Check if project's author (AM) is supervised by this manager
                $project_author_id = $project->post_author;
                $project_author_supervisor = get_user_meta($project_author_id, '_supervised_by_manager', true);
                
                if ($project_author_supervisor == $current_user->ID) {
                    $can_award = true;
                }
            }
        }
        
        if (!$can_award) {
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
            // error_log("No default process steps found for project {$project_id}");
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
            // error_log("Steps already exist for project {$project_id}, skipping creation");
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
                // error_log("Failed to create step: {$step_name} for project {$project_id}");
            }
        }
        
        // error_log("Created {$success_count} steps for project {$project_id}");
        
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
        
        // Extract user variables from auth
        $current_user = $auth['user'];
        $is_admin = $auth['is_admin'];
        
        // Permission check: Admin can review all, AM reviews their own projects, Manager reviews subordinate AM projects
        $can_review = false;
        
        if ($is_admin) {
            $can_review = true;
        } elseif ($project->post_author == $current_user->ID) {
            // Project author (AM) can review their own project
            $can_review = true;
        } else {
            // Check if current user is a Manager supervising the AM who created this project
            $is_manager = in_array('manager', (array)$current_user->roles);
            if ($is_manager) {
                // Check if project's author (AM) is supervised by this manager
                $project_author_id = $project->post_author;
                $project_author_supervisor = get_user_meta($project_author_id, '_supervised_by_manager', true);
                
                if ($project_author_supervisor == $current_user->ID) {
                    $can_review = true;
                }
            }
        }
        
        if (!$can_review) {
            wp_send_json_error(['message' => 'You do not have permission to review this submission.']);
        }
        
        $result = SP_Process_Steps_Manager::process_step_review($step_id, $decision, $comment, $current_user->ID);
        
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
     * Get team data for Manager (Alias for get_team_analysis_data)
     */
    public function get_manager_team_data() {
        $this->get_team_analysis_data();
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
        
        // Get all projects visible to this AM (by location or admin assignment)
        $project_ids = $this->get_am_visible_project_ids($manager->ID);
        
        $projects = [];
        global $wpdb;
        
        foreach ($project_ids as $project_id) {
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
                'title' => get_the_title($project_id),
                'status' => get_post_meta($project_id, 'project_status', true) ?: 'pending',
                'project_city' => get_post_meta($project_id, '_project_city', true),
                'project_state' => get_post_meta($project_id, '_project_state', true),
                'solar_system_size_kw' => get_post_meta($project_id, '_solar_system_size_kw', true),
                'total_cost' => get_post_meta($project_id, '_total_project_cost', true) ?: 0,
                'paid_amount' => get_post_meta($project_id, '_paid_amount', true) ?: 0,
                'vendor_name' => $vendor_name,
                'pending_submissions' => intval($pending_submissions),
                'created_at' => get_the_date('Y-m-d H:i:s', $project_id),
                'start_date' => get_post_meta($project_id, '_project_start_date', true),
            ];
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
        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'pending';
        
        // Check if user is a Manager supervising AMs
        $is_manager = in_array('manager', (array)$manager->roles);
        
        // DEBUG LOG
        // error_log('=== GET_AREA_MANAGER_REVIEWS DEBUG ===');
        // error_log('User ID: ' . $manager->ID);
        // error_log('User roles: ' . print_r($manager->roles, true));
        // error_log('Is Manager: ' . ($is_manager ? 'YES' : 'NO'));
        
        if ($is_manager) {
            // Check if manager has assigned states (global access logic)
            $manager_assigned_states = get_user_meta($manager->ID, '_assigned_states', true);
            // error_log('Manager assigned states: ' . print_r($manager_assigned_states, true));
            
            if (empty($manager_assigned_states)) {
                // error_log('GLOBAL ACCESS: Fetching ALL Area Managers');
                // No states = Global access to ALL Area Managers
                $supervised_ams = get_users([
                    'role' => 'area_manager',
                    'fields' => 'ID'
                ]);
            } else {
                // error_log('LIMITED ACCESS: Fetching only supervised AMs');
                // Has states = Only supervised Area Managers
                $supervised_ams = get_users([
                    'role' => 'area_manager',
                    'meta_key' => '_supervised_by_manager',
                    'meta_value' => $manager->ID,
                    'fields' => 'ID'
                ]);
            }
            
            // error_log('Supervised AMs count: ' . count($supervised_ams));
            // error_log('AM IDs: ' . print_r($supervised_ams, true));
            
            if (empty($supervised_ams)) {
                // Manager has no subordinates, return empty
                // error_log('ERROR: No AMs found, returning empty');
                wp_send_json_success(['reviews' => []]);
                return;
            }
            
            $am_ids = array_map('intval', $supervised_ams);
            
            // Add Manager's own ID to the list (managers can create their own projects too)
            $all_author_ids = array_merge([$manager->ID], $am_ids);
            $placeholders = implode(',', array_fill(0, count($all_author_ids), '%d'));
            
            // error_log('Author IDs to search (Manager + AMs): ' . print_r($all_author_ids, true));
            
            $query = "SELECT ps.*, p.post_title, p.ID as project_id,
                             p.post_author as am_id,
                             pm_city.meta_value as project_city,
                             pm_state.meta_value as project_state,
                             pm_size.meta_value as system_size,
                             pm_cost.meta_value as total_cost,
                             pm_status.meta_value as project_status,
                             client_user.display_name as client_name,
                             vendor_user.display_name as vendor_name
                      FROM {$steps_table} ps
                      JOIN {$wpdb->posts} p ON ps.project_id = p.ID
                      LEFT JOIN {$wpdb->postmeta} pm_city ON p.ID = pm_city.post_id AND pm_city.meta_key = '_project_city'
                      LEFT JOIN {$wpdb->postmeta} pm_state ON p.ID = pm_state.post_id AND pm_state.meta_key = '_project_state'
                      LEFT JOIN {$wpdb->postmeta} pm_size ON p.ID = pm_size.post_id AND pm_size.meta_key = 'solar_system_size_kw'
                      LEFT JOIN {$wpdb->postmeta} pm_cost ON p.ID = pm_cost.post_id AND pm_cost.meta_key = '_total_project_cost'
                      LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'project_status'
                      LEFT JOIN {$wpdb->postmeta} pm_client ON p.ID = pm_client.post_id AND pm_client.meta_key = '_client_user_id'
                      LEFT JOIN {$wpdb->users} client_user ON pm_client.meta_value = client_user.ID
                      LEFT JOIN {$wpdb->postmeta} pm_vendor ON p.ID = pm_vendor.post_id AND pm_vendor.meta_key = '_assigned_vendor_id'
                      LEFT JOIN {$wpdb->users} vendor_user ON pm_vendor.meta_value = vendor_user.ID
                      WHERE p.post_author IN ($placeholders)
                      " . ($filter === 'pending' ? "AND ps.admin_status = 'under_review'" : "") . "
                      ORDER BY ps.updated_at DESC
                      LIMIT %d OFFSET %d";
            
            // error_log('SQL Query: ' . $wpdb->prepare($query, array_merge($all_author_ids, [$limit, $offset])));
            $reviews = $wpdb->get_results($wpdb->prepare($query, array_merge($all_author_ids, [$limit, $offset])), ARRAY_A);
            
            // Calculate progress for each unique project
            $project_progress = [];
            if (!empty($reviews)) {
                $unique_projects = array_unique(array_column($reviews, 'project_id'));
                foreach ($unique_projects as $pid) {
                    $total_steps = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$steps_table} WHERE project_id = %d", $pid));
                    $approved_steps = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$steps_table} WHERE project_id = %d AND admin_status = 'approved'", $pid));
                    $project_progress[$pid] = $total_steps > 0 ? round(($approved_steps / $total_steps) * 100) : 0;
                }
                
                // Add progress to each review
                foreach ($reviews as &$review) {
                    $review['progress'] = $project_progress[$review['project_id']] ?? 0;
                }
            }
            
            // error_log('Reviews found: ' . count($reviews));
        } else {
            // Area Manager - show all visible projects (authored, assigned, or location-match)
            $project_ids = $this->get_am_visible_project_ids($manager->ID);
            
            if (empty($project_ids)) {
                wp_send_json_success(['reviews' => []]);
                return;
            }
            
            // Sanitize IDs for SQL IN clause
            $ids_placeholder = implode(',', array_fill(0, count($project_ids), '%d'));
            
            $reviews = $wpdb->get_results($wpdb->prepare(
                "SELECT ps.*, p.post_title as project_title, p.ID as project_id,
                        pm_city.meta_value as project_city,
                        pm_state.meta_value as project_state,
                        pm_size.meta_value as system_size
                 FROM {$steps_table} ps
                 JOIN {$wpdb->posts} p ON ps.project_id = p.ID
                 LEFT JOIN {$wpdb->postmeta} pm_city ON p.ID = pm_city.post_id AND pm_city.meta_key = '_project_city'
                 LEFT JOIN {$wpdb->postmeta} pm_state ON p.ID = pm_state.post_id AND pm_state.meta_key = '_project_state'
                 LEFT JOIN {$wpdb->postmeta} pm_size ON p.ID = pm_size.post_id AND pm_size.meta_key = 'solar_system_size_kw'
                 WHERE p.ID IN ($ids_placeholder) 
                 AND ps.admin_status = 'under_review'
                 ORDER BY ps.updated_at DESC
                 LIMIT %d OFFSET %d",
                array_merge($project_ids, [$limit, $offset])
            ), ARRAY_A);
            // error_log('Reviews found (AM): ' . count($reviews));
        }
        
        // error_log('Final reviews count: ' . count($reviews));
        // error_log('=== END DEBUG ===');
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
        
        // Prepare args for component
        $args = [
            'author' => $manager->ID,
            'search' => $search,
            'filter_meta' => []
        ];

        if (!empty($status)) {
            $args['filter_meta']['status'] = $status;
        }
        if (!empty($lead_type)) {
            $args['filter_meta']['lead_type'] = $lead_type;
        }
        
        // Use Component
        if (class_exists('LeadManagerComponent')) {
             $leads = LeadManagerComponent::get_leads($args);
        } else {
             wp_send_json_error(['message' => 'System Error: Component missing']);
             return;
        }
        
        wp_send_json_success(['leads' => $leads]);
    }
    
    /**
     * Create new lead
     */
    public function create_solar_lead() {
        error_log('🔵 [Admin Manager API] create_solar_lead called');
        
        try {
            check_ajax_referer('sp_lead_nonce', 'lead_nonce');
            error_log('✅ [Admin Manager API] Nonce verified');
        } catch (Exception $e) {
            error_log('❌ [Admin Manager API] Nonce verification failed: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        error_log('🔍 [Admin Manager API] Verifying Area Manager role...');
        $manager = $this->verify_area_manager_role();
        error_log('✅ [Admin Manager API] Manager verified: ID=' . $manager->ID);
        
        error_log('📦 [Admin Manager API] POST data: ' . print_r($_POST, true));
        
        // Use Component for creation
        if (class_exists('LeadManagerComponent')) {
            error_log('✅ [Admin Manager API] LeadManagerComponent found');
            error_log('🚀 [Admin Manager API] Calling LeadManagerComponent::create_lead()');
            
            $lead_id = LeadManagerComponent::create_lead($_POST, $manager->ID);
            
            error_log('📊 [Admin Manager API] create_lead returned: ' . print_r($lead_id, true));
        } else {
            error_log('❌ [Admin Manager API] LeadManagerComponent NOT found');
            wp_send_json_error(['message' => 'System Error: Component missing']);
            return;
        }
        
        if (is_wp_error($lead_id)) {
            error_log('❌ [Admin Manager API] WP_Error: ' . $lead_id->get_error_message());
            wp_send_json_error(['message' => $lead_id->get_error_message()]);
        }
        
        error_log('✨ [Admin Manager API] Lead created successfully! ID=' . $lead_id);
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
        
        // Use Component for deletion
        if (class_exists('LeadManagerComponent')) {
            $result = LeadManagerComponent::delete_lead($lead_id);
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }
        } else {
             wp_send_json_error(['message' => 'System Error: Component missing']);
             return;
        }
        
        wp_send_json_success(['message' => 'Lead deleted successfully']);
    }
    
    /**
     * Update lead status (Area Manager)
     */
    public function update_solar_lead_status() {
        check_ajax_referer('get_leads_nonce', 'nonce');
        
        $manager = $this->verify_area_manager_role();
        
        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        $status = isset($_POST['lead_status']) ? sanitize_text_field($_POST['lead_status']) : '';
        
        if (!$lead_id || empty($status)) {
            wp_send_json_error(['message' => 'Lead ID and status are required']);
        }
        
        $lead = get_post($lead_id);
        
        if (!$lead || $lead->post_type !== 'solar_lead') {
            wp_send_json_error(['message' => 'Invalid lead']);
        }
        
        // Verify ownership (Area Manager can only update their own leads, unless admin)
        if ($lead->post_author != $manager->ID && !current_user_can('administrator')) {
            wp_send_json_error(['message' => 'You do not have permission to update this lead']);
        }
        
        // Use Component for update
        if (class_exists('LeadManagerComponent')) {
            $result = LeadManagerComponent::update_lead($lead_id, ['lead_status' => $status]);
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }
        } else {
             wp_send_json_error(['message' => 'System Error: Component missing']);
             return;
        }
        
        wp_send_json_success(['message' => 'Status updated successfully']);
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
    
    public function assign_area_manager_location() {
        // check_ajax_referer('assign_location_nonce', 'nonce');
        
        $current_user = wp_get_current_user();
        $is_admin = in_array('administrator', (array)$current_user->roles);
        $is_manager = in_array('manager', (array)$current_user->roles);
    
        // Only admins or managers can assign locations/teams
        if (!$is_admin && !$is_manager) {
            wp_send_json_error(['message' => 'Permission denied. Admin or Manager access required.']);
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
        
        // If assigned by a Manager, link the AM to the Manager
        if ($is_manager) {
            update_user_meta($manager_id, '_supervised_by_manager', $current_user->ID);
        }
        
        wp_send_json_success(['message' => 'Location and Team assigned successfully']);
    }
    
    /**
     * Get bids for area manager's projects
     */
    public function get_area_manager_bids() {
        check_ajax_referer('get_projects_nonce', 'nonce');
        
        $manager = $this->verify_area_manager_role();
        
        global $wpdb;
        $bids_table = $wpdb->prefix . 'project_bids';
        
        // Get all projects visible to this AM
        $project_ids = $this->get_am_visible_project_ids($manager->ID);
        
        $projects_with_bids = [];
        
        foreach ($project_ids as $project_id) {
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
                    'title' => get_the_title($project_id),
                    'project_state' => get_post_meta($project_id, '_project_state', true),
                    'project_city' => get_post_meta($project_id, '_project_city', true),
                    'assigned_vendor_id' => $assigned_vendor_id,
                    'assigned_vendor_name' => $assigned_vendor_name,
                    'winning_bid_amount' => $winning_bid_amount,
                    'bids' => $bids
                ];
            }
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
    
    // ========================================
    // MANAGER DASHBOARD APIs (Multi-state access)
    // ========================================
    
    /**
     * Get team data for Manager dashboard
     * Returns Area Managers, Sales Managers, and Cleaners in manager's assigned states
     */
    public function get_team_analysis_data() {
        // error_log('=== GET TEAM ANALYSIS DATA DEBUG ===');
        check_ajax_referer('get_dashboard_stats_nonce', 'nonce');
        
        $user = wp_get_current_user();
        // error_log('User ID: ' . $user->ID . ', Roles: ' . print_r($user->roles, true));
        
        // Capability Check: Manager, Admin, OR Area Manager
        if (!in_array('manager', $user->roles) && !in_array('administrator', $user->roles) && !in_array('area_manager', $user->roles)) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        
        $assigned_states = get_user_meta($user->ID, '_assigned_states', true);
        $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
        
        // --- 1. Identify Target Area Managers ---
        $am_ids = [];
        $area_managers = [];

        // Define AMs list based on role
        if (in_array('area_manager', $user->roles) && !in_array('administrator', $user->roles)) {
             // Area Manager: Only see self
             $ams = [$user];
        } else {
             // Manager/Admin: See all relevant AMs
             $ams = get_users(['role' => 'area_manager', 'number' => -1]);
        }

        // Date range for "this month"
        $first_day_this_month = date('Y-m-01 00:00:00');

        foreach ($ams as $am) {
            $am_state = get_user_meta($am->ID, 'state', true);
            $am_city = get_user_meta($am->ID, 'city', true);
            
            // Filter by Manager's assigned states (skip if AM role as they only get themselves)
            if (!in_array('area_manager', $user->roles) && !empty($assigned_states) && is_array($assigned_states) && !in_array($am_state, $assigned_states)) {
                continue;
            }
            
            // Filter by specific state requested in dropdown
            if (!empty($state) && $am_state !== $state) {
                continue;
            }
            
            $am_ids[] = $am->ID;
            
            // Count projects
            $project_ids = $this->get_am_visible_project_ids($am->ID);
            $project_count = count($project_ids);
            
            // Calculate projects this month
            $projects_this_month = 0;
            foreach ($project_ids as $pid) {
                $post_date = get_the_date('Y-m-d H:i:s', $pid);
                if ($post_date >= $first_day_this_month) {
                    $projects_this_month++;
                }
            }

            // Get SMs
            $sms = get_users(['role' => 'sales_manager', 'meta_key' => '_assigned_area_manager', 'meta_value' => $am->ID]);
            
            // Get Cleaners
            $cleaners_list = get_users(['role' => 'solar_cleaner', 'meta_key' => '_supervised_by_area_manager', 'meta_value' => $am->ID]);

            $location = ($am_city && $am_state) ? "$am_city, $am_state" : 'Not Assigned';

            $area_managers[] = [
                'ID' => $am->ID,
                'display_name' => $am->display_name,
                'email' => $am->user_email,
                'state' => $am_state,
                'city' => $am_city,
                'location' => $location,
                'project_count' => $project_count,
                'projects_this_month' => $projects_this_month,
                'team_size' => count($sms) + count($cleaners_list)
            ];
        }
        
        // --- 2. Get Sales Managers ---
        $sales_managers = [];
        if (!empty($am_ids)) {
            $sm_args = [
                'role' => 'sales_manager',
                'meta_query' => [
                    [
                        'key' => '_assigned_area_manager',
                        'value' => $am_ids,
                        'compare' => 'IN'
                    ]
                ]
            ];
            $sms = get_users($sm_args);
            
            foreach ($sms as $sm) {
                $am_id = get_user_meta($sm->ID, '_assigned_area_manager', true);
                $am_user = get_userdata($am_id);
                
                // Get lead counts (WP_Query/Posts)
                global $wpdb;
                $lead_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'solar_lead' AND post_author = %d AND post_status != 'trash'",
                    $sm->ID
                ));
                
                $leads_this_month = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'solar_lead' AND post_author = %d AND post_status != 'trash' AND post_date >= %s",
                    $sm->ID,
                    $first_day_this_month
                ));

                $conversion_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'solar_lead' AND post_author = %d AND post_status = 'publish' AND ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_lead_status' AND meta_value = 'converted')",
                    $sm->ID
                ));
                
                $sales_managers[] = [
                    'ID' => $sm->ID,
                    'display_name' => $sm->display_name,
                    'email' => $sm->user_email,
                    'supervising_am' => $am_user ? $am_user->display_name : 'N/A',
                    'lead_count' => intval($lead_count),
                    'leads_this_month' => intval($leads_this_month),
                    'conversion_count' => intval($conversion_count)
                ];
            }
        }
        
        // --- 3. Get Cleaners ---
        $cleaners = [];
        if (!empty($am_ids)) {
            // Using get_users for cleaners
            $cleaner_args = [
                'role' => 'solar_cleaner',
                'meta_query' => [
                    [
                        'key' => '_supervised_by_area_manager',
                        'value' => $am_ids,
                        'compare' => 'IN'
                    ]
                ]
            ];
            $cleaner_rows = get_users($cleaner_args);
            
            foreach ($cleaner_rows as $c) {
                $am_id = get_user_meta($c->ID, '_supervised_by_area_manager', true);
                $am_user = get_userdata($am_id);

                // Get completed visits count
                $visits_query = new WP_Query([
                    'post_type' => 'cleaning_visit',
                    'post_status' => 'publish',
                    'meta_query' => [
                        'relation' => 'AND',
                        ['key' => '_cleaner_id', 'value' => $c->ID],
                        ['key' => '_status', 'value' => 'completed']
                    ],
                    'fields' => 'ids'
                ]);
                $visits_count = $visits_query->found_posts;

                 // Check for active status
                 $today = date('Y-m-d');
                 $active_visit_query = new WP_Query([
                     'post_type' => 'cleaning_visit',
                     'post_status' => 'publish',
                     'posts_per_page' => 1,
                     'meta_query' => [
                         'relation' => 'AND',
                         ['key' => '_cleaner_id', 'value' => $c->ID],
                         [
                             'relation' => 'OR',
                             ['key' => '_status', 'value' => 'in_progress'],
                             [
                                 'relation' => 'AND',
                                 ['key' => '_status', 'value' => 'assigned'],
                                 ['key' => '_scheduled_date', 'value' => $today]
                             ]
                         ]
                     ],
                     'fields' => 'ids'
                 ]);

                 $status = 'offline';
                 $status_label = 'Offline';

                 if ($active_visit_query->have_posts()) {
                     $active_visit_id = $active_visit_query->posts[0];
                     $visit_status = get_post_meta($active_visit_id, '_status', true);
                     if ($visit_status === 'in_progress') {
                         $status = 'on_job'; $status_label = 'On Job';
                     } else {
                         $status = 'active'; $status_label = 'Active Today';
                     }
                 }
                
                $cleaners[] = [
                    'id' => $c->ID,
                    'name' => $c->display_name,
                    'phone' => get_user_meta($c->ID, 'phone_number', true) ?: '-',
                    'supervising_am' => $am_user ? $am_user->display_name : 'N/A',
                    'completed_visits' => intval($visits_count),
                    'status' => $status,
                    'status_label' => $status_label
                ];
            }
        }
        
        // Calculate total projects
        $total_projects = 0;
        foreach ($am_ids as $am_id) {
            $total_projects += count($this->get_am_visible_project_ids($am_id));
        }
        
        wp_send_json_success([
            'area_managers' => $area_managers,
            'sales_managers' => $sales_managers,
            'cleaners' => $cleaners,
            'total_projects' => $total_projects
        ]);
    }
    
    /**
     * Get all area managers (for assignment dropdown)
     */
    public function get_unassigned_area_managers() {
        $user = wp_get_current_user();
        
        if (!in_array('manager', $user->roles) && !in_array('administrator', $user->roles)) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        
        $managers = get_users(['role' => 'area_manager', 'number' => -1]);
        $result = [];
        
        foreach ($managers as $am) {
            $result[] = [
                'ID' => $am->ID,
                'display_name' => $am->display_name,
                'email' => $am->user_email,
                'state' => get_user_meta($am->ID, 'state', true),
                'city' => get_user_meta($am->ID, 'city', true)
            ];
        }
        
        wp_send_json_success(['managers' => $result]);
    }
    
    /**
     * Get current AM location assignments (for Manager's states)
     */
    public function get_am_location_assignments() {
        $user = wp_get_current_user();
        
        if (!in_array('manager', $user->roles) && !in_array('administrator', $user->roles)) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        
        $assigned_states = get_user_meta($user->ID, '_assigned_states', true);
        
        $managers = get_users(['role' => 'area_manager', 'number' => -1]);
        $assignments = [];
        
        foreach ($managers as $am) {
            $am_state = get_user_meta($am->ID, 'state', true);
            $am_city = get_user_meta($am->ID, 'city', true);
            
            // Skip if not in manager's states (unless admin)
            if (!empty($assigned_states) && is_array($assigned_states) && !in_array($am_state, $assigned_states)) {
                continue;
            }
            
            if (!empty($am_state) && !empty($am_city)) {
                $assignments[] = [
                    'am_id' => $am->ID,
                    'am_name' => $am->display_name,
                    'state' => $am_state,
                    'city' => $am_city
                ];
            }
        }
        
        wp_send_json_success(['assignments' => $assignments]);
    }
    
    /**
     * Remove area manager location assignment
     */
    public function remove_area_manager_location() {
        $user = wp_get_current_user();
        
        if (!in_array('manager', $user->roles) && !in_array('administrator', $user->roles)) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        
        $manager_id = isset($_POST['manager_id']) ? intval($_POST['manager_id']) : 0;
        
        if (!$manager_id) {
            wp_send_json_error(['message' => 'Invalid manager ID']);
        }
        
        // Verify target is an area manager
        $am = get_userdata($manager_id);
        if (!$am || !in_array('area_manager', (array)$am->roles)) {
            wp_send_json_error(['message' => 'Invalid area manager']);
        }
        
        // Clear location
        delete_user_meta($manager_id, 'state');
        delete_user_meta($manager_id, 'city');
        
        wp_send_json_success(['message' => 'Location assignment removed successfully']);
    }
    
    /**
     * Get cities for a given state (for dropdown)
     */
    public function get_cities_for_state() {
        $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
        
        if (empty($state)) {
            wp_send_json_error(['message' => 'State required']);
        }
        
        // Load cities from JSON file
        $json_file = plugin_dir_path(dirname(dirname(__FILE__))) . 'assets/data/indian-states-cities.json';
        
        if (!file_exists($json_file)) {
            wp_send_json_error(['message' => 'Cities data not found']);
        }
        
        $json_content = file_get_contents($json_file);
        $data = json_decode($json_content, true);
        
        $cities = [];
        
        // JSON structure: { "states": [ {"state": "StateName", "districts": [...]} ] }
        if (isset($data['states']) && is_array($data['states'])) {
            foreach ($data['states'] as $state_data) {
                if (isset($state_data['state']) && $state_data['state'] === $state) {
                    if (isset($state_data['districts']) && is_array($state_data['districts'])) {
                        $cities = $state_data['districts'];
                    }
                    break;
                }
            }
        }
        
        wp_send_json_success(['cities' => $cities]);
    }
    
    /**
     * Get team data for Area Manager (their assigned Sales Managers)
     */
    public function get_am_team_data() {
        check_ajax_referer('get_projects_nonce', 'nonce');
        
        $user = wp_get_current_user();
        
        // Verify area_manager, manager, or admin role
        if (!in_array('area_manager', (array)$user->roles) && 
            !in_array('manager', (array)$user->roles) && 
            !in_array('administrator', (array)$user->roles)) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        
        $am_id = $user->ID;
        
        // 1. Get Sales Managers assigned to this Area Manager
        $sm_args = [
            'role' => 'sales_manager',
            'meta_key' => '_assigned_area_manager',
            'meta_value' => $am_id
        ];
        $sms = get_users($sm_args);
        
        $sales_managers = [];
        global $wpdb;
        
        foreach ($sms as $sm) {
            // Get lead counts using WP_Query on 'solar_lead' post type
            $lead_query = new WP_Query([
                'post_type' => 'solar_lead',
                'post_status' => 'any',
                'author' => $sm->ID,
                'fields' => 'ids'
            ]);
            $lead_count = $lead_query->found_posts;

            // Leads this month
            $month_leads_query = new WP_Query([
                'post_type' => 'solar_lead',
                'post_status' => 'any',
                'author' => $sm->ID,
                'date_query' => [
                    [
                        'year' => date('Y'),
                        'month' => date('m'),
                    ],
                ],
                'fields' => 'ids'
            ]);
            $leads_this_month = $month_leads_query->found_posts;
            
            // Conversions
            $conversion_query = new WP_Query([
                'post_type' => 'solar_lead',
                'post_status' => 'any',
                'author' => $sm->ID,
                'meta_key' => '_lead_status',
                'meta_value' => 'converted',
                'fields' => 'ids'
            ]);
            $conversion_count = $conversion_query->found_posts;
            
            $phone = get_user_meta($sm->ID, 'phone_number', true);
            
            $sales_managers[] = [
                'ID' => $sm->ID,
                'id' => $sm->ID, // for consistency
                'display_name' => $sm->display_name,
                'email' => $sm->user_email,
                'phone' => $phone ?: '',
                'supervising_am' => 'You',
                'lead_count' => intval($lead_count),
                'leads_this_month' => intval($leads_this_month),
                'conversion_count' => intval($conversion_count)
            ];
        }

        // 2. Get Cleaners supervised by this Area Manager
        $cleaner_args = [
            'role' => 'solar_cleaner',
            'meta_key' => '_supervised_by_area_manager',
            'meta_value' => $am_id
        ];
        $cleaners = get_users($cleaner_args);
        $cleaners_data = [];

        foreach ($cleaners as $cleaner) {
            // Calculate completed visits (All Time)
            $completed_query = new WP_Query([
                'post_type' => 'cleaning_visit',
                'post_status' => 'publish',
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_cleaner_id',
                        'value' => $cleaner->ID
                    ],
                    [
                        'key' => '_status',
                        'value' => 'completed'
                    ]
                ],
                'fields' => 'ids'
            ]);
            $completed_count = $completed_query->found_posts;

            // Check for active status (Active Today / On Job)
            $today = date('Y-m-d');
            $active_visit_query = new WP_Query([
                'post_type' => 'cleaning_visit',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_cleaner_id',
                        'value' => $cleaner->ID
                    ],
                    [
                        'relation' => 'OR',
                        [
                            'key' => '_status',
                            'value' => 'in_progress'
                        ],
                        [
                            'relation' => 'AND',
                            [
                                'key' => '_status',
                                'value' => 'assigned'
                            ],
                            [
                                'key' => '_scheduled_date',
                                'value' => $today
                            ]
                        ]
                    ]
                ],
                'fields' => 'ids'
            ]);

            $status = 'offline';
            $status_label = 'Offline';

            if ($active_visit_query->have_posts()) {
                $active_visit_id = $active_visit_query->posts[0];
                $visit_status = get_post_meta($active_visit_id, '_status', true);
                
                if ($visit_status === 'in_progress') {
                    $status = 'on_job';
                    $status_label = 'On Job';
                } else {
                    $status = 'active';
                    $status_label = 'Active Today';
                }
            }

            $cleaners_data[] = [
                'id' => $cleaner->ID,
                'name' => $cleaner->display_name,
                'email' => $cleaner->user_email,
                'phone' => get_user_meta($cleaner->ID, 'phone_number', true) ?: '',
                'supervising_am' => 'You',
                'completed_visits' => $completed_count,
                'status' => $status,
                'status_label' => $status_label
            ];
        }
        
        // 3. Get Active Projects Count
        $project_args = [
             'post_type' => 'solar_project',
             'post_status' => 'publish',
             'meta_query' => [
                 'relation' => 'AND',
                 [
                     'relation' => 'OR',
                     ['key' => '_created_by_area_manager', 'value' => $am_id],
                     ['key' => '_assigned_area_manager', 'value' => $am_id],
                     ['key' => 'post_author', 'value' => $am_id]
                 ],
                 [
                     'key' => 'project_status',
                     'value' => ['assigned', 'in_progress'],
                     'compare' => 'IN'
                 ]
             ],
             'fields' => 'ids'
        ];
        $project_query = new WP_Query($project_args);
        $active_project_count = $project_query->found_posts;

        wp_send_json_success([
            'sales_managers' => $sales_managers,
            'cleaners' => $cleaners_data,
            'total_projects' => $active_project_count // Renamed to match KSC_TeamAnalysis
        ]);
    }
    
    /**
     * Get all leads created by a specific Sales Manager (for AM visibility)
     */
    public function get_sm_leads_for_am() {
        $user = wp_get_current_user();
        
        // Verify area_manager, manager, or admin role
        if (!in_array('area_manager', (array)$user->roles) && 
            !in_array('manager', (array)$user->roles) && 
            !in_array('administrator', (array)$user->roles)) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        
        $sm_id = isset($_POST['sm_id']) ? intval($_POST['sm_id']) : 0;
        
        if (!$sm_id) {
            wp_send_json_error(['message' => 'Sales Manager ID required']);
        }
        
        // For non-admins, verify this SM is assigned to the requesting AM
        if (!in_array('administrator', (array)$user->roles) && !in_array('manager', (array)$user->roles)) {
            $assigned_am = get_user_meta($sm_id, '_assigned_area_manager', true);
            if ($assigned_am != $user->ID) {
                wp_send_json_error(['message' => 'This Sales Manager is not assigned to you']);
            }
        }
        
        global $wpdb;

        // Get leads created by this SM using WP_Query
        $leads_query = new WP_Query([
            'post_type' => 'solar_lead',
            'post_status' => 'any',
            'author' => $sm_id,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        $formatted_leads = [];
        
        if ($leads_query->have_posts()) {
            foreach ($leads_query->posts as $lead) {
                // Get last followup date dynamically from the custom table
                $last_followup = $wpdb->get_var($wpdb->prepare(
                    "SELECT created_at FROM {$wpdb->prefix}solar_lead_followups 
                     WHERE lead_id = %d 
                     ORDER BY created_at DESC 
                     LIMIT 1",
                    $lead->ID
                ));
                
                $formatted_leads[] = [
                    'id' => $lead->ID,
                    'name' => $lead->post_title,
                    'phone' => get_post_meta($lead->ID, '_lead_phone', true),
                    'email' => get_post_meta($lead->ID, '_lead_email', true),
                    'status' => get_post_meta($lead->ID, '_lead_status', true),
                    'city' => get_post_meta($lead->ID, '_lead_city', true),
                    'state' => get_post_meta($lead->ID, '_lead_state', true),
                    'notes' => $lead->post_content, // Assuming content holds notes
                    'created_date' => $lead->post_date,
                    'last_followup' => $last_followup
                ];
            }
        }
        
        wp_send_json_success([
            'leads' => $formatted_leads
        ]);
    }
    
    /**
     * Get followup history for a specific lead
     */
    public function get_lead_followup_history() {
        $user = wp_get_current_user();
        
        // Verify area_manager, manager, or admin role
        if (!in_array('area_manager', (array)$user->roles) && 
            !in_array('manager', (array)$user->roles) && 
            !in_array('administrator', (array)$user->roles) &&
            !in_array('sales_manager', (array)$user->roles)) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        
        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        
        if (!$lead_id) {
            wp_send_json_error(['message' => 'Lead ID required']);
        }
        
        global $wpdb;
        
        // Check if followups table exists, if not fallback to lead notes
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}solar_lead_followups'");
        
        if ($table_exists) {
            $followups = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}solar_lead_followups 
                 WHERE lead_id = %d 
                 ORDER BY created_at DESC",
                $lead_id
            ), ARRAY_A);
        } else {
            // Fallback: Get lead status changes and notes from lead itself
            $lead = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}solar_leads WHERE id = %d",
                $lead_id
            ), ARRAY_A);
            
            $followups = [];
            if ($lead && !empty($lead['notes'])) {
                $followups[] = [
                    'type' => 'note',
                    'notes' => $lead['notes'],
                    'created_at' => $lead['created_at'],
                    'outcome' => null,
                    'next_action' => null,
                    'next_action_date' => null
                ];
            }
            if ($lead && !empty($lead['updated_at'])) {
                $followups[] = [
                    'type' => 'status_change',
                    'notes' => 'Status changed to: ' . ucfirst($lead['status']),
                    'created_at' => $lead['updated_at'],
                    'outcome' => null,
                    'next_action' => null,
                    'next_action_date' => null
                ];
            }
        }
        
        wp_send_json_success([
            'followups' => $followups
        ]);
    }


    /**
     * Get Team Activity Logs (Hierarchical)
     */
    public function get_team_activity() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $current_user = wp_get_current_user();
        global $wpdb;
        $table = $wpdb->prefix . 'solar_activity_logs';
        $where_clauses = [];

        // HIERARCHY LOGIC
        if (in_array('administrator', $current_user->roles)) {
            // Admin sees EVERYTHING - No filter needed
        } 
        elseif (in_array('manager', $current_user->roles)) {
            // Manager sees: Area Managers, Sales Managers, Cleaners (in their system)
            $where_clauses[] = "user_role IN ('area_manager', 'sales_manager', 'solar_cleaner')";
        } 
        elseif (in_array('area_manager', $current_user->roles)) {
            // Area Manager sees: Their Sales Managers and Cleaners
            
            // Get Sales Managers assigned to this AM
            $sm_ids = get_users([
                'role' => 'sales_manager',
                'meta_key' => '_assigned_area_manager',
                'meta_value' => $current_user->ID,
                'fields' => 'ID'
            ]);

            // Get Cleaners assigned to this AM
            $cleaner_ids = get_users([
                'role' => 'solar_cleaner',
                'meta_key' => '_supervised_by_area_manager',
                'meta_value' => $current_user->ID,
                'fields' => 'ID'
            ]);

            $team_ids = array_merge($sm_ids, $cleaner_ids);
            
            // Also include the AM's OWN actions (so they see what they did)
            $team_ids[] = $current_user->ID;

            if (!empty($team_ids)) {
                $ids_str = implode(',', array_map('intval', $team_ids));
                $where_clauses[] = "user_id IN ($ids_str)";
            } else {
                // If no team, show only own actions
                $where_clauses[] = "user_id = " . $current_user->ID;
            }
        } 
        else {
            // Other roles (SM, Vendor, Client) - See only own activity
            $where_clauses[] = "user_id = " . $current_user->ID;
        }

        // Build Query
        $sql = "SELECT * FROM {$table}";
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }
        $sql .= " ORDER BY created_at DESC LIMIT 20";

        $logs = $wpdb->get_results($sql);
        $formatted_logs = [];

        foreach ($logs as $log) {
            $formatted_logs[] = [
                'icon' => $this->get_activity_icon($log->action_type),
                'user' => $this->get_user_display_name_safe($log->user_id, $log->user_role),
                'action' => ucfirst(str_replace('_', ' ', $log->action_type)),
                'details' => $log->details,
                'time_ago' => human_time_diff(strtotime($log->created_at), current_time('timestamp')) . ' ago',
                'role' => ucfirst(str_replace('_', ' ', $log->user_role))
            ];
        }

        wp_send_json_success($formatted_logs);
    }

    private function get_activity_icon($action_type) {
        if (strpos($action_type, 'create') !== false) return '🆕';
        if (strpos($action_type, 'update') !== false) return '✏️';
        if (strpos($action_type, 'delete') !== false) return '🗑️';
        if (strpos($action_type, 'assign') !== false) return '📎';
        if (strpos($action_type, 'approve') !== false) return '✅';
        if (strpos($action_type, 'reject') !== false) return '❌';
        return '📝';
    }

    private function get_user_display_name_safe($user_id, $fallback_role) {
        $user = get_userdata($user_id);
        return $user ? $user->display_name : ucfirst($fallback_role);
    }



    /**
     * Update Manager's assigned states (Admin Only)
     */
    public function update_manager_assigned_states() {
        check_ajax_referer('admin_manager_action_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $manager_id = intval($_POST['manager_id']);
        $states = isset($_POST['states']) ? $_POST['states'] : [];
        $is_all = isset($_POST['assign_all']) && filter_var($_POST['assign_all'], FILTER_VALIDATE_BOOLEAN);
        
        if (!$manager_id) {
            wp_send_json_error(['message' => 'Invalid Manager ID']);
        }
        
        // Validate user is a Manager
        $manager = get_userdata($manager_id);
        if (!$manager || !in_array('manager', (array)$manager->roles)) {
            wp_send_json_error(['message' => 'User is not a Manager']);
        }
        
        // Update manager's assigned states
        if ($is_all) {
            delete_user_meta($manager_id, '_assigned_states');
            $assigned_states = []; // Empty array means ALL states
        } else {
            $sanitized_states = array_map('sanitize_text_field', (array)$states);
            update_user_meta($manager_id, '_assigned_states', $sanitized_states);
            $assigned_states = $sanitized_states;
        }
        
        // =========================================
        // AUTO-ASSIGNMENT LOGIC
        // =========================================
        
        // 1. Get ALL Area Managers currently supervised by this Manager
        $current_ams = get_users([
            'role' => 'area_manager',
            'meta_key' => '_supervised_by_manager',
            'meta_value' => $manager_id,
            'fields' => 'ID'
        ]);
        
        // 2. Get ALL Area Managers in the newly assigned states
        $ams_in_states = [];
        if (!empty($assigned_states)) {
            foreach ($assigned_states as $state) {
                $state_ams = get_users([
                    'role' => 'area_manager',
                    'meta_key' => 'state',
                    'meta_value' => $state,
                    'fields' => 'ID'
                ]);
                $ams_in_states = array_merge($ams_in_states, $state_ams);
            }
            $ams_in_states = array_unique($ams_in_states);
        } elseif ($is_all) {
            // If "assign all" is checked, get ALL area managers
            $all_ams = get_users([
                'role' => 'area_manager',
                'fields' => 'ID'
            ]);
            $ams_in_states = $all_ams;
        }
        
        // 3. Assign new AMs (those in states but not currently supervised)
        $newly_assigned = array_diff($ams_in_states, $current_ams);
        foreach ($newly_assigned as $am_id) {
            update_user_meta($am_id, '_supervised_by_manager', $manager_id);
        }
        
        // 4. Unassign AMs no longer in manager's states
        $to_unassign = array_diff($current_ams, $ams_in_states);
        foreach ($to_unassign as $am_id) {
            delete_user_meta($am_id, '_supervised_by_manager');
        }
        
        wp_send_json_success([
            'message' => 'Manager states updated successfully',
            'assigned_count' => count($newly_assigned),
            'unassigned_count' => count($to_unassign),
            'total_supervised_ams' => count($ams_in_states),
            'states' => $assigned_states
        ]);
    }
    
    /**
     * Get detailed information about a team member
     * Shows comprehensive activity data based on role
     */
    public function get_team_member_details() {
        check_ajax_referer('get_projects_nonce', 'nonce');
        
        $current_user = wp_get_current_user();
        $is_admin = in_array('administrator', (array)$current_user->roles);
        $is_manager = in_array('manager', (array)$current_user->roles);
        $is_am = in_array('area_manager', (array)$current_user->roles);
        
        if (!$is_admin && !$is_manager && !$is_am) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';
        
        if (!$user_id || !$role) {
            wp_send_json_error(['message' => 'Missing user ID or role']);
        }
        
        // Ownership Check for Area Managers
        if ($is_am) {
            if ($role === 'sales_manager') {
                $assigned_am = get_user_meta($user_id, '_assigned_area_manager', true);
                if ($assigned_am != $current_user->ID) {
                    wp_send_json_error(['message' => 'Access denied. You do not supervise this Sales Manager.']);
                }
            } elseif ($role === 'cleaner') {
                $supervisor = get_user_meta($user_id, '_supervised_by_area_manager', true);
                if ($supervisor != $current_user->ID) {
                    wp_send_json_error(['message' => 'Access denied. You do not supervise this Cleaner.']);
                }
            } elseif ($role === 'area_manager') {
                if ($user_id != $current_user->ID) {
                    wp_send_json_error(['message' => 'Access denied. You can only view your own details.']);
                }
            }
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(['message' => 'User not found']);
        }
        
        // Build response based on role
        $response = [
            'user_info' => [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'phone' => get_user_meta($user->ID, 'phone_number', true) ?: get_user_meta($user->ID, 'phone', true),
                'state' => get_user_meta($user->ID, 'state', true),
                'city' => get_user_meta($user->ID, 'city', true),
                'joined_date' => date('M Y', strtotime($user->user_registered)),
                'photo_url' => get_user_meta($user->ID, '_photo_url', true),
                'aadhaar_number' => get_user_meta($user->ID, '_aadhaar_number', true),
                'aadhaar_image_url' => get_user_meta($user->ID, '_aadhaar_image_url', true)
            ],
            'stats' => [],
            'projects' => [],
            'leads' => [],
            'visits' => [],
            'recent_activity' => []
        ];
        
        global $wpdb;
        
        // Role-specific data fetching
        switch ($role) {
            case 'area_manager':
                $response = array_merge($response, $this->get_am_detail_data($user_id));
                break;
                
            case 'sales_manager':
                $response = array_merge($response, $this->get_sm_detail_data($user_id));
                break;
                
            case 'cleaner':
                $response = array_merge($response, $this->get_cleaner_detail_data($user_id));
                break;
                
            default:
                wp_send_json_error(['message' => 'Invalid role']);
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Get detailed data for Area Manager
     */
    private function get_am_detail_data($user_id) {
        global $wpdb;
        
        // Get projects
        $project_ids = $this->get_am_visible_project_ids($user_id);
        $projects = [];
        
        foreach ($project_ids as $pid) {
            $projects[] = [
                'id' => $pid,
                'title' => get_the_title($pid),
                'status' => get_post_meta($pid, 'project_status', true) ?: 'pending',
                'city' => get_post_meta($pid, '_project_city', true),
                'state' => get_post_meta($pid, '_project_state', true),
                'cost' => get_post_meta($pid, '_total_project_cost', true) ?: 0
            ];
        }
        
        // Get leads (own + team SM leads)
        $sm_ids = get_users([
            'role' => 'sales_manager',
            'meta_key' => '_assigned_area_manager',
            'meta_value' => $user_id,
            'fields' => 'ID'
        ]);
        
        $all_ids = array_merge([$user_id], $sm_ids);
        $leads_query = "SELECT * FROM {$wpdb->posts} WHERE post_type = 'solar_lead' AND post_author IN (" . implode(',', array_map('intval', $all_ids)) . ") ORDER BY post_date DESC LIMIT 20";
        $leads_raw = $wpdb->get_results($leads_query);
        
        $leads = [];
        foreach ($leads_raw as $lead) {
            $leads[] = [
                'id' => $lead->ID,
                'name' => $lead->post_title,
                'phone' => get_post_meta($lead->ID, '_lead_phone', true),
                'status' => get_post_meta($lead->ID, '_lead_status', true) ?: 'new',
                'city' => get_post_meta($lead->ID, '_lead_city', true),
                'created_date' => date('M d, Y', strtotime($lead->post_date))
            ];
        }
        
        // Get team size
        $team_size = count($sm_ids);
        
        // Get team members details (Sales Managers)
        $team_members = [];
        foreach ($sm_ids as $sm_id) {
            $sm_user = get_userdata($sm_id);
            if ($sm_user) {
                // Get lead count for this SM
                global $wpdb;
                $sm_lead_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'solar_lead' AND post_author = %d",
                    $sm_id
                ));
                
                $team_members[] = [
                    'id' => $sm_user->ID,
                    'name' => $sm_user->display_name,
                    'email' => $sm_user->user_email,
                    'phone' => get_user_meta($sm_id, 'phone_number', true) ?: get_user_meta($sm_id, 'phone', true),
                    'role' => 'Sales Manager',
                    'lead_count' => intval($sm_lead_count),
                    'joined_date' => date('M d, Y', strtotime($sm_user->user_registered))
                ];
            }
        }
        
        // Add Cleaners to team
        $cleaner_ids = get_users([
            'role' => 'solar_cleaner',
            'meta_key' => '_supervised_by_area_manager',
            'meta_value' => $user_id,
            'fields' => 'ID'
        ]);
        
        foreach ($cleaner_ids as $cleaner_id) {
            $cleaner_user = get_userdata($cleaner_id);
            if ($cleaner_user) {
                $team_members[] = [
                    'id' => $cleaner_user->ID,
                    'name' => $cleaner_user->display_name,
                    'email' => $cleaner_user->user_email,
                    'phone' => get_user_meta($cleaner_id, 'phone_number', true) ?: get_user_meta($cleaner_id, 'phone', true),
                    'role' => 'Cleaner',
                    'lead_count' => 0,
                    'joined_date' => date('M d, Y', strtotime($cleaner_user->user_registered))
                ];
            }
        }
        
        $team_size += count($cleaner_ids);
        
        // Stats
        $stats = [
            'total_projects' => count($project_ids),
            'total_leads' => count($leads_raw),
            'team_size' => $team_size
        ];
        
        // Recent activity from logs
        $activity = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}solar_activity_logs 
             WHERE user_id = %d 
             ORDER BY created_at DESC LIMIT 10",
            $user_id
        ));
        
        $recent_activity = [];
        foreach ($activity as $act) {
            $recent_activity[] = [
                'time' => human_time_diff(strtotime($act->created_at), current_time('timestamp')) . ' ago',
                'description' => $act->details ?: ucfirst(str_replace('_', ' ', $act->action_type))
            ];
        }
        
        return [
            'stats' => $stats,
            'projects' => $projects,
            'leads' => $leads,
            'team_members' => $team_members,
            'recent_activity' => $recent_activity
        ];
    }
    
    /**
     * Get detailed data for Sales Manager
     */
    private function get_sm_detail_data($user_id) {
        global $wpdb;
        
        // Get leads
        $leads_query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->posts} WHERE post_type = 'solar_lead' AND post_author = %d ORDER BY post_date DESC LIMIT 20",
            $user_id
        );
        $leads_raw = $wpdb->get_results($leads_query);
        
        $leads = [];
        $conversions = 0;
        
        foreach ($leads_raw as $lead) {
            $status = get_post_meta($lead->ID, '_lead_status', true) ?: 'new';
            if ($status === 'converted') {
                $conversions++;
            }
            
            $leads[] = [
                'id' => $lead->ID,
                'name' => $lead->post_title,
                'phone' => get_post_meta($lead->ID, '_lead_phone', true),
                'status' => $status,
                'city' => get_post_meta($lead->ID, '_lead_city', true),
                'created_date' => date('M d, Y', strtotime($lead->post_date))
            ];
        }
        
        $total_leads = count($leads_raw);
        $conversion_rate = $total_leads > 0 ? round(($conversions / $total_leads) * 100, 1) : 0;
        
        // Stats
        $stats = [
            'total_leads' => $total_leads,
            'conversions' => $conversions,
            'conversion_rate' => $conversion_rate
        ];
        
        // Recent activity
        $activity = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}solar_activity_logs 
             WHERE user_id = %d 
             ORDER BY created_at DESC LIMIT 10",
            $user_id
        ));
        
        $recent_activity = [];
        foreach ($activity as $act) {
            $recent_activity[] = [
                'time' => human_time_diff(strtotime($act->created_at), current_time('timestamp')) . ' ago',
                'description' => $act->details ?: ucfirst(str_replace('_', ' ', $act->action_type))
            ];
        }
        
        return [
            'stats' => $stats,
            'leads' => $leads,
            'recent_activity' => $recent_activity
        ];
    }
    
    /**
     * Get detailed data for Cleaner
     */
    private function get_cleaner_detail_data($user_id) {
        global $wpdb;
        
        // Get cleaning visits
        // Get cleaning visits
        $visits_query = new WP_Query([
            'post_type' => 'cleaning_visit',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'meta_key' => '_scheduled_date',
            'orderby' => 'meta_value',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '_cleaner_id',
                    'value' => $user_id
                ]
            ]
        ]);
        
        $visits = [];
        $completed = 0;
        $pending = 0;
        $total_found = $visits_query->found_posts; // Approximate total for rate calculation
        
        foreach ($visits_query->posts as $visit) {
            $status = get_post_meta($visit->ID, '_status', true);
            $scheduled_date = get_post_meta($visit->ID, '_scheduled_date', true);
            $service_id = get_post_meta($visit->ID, '_service_id', true);
            
            // Get client details from service
            $client_name = 'N/A';
            $location = 'N/A';
            
            if ($service_id) {
                $client_name = get_post_meta($service_id, '_customer_name', true) ?: 'N/A';
                $location = get_post_meta($service_id, '_customer_city', true) ?: get_post_meta($service_id, '_customer_address', true) ?: 'N/A';
            }
            
            if ($status === 'completed') {
                $completed++;
            } elseif ($status !== 'cancelled') {
                $pending++;
            }
            
            $visits[] = [
                'id' => $visit->ID,
                'visit_date' => $scheduled_date ? date('M d, Y', strtotime($scheduled_date)) : 'N/A',
                'client_name' => $client_name,
                'location' => $location,
                'status' => $status ?: 'pending'
            ];
        }
        
        // Calculate total stats
        $completed_query = new WP_Query([
            'post_type' => 'cleaning_visit',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_cleaner_id',
                    'value' => $user_id
                ],
                [
                    'key' => '_status',
                    'value' => 'completed'
                ]
            ],
            'fields' => 'ids'
        ]);
        $total_completed = $completed_query->found_posts;

        $pending_query = new WP_Query([
            'post_type' => 'cleaning_visit',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_cleaner_id',
                    'value' => $user_id
                ],
                [
                    'key' => '_status',
                    'compare' => 'NOT IN',
                    'value' => ['completed', 'cancelled']
                ]
            ],
            'fields' => 'ids'
        ]);
        $total_pending = $pending_query->found_posts;
        
        $grand_total = $total_completed + $total_pending;
        $completion_rate = $grand_total > 0 ? round(($total_completed / $grand_total) * 100, 1) : 0;
        
        // Stats
        $stats = [
            'completed_visits' => $total_completed,
            'pending_visits' => $total_pending,
            'completion_rate' => $completion_rate
        ];
        
        // Recent activity
        $activity = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}solar_activity_logs 
             WHERE user_id = %d 
             ORDER BY created_at DESC LIMIT 10",
            $user_id
        ));
        
        $recent_activity = [];
        foreach ($activity as $act) {
            $recent_activity[] = [
                'time' => human_time_diff(strtotime($act->created_at), current_time('timestamp')) . ' ago',
                'description' => $act->details ?: ucfirst(str_replace('_', ' ', $act->action_type))
            ];
        }
        
        return [
            'stats' => $stats,
            'visits' => $visits,
            'recent_activity' => $recent_activity
        ];
    }
}
