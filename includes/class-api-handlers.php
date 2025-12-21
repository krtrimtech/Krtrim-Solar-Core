<?php
/**
 * API Handlers Loader
 * 
 * Loads and initializes all API modules.
 * Refactored from a single 2,416-line file into modular classes.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.0
 * @version 1.0.1 - Refactored into modules
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class SP_API_Handlers {
    
    private $vendor_api;
    private $client_api;
    private $admin_manager_api;
    private $public_api;
    private $sales_manager_api;
    
    /**
     * Constructor. Loads API modules and hooks into WordPress.
     */
    public function __construct() {
        // Load API base class
        require_once plugin_dir_path(__FILE__) . 'api/class-api-base.php';
        
        // Load API modules
        require_once plugin_dir_path(__FILE__) . 'api/class-vendor-api.php';
        require_once plugin_dir_path(__FILE__) . 'api/class-client-api.php';
        require_once plugin_dir_path(__FILE__) . 'api/class-admin-manager-api.php';
        require_once plugin_dir_path(__FILE__) . 'api/class-public-api.php';
        require_once plugin_dir_path(__FILE__) . 'api/class-sales-manager-api.php';
        
        // Initialize API modules
        $this->vendor_api = new KSC_Vendor_API();
        $this->client_api = new KSC_Client_API();
        $this->admin_manager_api = new KSC_Admin_Manager_API();
        $this->public_api = new KSC_Public_API();
        $this->sales_manager_api = new KSC_Sales_Manager_API();
        
        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Process step template management (admin only)
        add_action('wp_ajax_save_default_process_steps', [$this, 'save_default_process_steps']);
        add_action('wp_ajax_reset_default_process_steps', [$this, 'reset_default_process_steps']);
    }
    
    /**
     * Register all REST API routes
     */
    public function register_rest_routes() {
        // Client notifications
        register_rest_route('solar/v1', '/client-notifications', [
            'methods' => 'GET',
            'callback' => [$this, 'client_get_notifications_rest'],
            'permission_callback' => function () {
                return is_user_logged_in() && (current_user_can('solar_client') || current_user_can('administrator'));
            },
        ]);
        
        // Client comments
        register_rest_route('solar/v1', '/client-comments', [
            'methods' => 'POST',
            'callback' => [$this, 'client_submit_comment_rest'],
            'permission_callback' => function () {
                return is_user_logged_in() && (current_user_can('solar_client') || current_user_can('administrator'));
            },
        ]);
        
        // Vendor notifications
        register_rest_route('solar/v1', '/vendor-notifications', [
            'methods' => 'GET',
            'callback' => [$this, 'get_vendor_notifications_rest'],
            'permission_callback' => function () {
                return is_user_logged_in() && (current_user_can('solar_vendor') || current_user_can('vendor'));
            },
        ]);
        
        register_rest_route('solar/v1', '/vendor-notifications/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_vendor_notification_rest'],
            'permission_callback' => function () {
                return is_user_logged_in() && (current_user_can('solar_vendor') || current_user_can('vendor'));
            },
        ]);
    }
    
    /**
     * REST: Get client notifications
     */
    public function client_get_notifications_rest(WP_REST_Request $request) {
        $user = wp_get_current_user();
        $client_id = $user->ID;
        
        global $wpdb;
        $table = $wpdb->prefix . 'solar_notifications';
        
        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND status = 'unread' ORDER BY created_at DESC LIMIT 10",
            $client_id
        ));
        
        $formatted = [];
        foreach ($notifications as $notif) {
            $icon = 'ℹ️';
            if (strpos($notif->type, 'approved') !== false) $icon = '✅';
            if (strpos($notif->type, 'rejected') !== false) $icon = '❌';
            if (strpos($notif->type, 'assigned') !== false) $icon = '👷';
            
            $formatted[] = [
                'id' => $notif->id,
                'type' => $notif->type,
                'icon' => $icon,
                'title' => ucfirst(str_replace('_', ' ', $notif->type)),
                'message' => wp_kses_post($notif->message),
                'time_ago' => human_time_diff(strtotime($notif->created_at), current_time('timestamp')) . ' ago',
            ];
        }
        
        return rest_ensure_response($formatted);
    }
    
    /**
     * REST: Submit client comment
     */
    public function client_submit_comment_rest(WP_REST_Request $request) {
        $step_id = intval($request->get_param('step_id'));
        $comment_text = sanitize_textarea_field($request->get_param('comment_text'));
        
        if (empty($comment_text)) {
            return new WP_Error('empty_comment', 'Comment cannot be empty', ['status' => 400]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'solar_process_steps';
        
        $existing_comment = $wpdb->get_var($wpdb->prepare("SELECT client_comment FROM $table WHERE id = %d", $step_id));
        $updated_comment = trim($existing_comment . "\n\n" . "Client: " . $comment_text);
        
        $updated = $wpdb->update(
            $table,
            ['client_comment' => $updated_comment, 'updated_at' => current_time('mysql')],
            ['id' => $step_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($updated === false) {
            return new WP_Error('db_error', 'Failed to update comment', ['status' => 500]);
        }
        
        return rest_ensure_response(['message' => 'Comment submitted successfully']);
    }
    
    /**
     * REST: Get vendor notifications
     */
    public function get_vendor_notifications_rest(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        if (!$user_id) return new WP_Error('no_user', 'Not logged in', ['status' => 401]);
        
        global $wpdb;
        $table = $wpdb->prefix . 'solar_notifications';
        
        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND status = 'unread' ORDER BY created_at DESC LIMIT 20",
            $user_id
        ));
        
        $formatted = [];
        foreach ($notifications as $notif) {
            $time_ago = human_time_diff(strtotime($notif->created_at), current_time('timestamp')) . ' ago';
            
            $icon = 'ℹ️';
            if (strpos($notif->type, 'approved') !== false) $icon = '✅';
            if (strpos($notif->type, 'rejected') !== false) $icon = '❌';
            if (strpos($notif->type, 'bid') !== false) $icon = '💰';
            
            $formatted[] = [
                'id' => $notif->id,
                'type' => $notif->type,
                'icon' => $icon,
                'title' => ucfirst(str_replace('_', ' ', $notif->type)),
                'message' => wp_kses_post($notif->message),
                'time_ago' => $time_ago,
            ];
        }
        
        return rest_ensure_response(['notifications' => $formatted]);
    }
    
    /**
     * REST: Delete vendor notification
     */
    public function delete_vendor_notification_rest(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        if (!$user_id) return new WP_Error('unauthorized', 'Not logged in', ['status' => 401]);
        
        $notif_id = intval($request->get_param('id'));
        if (!$notif_id) return new WP_Error('no_id', 'Notification ID missing', ['status' => 400]);
        
        return rest_ensure_response(['message' => 'Notification dismissed (simulated).']);
    }
    
    /**
     * Save default process steps template
     */
    public function save_default_process_steps() {
        check_ajax_referer('sp_save_default_steps', 'nonce');
        
        if (!current_user_can('administrator') && !current_user_can('manager')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }
        
        $steps = isset($_POST['steps']) && is_array($_POST['steps']) ? $_POST['steps'] : [];
        
        if (empty($steps)) {
            wp_send_json_error(['message' => 'At least one step is required.']);
        }
        
        $sanitized_steps = array_map('sanitize_text_field', $steps);
        
        update_option('sp_default_process_steps', $sanitized_steps);
        
        wp_send_json_success([
            'message' => 'Process steps saved successfully!',
            'steps' => $sanitized_steps
        ]);
    }
    
    /**
     * Reset default process steps to factory defaults
     */
    public function reset_default_process_steps() {
        check_ajax_referer('sp_save_default_steps', 'nonce');
        
        if (!current_user_can('administrator') && !current_user_can('manager')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }
        
        $default_steps = [
            'Site Visit',
            'Design Approval',
            'Material Delivery',
            'Installation',
            'Grid Connection',
            'Final Inspection'
        ];
        
        update_option('sp_default_process_steps', $default_steps);
        
        wp_send_json_success([
            'message' => 'Process steps reset to defaults successfully!',
            'steps' => $default_steps
        ]);
    }
}
