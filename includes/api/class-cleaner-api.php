<?php
/**
 * Cleaner Management API
 * 
 * Handles AJAX requests for managing solar cleaners - create, list, update, delete.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Cleaner_API {

    /**
     * Initialize hooks
     */
    public function __construct() {
        // Cleaner CRUD operations
        add_action('wp_ajax_create_cleaner', [$this, 'create_cleaner']);
        add_action('wp_ajax_get_cleaners', [$this, 'get_cleaners']);
        add_action('wp_ajax_update_cleaner', [$this, 'update_cleaner']);
        add_action('wp_ajax_delete_cleaner', [$this, 'delete_cleaner']);
        
        // Hierarchy
        add_action('wp_ajax_get_cleaner_superiors', [$this, 'get_cleaner_superiors']);
    }

    /**
     * Verify Area Manager, Sales Manager, or Admin access
     */
    private function verify_am_access() {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
        return in_array('area_manager', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles) ||
               in_array('manager', (array) $user->roles) ||
               in_array('sales_manager', (array) $user->roles);
    }

    /**
     * Create a new cleaner account
     */
    public function create_cleaner() {
        if (!$this->verify_am_access()) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }

        check_ajax_referer('ksc_cleaner_nonce', 'cleaner_nonce');

        $name = sanitize_text_field($_POST['cleaner_name'] ?? '');
        $phone = sanitize_text_field($_POST['cleaner_phone'] ?? '');
        $email = sanitize_email($_POST['cleaner_email'] ?? '');
        $aadhaar = sanitize_text_field($_POST['cleaner_aadhaar'] ?? '');
        $address = sanitize_textarea_field($_POST['cleaner_address'] ?? '');

        // Validate required fields
        if (empty($name) || empty($phone) || empty($aadhaar)) {
            wp_send_json_error(['message' => 'Name, phone, and Aadhaar are required']);
        }

        // Validate Aadhaar format (12 digits)
        if (!preg_match('/^[0-9]{12}$/', $aadhaar)) {
            wp_send_json_error(['message' => 'Invalid Aadhaar format. Must be 12 digits.']);
        }

        // Check if phone already exists
        $existing = get_users(['meta_key' => 'phone', 'meta_value' => $phone, 'number' => 1]);
        if (!empty($existing)) {
            wp_send_json_error(['message' => 'A user with this phone number already exists']);
        }

        // Generate username and password
        $username = 'cleaner_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . '_' . substr($phone, -4);
        $password = wp_generate_password(12, true, false);

        // Create user
        $user_id = wp_create_user($username, $password, $email ?: $username . '@cleaner.local');

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }

        // Set role
        $user = new WP_User($user_id);
        $user->set_role('solar_cleaner');

        // Update display name
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => explode(' ', $name)[0],
            'last_name' => count(explode(' ', $name)) > 1 ? end(explode(' ', $name)) : '',
        ]);

        // Save meta data
        // Determine Supervisor (Area Manager)
        $supervisor_id = get_current_user_id();
        
        // If Admin/Manager is creating, allow assigning to specific AM
        if (isset($_POST['assigned_area_manager']) && !empty($_POST['assigned_area_manager'])) {
             $current_user = wp_get_current_user();
             if (in_array('administrator', (array)$current_user->roles) || in_array('manager', (array)$current_user->roles)) {
                 $assigned_am_id = intval($_POST['assigned_area_manager']);
                 // Verify the assigned user is actually an AM
                 $assigned_user = get_userdata($assigned_am_id);
                 if ($assigned_user && in_array('area_manager', (array)$assigned_user->roles)) {
                     $supervisor_id = $assigned_am_id;
                 }
             }
        }

        update_user_meta($user_id, 'phone', $phone);
        update_user_meta($user_id, '_aadhaar_number', $aadhaar);
        update_user_meta($user_id, '_cleaner_address', $address);
        update_user_meta($user_id, '_supervised_by_area_manager', $supervisor_id);
        update_user_meta($user_id, '_created_by', get_current_user_id());
        update_user_meta($user_id, '_created_at', current_time('mysql'));

        // Handle photo upload
        if (!empty($_FILES['cleaner_photo']['name'])) {
            $photo_id = $this->handle_file_upload($_FILES['cleaner_photo'], $user_id, 'cleaner_photo');
            if ($photo_id) {
                update_user_meta($user_id, '_photo_id', $photo_id);
                update_user_meta($user_id, '_photo_url', wp_get_attachment_url($photo_id));
            }
        }

        // Handle Aadhaar image upload
        if (!empty($_FILES['cleaner_aadhaar_image']['name'])) {
            $aadhaar_id = $this->handle_file_upload($_FILES['cleaner_aadhaar_image'], $user_id, 'aadhaar_card');
            if ($aadhaar_id) {
                update_user_meta($user_id, '_aadhaar_image_id', $aadhaar_id);
                update_user_meta($user_id, '_aadhaar_image_url', wp_get_attachment_url($aadhaar_id));
            }
        }

        // Get area manager's location and assign to cleaner
        $am_id = get_current_user_id();
        $am_state = get_user_meta($am_id, 'state', true);
        $am_city = get_user_meta($am_id, 'city', true);
        if ($am_state) update_user_meta($user_id, 'state', $am_state);
        if ($am_city) update_user_meta($user_id, 'city', $am_city);

        // Log Activity
        $current_user = wp_get_current_user();
        $role = in_array('administrator', $current_user->roles) ? 'administrator' : 'area_manager';
        $this->log_activity($current_user->ID, $role, 'create_cleaner', $user_id, $name, 'Created new cleaner account');

        wp_send_json_success([
            'message' => 'Cleaner account created successfully',
            'cleaner_id' => $user_id,
            'username' => $username,
            'password' => $password,
        ]);
    }

    /**
     * Handle file upload
     */
    private function handle_file_upload($file, $user_id, $type) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_handle_upload($file, ['test_form' => false]);
        
        if (isset($upload['error'])) {
            return false;
        }

        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name($file['name']),
            'post_content'   => '',
            'post_status'    => 'private',
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        
        if (!is_wp_error($attach_id)) {
            $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
            wp_update_attachment_metadata($attach_id, $attach_data);
            update_post_meta($attach_id, '_cleaner_id', $user_id);
            update_post_meta($attach_id, '_file_type', $type);
            return $attach_id;
        }

        return false;
    }

    /**
     * Get cleaners for current Area Manager
     */


    /**
     * Update cleaner details
     */
    public function update_cleaner() {
        if (!$this->verify_am_access()) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }

        $cleaner_id = intval($_POST['cleaner_id'] ?? 0);
        if (!$cleaner_id) {
            wp_send_json_error(['message' => 'Invalid cleaner ID']);
        }

        // Verify ownership
        $supervisor = get_user_meta($cleaner_id, '_supervised_by_area_manager', true);
        $current_user = wp_get_current_user();
        if (!in_array('administrator', (array) $current_user->roles) && $supervisor != get_current_user_id()) {
            wp_send_json_error(['message' => 'You can only update your own cleaners']);
        }

        if (!empty($_POST['cleaner_address'])) {
            update_user_meta($cleaner_id, '_cleaner_address', sanitize_textarea_field($_POST['cleaner_address']));
        }

        // Allow Admin/Manager to update Assigned Area Manager
        if (isset($_POST['assigned_area_manager'])) {
             $current_user = wp_get_current_user();
             if (in_array('administrator', (array)$current_user->roles) || in_array('manager', (array)$current_user->roles)) {
                 $new_am_id = intval($_POST['assigned_area_manager']);
                 if ($new_am_id > 0) {
                     // Verify the assigned user is actually an AM
                     $assigned_user = get_userdata($new_am_id);
                     if ($assigned_user && in_array('area_manager', (array)$assigned_user->roles)) {
                         update_user_meta($cleaner_id, '_supervised_by_area_manager', $new_am_id);
                     }
                 }
             }
        }

        // Log Activity
        $cleaner_user = get_userdata($cleaner_id);
        $role = in_array('administrator', $current_user->roles) ? 'administrator' : 'area_manager';
        $this->log_activity($current_user->ID, $role, 'update_cleaner', $cleaner_id, $cleaner_user->display_name, 'Updated cleaner profile details');

        wp_send_json_success(['message' => 'Cleaner updated successfully']);
    }

    /**
     * Delete cleaner account
     */
    public function delete_cleaner() {
        if (!$this->verify_am_access()) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }

        $cleaner_id = intval($_POST['cleaner_id'] ?? 0);
        if (!$cleaner_id) {
            wp_send_json_error(['message' => 'Invalid cleaner ID']);
        }

        // Verify ownership
        $supervisor = get_user_meta($cleaner_id, '_supervised_by_area_manager', true);
        $current_user = wp_get_current_user();
        if (!in_array('administrator', (array) $current_user->roles) && $supervisor != get_current_user_id()) {
            wp_send_json_error(['message' => 'You can only delete your own cleaners']);
        }

        // Check if cleaner has assigned visits
        $visits = get_posts([
            'post_type' => 'cleaning_visit',
            'meta_key' => '_cleaner_id',
            'meta_value' => $cleaner_id,
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);

        if (!empty($visits)) {
            wp_send_json_error(['message' => 'Cannot delete: Cleaner has assigned visits. Remove assignments first.']);
        }

        // Delete user
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($cleaner_id);

        // Log Activity
        $role = in_array('administrator', $current_user->roles) ? 'administrator' : 'area_manager';
        // Need to get name before deleting if possible, otherwise use ID
        $this->log_activity($current_user->ID, $role, 'delete_cleaner', $cleaner_id, 'Cleaner #' . $cleaner_id, 'Deleted cleaner account');

        wp_send_json_success(['message' => 'Cleaner deleted successfully']);
    }

    /**
     * Get cleaner's superiors (Area Manager + Sales Manager)
     */
    public function get_cleaner_superiors() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $user = wp_get_current_user();
        if (!in_array('solar_cleaner', (array) $user->roles)) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $superiors = [];

        // 1. Get Area Manager (Creator)
        $am_id = get_user_meta($user->ID, '_created_by', true);
        if ($am_id) {
            $am = get_userdata($am_id);
            if ($am && in_array('area_manager', (array) $am->roles)) {
                $superiors[] = [
                    'name' => $am->display_name,
                    'phone' => get_user_meta($am_id, 'phone', true),
                    'role' => 'Area Manager',
                    'email' => $am->user_email
                ];

                // 2. Get Sales Manager (Supervisor of AM)
                $sm_ids = get_user_meta($am_id, '_supervisor_ids', true);
                if (!empty($sm_ids) && is_array($sm_ids)) {
                    foreach ($sm_ids as $sm_id) {
                        $sm = get_userdata($sm_id);
                        if ($sm && in_array('sales_manager', (array) $sm->roles)) {
                            $superiors[] = [
                                'name' => $sm->display_name,
                                'phone' => get_user_meta($sm_id, 'phone', true),
                                'role' => 'Sales Manager (Supervisor)',
                                'email' => $sm->user_email
                            ];
                        }
                    }
                }
            }
        }

        // Fallback: If no superiors found, show admin
        if (empty($superiors)) {
            $admins = get_users(['role' => 'administrator', 'number' => 1]);
            if (!empty($admins)) {
                $admin = $admins[0];
                $superiors[] = [
                    'name' => 'Support Team',
                    'phone' => get_user_meta($admin->ID, 'phone', true),
                    'role' => 'Administrator',
                    'email' => $admin->user_email
                ];
            }
        }

        wp_send_json_success($superiors);
    }

    /**
     * Helper: Log Activity
     */
    private function log_activity($user_id, $role, $action, $target_id, $target_name, $details) {
        global $wpdb;
        $table = $wpdb->prefix . 'solar_activity_logs';
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'user_role' => $role,
            'action_type' => $action,
            'target_id' => $target_id,
            'target_name' => $target_name,
            'details' => $details,
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Get updated cleaner details API (Single or List)
     */
    public function get_cleaners() {
        if (!$this->verify_am_access()) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }

        $current_user = wp_get_current_user();
        
        // Admin/Manager sees all cleaners, AM sees their own, SM sees their AM's cleaners
        $meta_query = [];
        if (!in_array('administrator', (array) $current_user->roles) && !in_array('manager', (array) $current_user->roles)) {
            if (in_array('sales_manager', (array) $current_user->roles)) {
                $supervising_am = get_user_meta($current_user->ID, '_assigned_area_manager', true); // Fixed meta key
                if ($supervising_am) {
                    $meta_query[] = [
                        'key' => '_supervised_by_area_manager',
                        'value' => $supervising_am,
                    ];
                } else {
                    wp_send_json_success([]);
                    return;
                }
            } else {
                $meta_query[] = [
                    'key' => '_supervised_by_area_manager',
                    'value' => get_current_user_id(),
                ];
            }
        }

        $cleaners = get_users([
            'role' => 'solar_cleaner',
            'meta_query' => $meta_query,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        $result = [];
        foreach ($cleaners as $cleaner) {
            $aadhaar_img_id = get_user_meta($cleaner->ID, '_aadhaar_image_id', true);
            $photo_id = get_user_meta($cleaner->ID, '_photo_id', true);

            $result[] = [
                'id' => $cleaner->ID,
                'name' => $cleaner->display_name,
                'phone' => get_user_meta($cleaner->ID, 'phone', true),
                'email' => $cleaner->user_email,
                'aadhaar' => get_user_meta($cleaner->ID, '_aadhaar_number', true),
                'photo_url' => get_user_meta($cleaner->ID, '_photo_url', true),
                'aadhaar_image_url' => get_user_meta($cleaner->ID, '_aadhaar_image_url', true),
                'address' => get_user_meta($cleaner->ID, '_cleaner_address', true),
                'state' => get_user_meta($cleaner->ID, 'state', true),
                'city' => get_user_meta($cleaner->ID, 'city', true),
                'created_at' => get_user_meta($cleaner->ID, '_created_at', true),
                'supervisor_id' => get_user_meta($cleaner->ID, '_supervised_by_area_manager', true),
            ];
        }

        wp_send_json_success($result);
    }
}

// Initialize the class
new KSC_Cleaner_API();
