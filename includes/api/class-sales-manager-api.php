<?php
/**
 * Sales Manager API Class
 * 
 * Handles all Sales Manager specific AJAX endpoints.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Sales_Manager_API {

    public function __construct() {
        // Dashboard stats
        add_action('wp_ajax_get_sales_manager_stats', [$this, 'get_sales_manager_stats']);
        
        // Lead management
        add_action('wp_ajax_get_sales_manager_leads', [$this, 'get_sales_manager_leads']);
        add_action('wp_ajax_create_lead_by_sales_manager', [$this, 'create_lead_by_sales_manager']);
        add_action('wp_ajax_update_lead_by_sales_manager', [$this, 'update_lead_by_sales_manager']);
        
        // Follow-up management
        add_action('wp_ajax_add_lead_followup', [$this, 'add_lead_followup']);
        add_action('wp_ajax_get_lead_followups', [$this, 'get_lead_followups']);
        add_action('wp_ajax_get_today_followups', [$this, 'get_today_followups']);
        add_action('wp_ajax_get_all_followups_history', [$this, 'get_all_followups_history']);
        
        // Conversions
        add_action('wp_ajax_mark_lead_converted', [$this, 'mark_lead_converted']);
        add_action('wp_ajax_get_sales_manager_conversions', [$this, 'get_sales_manager_conversions']);
    }

    /**
     * Verify user is a Sales Manager
     */
    private function verify_sales_manager() {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
        return in_array('sales_manager', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles) ||
               in_array('manager', (array) $user->roles) ||
               in_array('area_manager', (array) $user->roles);
    }

    /**
     * Get Sales Manager dashboard statistics
     */
    public function get_sales_manager_stats() {
        if (!$this->verify_sales_manager()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $user_id = get_current_user_id();
        global $wpdb;

        // Get leads created by or converted by this SM
        $leads_query = new WP_Query([
            'post_type' => 'solar_lead',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'OR',
                ['key' => '_created_by_sales_manager', 'value' => $user_id],
                ['key' => '_converted_by_sales_manager', 'value' => $user_id],
            ],
        ]);

        $total_leads = 0;
        $converted_leads = 0;
        $lead_statuses = ['new' => 0, 'contacted' => 0, 'interested' => 0, 'converted' => 0, 'lost' => 0];

        if ($leads_query->have_posts()) {
            while ($leads_query->have_posts()) {
                $leads_query->the_post();
                $lead_id = get_the_ID();
                $status = get_post_meta($lead_id, '_lead_status', true) ?: 'new';
                
                // Only count if created by this SM (for total)
                if (get_post_meta($lead_id, '_created_by_sales_manager', true) == $user_id) {
                    $total_leads++;
                    if (isset($lead_statuses[$status])) {
                        $lead_statuses[$status]++;
                    }
                }
                
                // Count conversions where this SM gets credit
                if (get_post_meta($lead_id, '_converted_by_sales_manager', true) == $user_id) {
                    $converted_leads++;
                }
            }
            wp_reset_postdata();
        }

        // Calculate conversion rate
        $conversion_rate = $total_leads > 0 ? round(($converted_leads / $total_leads) * 100, 1) : 0;

        // Get pending follow-ups (today or overdue)
        $table_followups = $wpdb->prefix . 'solar_lead_followups';
        $today = date('Y-m-d');
        
        // Count leads that need follow-up (no activity in last 3 days)
        $pending_followups = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT l.ID) 
            FROM {$wpdb->posts} l
            LEFT JOIN {$table_followups} f ON l.ID = f.lead_id
            WHERE l.post_type = 'solar_lead'
            AND l.post_status = 'publish'
            AND (
                SELECT pm.meta_value FROM {$wpdb->postmeta} pm 
                WHERE pm.post_id = l.ID AND pm.meta_key = '_created_by_sales_manager'
            ) = %d
            AND (
                SELECT pm2.meta_value FROM {$wpdb->postmeta} pm2 
                WHERE pm2.post_id = l.ID AND pm2.meta_key = '_lead_status'
            ) NOT IN ('converted', 'lost')
            GROUP BY l.ID
            HAVING MAX(f.activity_date) < DATE_SUB(NOW(), INTERVAL 3 DAY) OR MAX(f.activity_date) IS NULL
        ", $user_id));

        wp_send_json_success([
            'total_leads' => $total_leads,
            'converted_leads' => $converted_leads,
            'conversion_rate' => $conversion_rate,
            'pending_followups' => intval($pending_followups) ?: 0,
            'lead_statuses' => $lead_statuses,
        ]);
    }

    /**
     * Get leads for Sales Manager (only their own leads)
     */
    public function get_sales_manager_leads() {
        if (!$this->verify_sales_manager()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $user_id = get_current_user_id();
        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $meta_query = [
            ['key' => '_created_by_sales_manager', 'value' => $user_id],
        ];
        
        // Exclude 'converted' leads by default (unless specifically filtering for them)
        if ($status_filter !== 'converted') {
            $meta_query[] = [
                'key' => '_lead_status',
                'value' => 'converted',
                'compare' => '!='
            ];
        }

        if (!empty($status_filter)) {
            $meta_query[] = ['key' => '_lead_status', 'value' => $status_filter];
        }

        $args = [
            'post_type' => 'solar_lead',
            'posts_per_page' => 50,
            'post_status' => 'publish',
            'meta_query' => $meta_query,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $leads_query = new WP_Query($args);
        $leads = [];

        global $wpdb;
        $table_followups = $wpdb->prefix . 'solar_lead_followups';

        if ($leads_query->have_posts()) {
            while ($leads_query->have_posts()) {
                $leads_query->the_post();
                $lead_id = get_the_ID();
                
                // Get all follow-ups for this lead (for thread display)
                $followups = $wpdb->get_results($wpdb->prepare(
                    "SELECT activity_type, activity_date, notes FROM {$table_followups} 
                     WHERE lead_id = %d ORDER BY activity_date DESC LIMIT 10",
                    $lead_id
                ));

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
                    'created_date' => get_the_date('Y-m-d'),
                    'followups' => $followups,
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json_success(['leads' => $leads]);
    }

    /**
     * Create a new lead
     */
    public function create_lead_by_sales_manager() {
        if (!$this->verify_sales_manager()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        check_ajax_referer('sp_sales_manager_nonce', 'sm_nonce');

        $user_id = get_current_user_id();
        $supervisor_id = get_user_meta($user_id, '_supervised_by_area_manager', true);

        $name = sanitize_text_field($_POST['lead_name']);
        $phone = sanitize_text_field($_POST['lead_phone']);
        $email = sanitize_email($_POST['lead_email'] ?? '');
        $address = sanitize_textarea_field($_POST['lead_address'] ?? '');
        $source = sanitize_text_field($_POST['lead_source'] ?? 'other');
        $notes = sanitize_textarea_field($_POST['lead_notes'] ?? '');

        if (empty($name) || empty($phone)) {
            wp_send_json_error(['message' => 'Name and phone are required']);
        }

        // Create lead post
        $post_id = wp_insert_post([
            'post_title' => $name,
            'post_type' => 'solar_lead',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ]);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => 'Failed to create lead']);
        }

        // Save meta
        update_post_meta($post_id, '_lead_phone', $phone);
        update_post_meta($post_id, '_lead_email', $email);
        update_post_meta($post_id, '_lead_address', $address);
        update_post_meta($post_id, '_lead_source', $source);
        update_post_meta($post_id, '_lead_notes', $notes);
        update_post_meta($post_id, '_lead_status', 'new');
        
        // Save Lead Type & Specifics
        $lead_type = sanitize_text_field($_POST['lead_type'] ?? 'solar_project');
        update_post_meta($post_id, '_lead_type', $lead_type);
        
        if ($lead_type === 'solar_project') {
            $project_type = sanitize_text_field($_POST['lead_project_type'] ?? '');
            update_post_meta($post_id, '_lead_project_type', $project_type);
        } else {
            $system_size = floatval($_POST['lead_system_size'] ?? 0);
            update_post_meta($post_id, '_lead_system_size', $system_size);
        }

        update_post_meta($post_id, '_created_by_sales_manager', $user_id);
        
        if ($supervisor_id) {
            update_post_meta($post_id, '_assigned_area_manager', $supervisor_id);
        }

        wp_send_json_success([
            'message' => 'Lead created successfully',
            'lead_id' => $post_id,
        ]);
    }

    /**
     * Update lead (only status and basic info)
     */
    public function update_lead_by_sales_manager() {
        if (!$this->verify_sales_manager()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $user_id = get_current_user_id();
        $lead_id = intval($_POST['lead_id']);

        // Verify ownership
        $created_by = get_post_meta($lead_id, '_created_by_sales_manager', true);
        if ($created_by != $user_id && !current_user_can('administrator')) {
            wp_send_json_error(['message' => 'You can only edit your own leads']);
        }

        if (isset($_POST['lead_status'])) {
            update_post_meta($lead_id, '_lead_status', sanitize_text_field($_POST['lead_status']));
        }
        if (isset($_POST['lead_phone'])) {
            update_post_meta($lead_id, '_lead_phone', sanitize_text_field($_POST['lead_phone']));
        }
        if (isset($_POST['lead_email'])) {
            update_post_meta($lead_id, '_lead_email', sanitize_email($_POST['lead_email']));
        }
        if (isset($_POST['lead_notes'])) {
            update_post_meta($lead_id, '_lead_notes', sanitize_textarea_field($_POST['lead_notes']));
        }
        // Optional: Update Type/Details if provided
        if (isset($_POST['lead_type'])) {
            $lead_type = sanitize_text_field($_POST['lead_type']);
            update_post_meta($lead_id, '_lead_type', $lead_type);
            
            if ($lead_type === 'solar_project' && isset($_POST['lead_project_type'])) {
                update_post_meta($lead_id, '_lead_project_type', sanitize_text_field($_POST['lead_project_type']));
            } elseif ($lead_type === 'cleaning_service' && isset($_POST['lead_system_size'])) {
                update_post_meta($lead_id, '_lead_system_size', floatval($_POST['lead_system_size']));
            }
        }

        wp_send_json_success(['message' => 'Lead updated']);
    }


    /**
     * Add follow-up activity
     */
    public function add_lead_followup() {
        if (!$this->verify_sales_manager()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        $table_followups = $wpdb->prefix . 'solar_lead_followups';

        $user_id = get_current_user_id();
        $lead_id = intval($_POST['lead_id']);
        $activity_type = sanitize_text_field($_POST['activity_type']);
        $activity_date = sanitize_text_field($_POST['activity_date']);
        $notes = sanitize_textarea_field($_POST['notes']);

        if (empty($lead_id) || empty($activity_type) || empty($activity_date) || empty($notes)) {
            wp_send_json_error(['message' => 'All fields are required']);
        }

        // Verify lead exists and user has access
        $created_by_sm = get_post_meta($lead_id, '_created_by_sales_manager', true);
        $lead_post = get_post($lead_id);
        $post_author = $lead_post ? $lead_post->post_author : 0;
        
        $has_access = false;
        
        // 1. Owner (Creator) or Admin or Manager
        if ($created_by_sm == $user_id || $post_author == $user_id || current_user_can('administrator') || in_array('manager', (array) wp_get_current_user()->roles)) {
            $has_access = true;
        } 
        // 2. Area Manager supervising the Creator (SM)
        elseif (in_array('area_manager', (array) wp_get_current_user()->roles)) {
            // If created by SM, check if AM supervises them
            if ($created_by_sm) {
                $assigned_am = get_user_meta($created_by_sm, '_assigned_area_manager', true);
                if ($assigned_am == $user_id) {
                    $has_access = true;
                }
            }
        }

        if (!$has_access) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $result = $wpdb->insert(
            $table_followups,
            [
                'lead_id' => $lead_id,
                'sales_manager_id' => $user_id,
                'activity_type' => $activity_type,
                'activity_date' => $activity_date,
                'notes' => $notes,
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to save follow-up']);
        }

        // Update lead status to 'contacted' if it was 'new'
        $current_status = get_post_meta($lead_id, '_lead_status', true);
        if ($current_status === 'new') {
            update_post_meta($lead_id, '_lead_status', 'contacted');
        }

        wp_send_json_success([
            'message' => 'Follow-up added successfully',
            'followup_id' => $wpdb->insert_id,
        ]);
    }

    /**
     * Get follow-ups for a specific lead
     */
    public function get_lead_followups() {
        if (!$this->verify_sales_manager()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        $table_followups = $wpdb->prefix . 'solar_lead_followups';

        $lead_id = intval($_POST['lead_id']);

        $followups = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_followups} WHERE lead_id = %d ORDER BY activity_date DESC",
            $lead_id
        ));

        wp_send_json_success(['followups' => $followups]);
    }

    /**
     * Get today's follow-ups (for dashboard)
     */
    public function get_today_followups() {
        if (!$this->verify_sales_manager()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        $table_followups = $wpdb->prefix . 'solar_lead_followups';
        $user_id = get_current_user_id();
        $today = date('Y-m-d');

        $followups = $wpdb->get_results($wpdb->prepare(
            "SELECT f.*, p.post_title as lead_name 
             FROM {$table_followups} f
             JOIN {$wpdb->posts} p ON f.lead_id = p.ID
             WHERE f.sales_manager_id = %d 
             AND DATE(f.activity_date) = %s
             ORDER BY f.activity_date DESC",
            $user_id, $today
        ));

        wp_send_json_success(['followups' => $followups]);
    }

    /**
     * Get all follow-ups history
     */
    public function get_all_followups_history() {
        if (!$this->verify_sales_manager()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        $table_followups = $wpdb->prefix . 'solar_lead_followups';
        $user_id = get_current_user_id();
        $type_filter = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        $where = "WHERE f.sales_manager_id = %d";
        $params = [$user_id];

        if (!empty($type_filter)) {
            $where .= " AND f.activity_type = %s";
            $params[] = $type_filter;
        }

        $followups = $wpdb->get_results($wpdb->prepare(
            "SELECT f.*, p.post_title as lead_name 
             FROM {$table_followups} f
             JOIN {$wpdb->posts} p ON f.lead_id = p.ID
             {$where}
             ORDER BY f.activity_date DESC
             LIMIT 100",
            ...$params
        ));

        wp_send_json_success(['followups' => $followups]);
    }

    /**
     * Mark lead as converted (SM gets credit)
     */
    public function mark_lead_converted() {
        if (!$this->verify_sales_manager()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $user_id = get_current_user_id();
        $lead_id = intval($_POST['lead_id']);

        // Update lead status
        update_post_meta($lead_id, '_lead_status', 'converted');
        update_post_meta($lead_id, '_converted_by_sales_manager', $user_id);
        update_post_meta($lead_id, '_conversion_date', current_time('mysql'));

        wp_send_json_success(['message' => 'Lead marked as converted']);
    }

    /**
     * Get conversions for this Sales Manager
     */
    public function get_sales_manager_conversions() {
        if (!$this->verify_sales_manager()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $user_id = get_current_user_id();

        $leads_query = new WP_Query([
            'post_type' => 'solar_lead',
            'posts_per_page' => 50,
            'post_status' => 'publish',
            'meta_query' => [
                ['key' => '_converted_by_sales_manager', 'value' => $user_id],
            ],
            'orderby' => 'meta_value',
            'meta_key' => '_conversion_date',
            'order' => 'DESC',
        ]);

        $conversions = [];

        if ($leads_query->have_posts()) {
            while ($leads_query->have_posts()) {
                $leads_query->the_post();
                $lead_id = get_the_ID();
                
                $conversions[] = [
                    'id' => $lead_id,
                    'name' => get_the_title(),
                    'phone' => get_post_meta($lead_id, '_lead_phone', true),
                    'email' => get_post_meta($lead_id, '_lead_email', true),
                    'conversion_date' => get_post_meta($lead_id, '_conversion_date', true),
                    'source' => get_post_meta($lead_id, '_lead_source', true),
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json_success(['conversions' => $conversions]);
    }
}
