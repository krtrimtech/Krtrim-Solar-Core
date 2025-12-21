<?php
/**
 * Cleaning Services API
 * 
 * Handles AJAX requests for managing cleaning services, bookings, and visits.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Cleaning_Services_API {

    /**
     * Initialize hooks
     */
    public function __construct() {
        // Cleaning service management
        add_action('wp_ajax_get_cleaning_services', [$this, 'get_cleaning_services']);
        add_action('wp_ajax_get_cleaning_service_details', [$this, 'get_cleaning_service_details']);
        add_action('wp_ajax_schedule_cleaning_visit', [$this, 'schedule_cleaning_visit']);
        add_action('wp_ajax_assign_cleaner', [$this, 'assign_cleaner']);
        
        // Visit management
        add_action('wp_ajax_get_cleaning_visits', [$this, 'get_cleaning_visits']);
        add_action('wp_ajax_complete_cleaning_visit', [$this, 'complete_cleaning_visit']);
        add_action('wp_ajax_cancel_cleaning_visit', [$this, 'cancel_cleaning_visit']);
    }

    /**
     * Verify Area Manager or Admin access
     */
    private function verify_am_access() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }
        $user = wp_get_current_user();
        if (!in_array('area_manager', (array) $user->roles) && 
            !in_array('administrator', (array) $user->roles) &&
            !in_array('manager', (array) $user->roles)) {
            wp_send_json_error(['message' => 'Access denied. Area Manager role required.']);
        }
        return $user;
    }

    /**
     * Get cleaning services for AM dashboard
     */
    public function get_cleaning_services() {
        $user = $this->verify_am_access();
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $args = [
            'post_type' => 'cleaning_service',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];

        // Admin sees all, AM sees only their area
        if (!in_array('administrator', (array) $user->roles)) {
            $args['meta_query'][] = [
                'key' => '_assigned_area_manager',
                'value' => $user->ID,
            ];
        }

        // Filter by payment status
        if (!empty($status)) {
            $args['meta_query'][] = [
                'key' => '_payment_status',
                'value' => $status,
            ];
        }

        // Search by customer name
        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $services = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $service_id = get_the_ID();
                
                // Get next scheduled visit
                $next_visit = $this->get_next_visit($service_id);
                
                $services[] = [
                    'id' => $service_id,
                    'customer_name' => get_post_meta($service_id, '_customer_name', true),
                    'customer_phone' => get_post_meta($service_id, '_customer_phone', true),
                    'customer_type' => get_post_meta($service_id, '_customer_type', true),
                    'plan_type' => get_post_meta($service_id, '_plan_type', true),
                    'system_size_kw' => get_post_meta($service_id, '_system_size_kw', true),
                    'visits_total' => get_post_meta($service_id, '_visits_total', true),
                    'visits_used' => get_post_meta($service_id, '_visits_used', true),
                    'payment_status' => get_post_meta($service_id, '_payment_status', true),
                    'payment_option' => get_post_meta($service_id, '_payment_option', true),
                    'total_amount' => get_post_meta($service_id, '_total_amount', true),
                    'next_visit_date' => $next_visit ? $next_visit['scheduled_date'] : null,
                    'next_visit_cleaner' => $next_visit ? $next_visit['cleaner_name'] : null,
                    'created_at' => get_the_date('Y-m-d'),
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json_success($services);
    }

    /**
     * Get next scheduled visit for a service
     */
    private function get_next_visit($service_id) {
        $visits = get_posts([
            'post_type' => 'cleaning_visit',
            'meta_query' => [
                [
                    'key' => '_service_id',
                    'value' => $service_id,
                ],
                [
                    'key' => '_status',
                    'value' => 'scheduled',
                ],
            ],
            'meta_key' => '_scheduled_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'numberposts' => 1,
        ]);

        if (empty($visits)) {
            return null;
        }

        $visit = $visits[0];
        $cleaner_id = get_post_meta($visit->ID, '_cleaner_id', true);
        $cleaner_name = '';
        if ($cleaner_id) {
            $cleaner = get_userdata($cleaner_id);
            $cleaner_name = $cleaner ? $cleaner->display_name : '';
        }

        return [
            'visit_id' => $visit->ID,
            'scheduled_date' => get_post_meta($visit->ID, '_scheduled_date', true),
            'scheduled_time' => get_post_meta($visit->ID, '_scheduled_time', true),
            'cleaner_id' => $cleaner_id,
            'cleaner_name' => $cleaner_name,
        ];
    }

    /**
     * Get cleaning service details
     */
    public function get_cleaning_service_details() {
        $this->verify_am_access();
        
        $service_id = intval($_POST['service_id'] ?? 0);
        if (!$service_id) {
            wp_send_json_error(['message' => 'Service ID required']);
        }

        $service = get_post($service_id);
        if (!$service || $service->post_type !== 'cleaning_service') {
            wp_send_json_error(['message' => 'Invalid service']);
        }

        // Get all visits for this service
        $visits = get_posts([
            'post_type' => 'cleaning_visit',
            'meta_key' => '_service_id',
            'meta_value' => $service_id,
            'numberposts' => -1,
            'orderby' => 'meta_value',
            'meta_key' => '_scheduled_date',
            'order' => 'DESC',
        ]);

        $visit_data = [];
        foreach ($visits as $visit) {
            $cleaner_id = get_post_meta($visit->ID, '_cleaner_id', true);
            $cleaner_name = '';
            if ($cleaner_id) {
                $cleaner = get_userdata($cleaner_id);
                $cleaner_name = $cleaner ? $cleaner->display_name : '';
            }

            $visit_data[] = [
                'id' => $visit->ID,
                'scheduled_date' => get_post_meta($visit->ID, '_scheduled_date', true),
                'scheduled_time' => get_post_meta($visit->ID, '_scheduled_time', true),
                'status' => get_post_meta($visit->ID, '_status', true),
                'cleaner_id' => $cleaner_id,
                'cleaner_name' => $cleaner_name,
                'completion_photo' => get_post_meta($visit->ID, '_completion_photo', true),
                'completion_notes' => get_post_meta($visit->ID, '_completion_notes', true),
                'completed_at' => get_post_meta($visit->ID, '_completed_at', true),
            ];
        }

        wp_send_json_success([
            'service' => [
                'id' => $service_id,
                'customer_name' => get_post_meta($service_id, '_customer_name', true),
                'customer_phone' => get_post_meta($service_id, '_customer_phone', true),
                'customer_address' => get_post_meta($service_id, '_customer_address', true),
                'plan_type' => get_post_meta($service_id, '_plan_type', true),
                'system_size_kw' => get_post_meta($service_id, '_system_size_kw', true),
                'visits_total' => get_post_meta($service_id, '_visits_total', true),
                'visits_used' => get_post_meta($service_id, '_visits_used', true),
                'payment_status' => get_post_meta($service_id, '_payment_status', true),
                'total_amount' => get_post_meta($service_id, '_total_amount', true),
            ],
            'visits' => $visit_data,
        ]);
    }

    /**
     * Schedule a cleaning visit
     */
    public function schedule_cleaning_visit() {
        $this->verify_am_access();
        
        $service_id = intval($_POST['service_id'] ?? 0);
        $cleaner_id = intval($_POST['cleaner_id'] ?? 0);
        $scheduled_date = sanitize_text_field($_POST['scheduled_date'] ?? '');
        $scheduled_time = sanitize_text_field($_POST['scheduled_time'] ?? '09:00');

        if (!$service_id || !$cleaner_id || empty($scheduled_date)) {
            wp_send_json_error(['message' => 'Service ID, cleaner ID, and date are required']);
        }

        // Verify service exists
        $service = get_post($service_id);
        if (!$service || $service->post_type !== 'cleaning_service') {
            wp_send_json_error(['message' => 'Invalid service']);
        }

        // Check remaining visits
        $visits_total = intval(get_post_meta($service_id, '_visits_total', true));
        $visits_used = intval(get_post_meta($service_id, '_visits_used', true));
        if ($visits_used >= $visits_total) {
            wp_send_json_error(['message' => 'No remaining visits in this subscription']);
        }

        // Create visit
        $customer_name = get_post_meta($service_id, '_customer_name', true);
        $visit_id = wp_insert_post([
            'post_type' => 'cleaning_visit',
            'post_title' => sprintf('Visit - %s - %s', $customer_name, $scheduled_date),
            'post_status' => 'publish',
        ]);

        if (is_wp_error($visit_id)) {
            wp_send_json_error(['message' => 'Error creating visit']);
        }

        update_post_meta($visit_id, '_service_id', $service_id);
        update_post_meta($visit_id, '_cleaner_id', $cleaner_id);
        update_post_meta($visit_id, '_scheduled_date', $scheduled_date);
        update_post_meta($visit_id, '_scheduled_time', $scheduled_time);
        update_post_meta($visit_id, '_status', 'scheduled');
        update_post_meta($visit_id, '_notification_sent', false);

        // Notify cleaner about the assignment
        KSC_Cleaning_Notifications::notify_visit_scheduled($visit_id, $cleaner_id, $scheduled_date);

        wp_send_json_success([
            'message' => 'Visit scheduled successfully',
            'visit_id' => $visit_id,
        ]);
    }

    /**
     * Assign cleaner to a visit
     */
    public function assign_cleaner() {
        $this->verify_am_access();
        
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $cleaner_id = intval($_POST['cleaner_id'] ?? 0);

        if (!$visit_id || !$cleaner_id) {
            wp_send_json_error(['message' => 'Visit ID and cleaner ID required']);
        }

        update_post_meta($visit_id, '_cleaner_id', $cleaner_id);

        wp_send_json_success(['message' => 'Cleaner assigned successfully']);
    }

    /**
     * Complete a cleaning visit
     */
    public function complete_cleaning_visit() {
        $user = wp_get_current_user();
        
        // Allow cleaner, AM, or admin
        $allowed_roles = ['solar_cleaner', 'area_manager', 'administrator', 'manager'];
        if (!array_intersect($allowed_roles, (array) $user->roles)) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $visit_id = intval($_POST['visit_id'] ?? 0);
        $completion_notes = sanitize_textarea_field($_POST['completion_notes'] ?? '');

        if (!$visit_id) {
            wp_send_json_error(['message' => 'Visit ID required']);
        }

        update_post_meta($visit_id, '_status', 'completed');
        update_post_meta($visit_id, '_completion_notes', $completion_notes);
        update_post_meta($visit_id, '_completed_at', current_time('mysql'));

        // Handle photo upload
        if (!empty($_FILES['completion_photo']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $upload = wp_handle_upload($_FILES['completion_photo'], ['test_form' => false]);
            if (isset($upload['url'])) {
                update_post_meta($visit_id, '_completion_photo', $upload['url']);
            }
        }

        // Update service visits_used count
        $service_id = get_post_meta($visit_id, '_service_id', true);
        if ($service_id) {
            $visits_used = intval(get_post_meta($service_id, '_visits_used', true));
            update_post_meta($service_id, '_visits_used', $visits_used + 1);
            
            // Trigger post-service notification (review request after 2 hours)
            do_action('ksc_cleaning_visit_completed', $visit_id, $service_id);
        }

        wp_send_json_success(['message' => 'Visit marked as completed']);
    }

    /**
     * Cancel a cleaning visit
     */
    public function cancel_cleaning_visit() {
        $this->verify_am_access();
        
        $visit_id = intval($_POST['visit_id'] ?? 0);

        if (!$visit_id) {
            wp_send_json_error(['message' => 'Visit ID required']);
        }

        update_post_meta($visit_id, '_status', 'cancelled');

        wp_send_json_success(['message' => 'Visit cancelled']);
    }

    /**
     * Get visits for cleaner dashboard
     */
    public function get_cleaning_visits() {
        $user = wp_get_current_user();
        
        // Check if user is cleaner, AM, or admin
        $is_cleaner = in_array('solar_cleaner', (array) $user->roles);
        $is_am = in_array('area_manager', (array) $user->roles) || 
                 in_array('administrator', (array) $user->roles) ||
                 in_array('manager', (array) $user->roles);

        if (!$is_cleaner && !$is_am) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $status = sanitize_text_field($_POST['status'] ?? '');
        
        $args = [
            'post_type' => 'cleaning_visit',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];

        // Cleaner sees only their assigned visits
        if ($is_cleaner && !$is_am) {
            $args['meta_query'][] = [
                'key' => '_cleaner_id',
                'value' => $user->ID,
            ];
        }

        if (!empty($status)) {
            $args['meta_query'][] = [
                'key' => '_status',
                'value' => $status,
            ];
        }

        $query = new WP_Query($args);
        $visits = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $visit_id = get_the_ID();
                $service_id = get_post_meta($visit_id, '_service_id', true);

                $visits[] = [
                    'id' => $visit_id,
                    'service_id' => $service_id,
                    'customer_name' => get_post_meta($service_id, '_customer_name', true),
                    'customer_phone' => get_post_meta($service_id, '_customer_phone', true),
                    'customer_address' => get_post_meta($service_id, '_customer_address', true),
                    'scheduled_date' => get_post_meta($visit_id, '_scheduled_date', true),
                    'scheduled_time' => get_post_meta($visit_id, '_scheduled_time', true),
                    'status' => get_post_meta($visit_id, '_status', true),
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json_success($visits);
    }
}

// Initialize the class
new KSC_Cleaning_Services_API();
