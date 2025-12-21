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
    }

    /**
     * Verify Area Manager or Admin access
     */
    private function verify_am_access() {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
        return in_array('area_manager', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles) ||
               in_array('manager', (array) $user->roles);
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
        update_user_meta($user_id, 'phone', $phone);
        update_user_meta($user_id, '_aadhaar_number', $aadhaar);
        update_user_meta($user_id, '_cleaner_address', $address);
        update_user_meta($user_id, '_supervised_by_area_manager', get_current_user_id());
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
    public function get_cleaners() {
        if (!$this->verify_am_access()) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }

        $current_user = wp_get_current_user();
        
        // Admin sees all cleaners, AM sees only their own
        $meta_query = [];
        if (!in_array('administrator', (array) $current_user->roles) && !in_array('manager', (array) $current_user->roles)) {
            $meta_query[] = [
                'key' => '_supervised_by_area_manager',
                'value' => get_current_user_id(),
            ];
        }

        $cleaners = get_users([
            'role' => 'solar_cleaner',
            'meta_query' => $meta_query,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        $result = [];
        foreach ($cleaners as $cleaner) {
            $result[] = [
                'id' => $cleaner->ID,
                'name' => $cleaner->display_name,
                'phone' => get_user_meta($cleaner->ID, 'phone', true),
                'email' => $cleaner->user_email,
                'aadhaar' => get_user_meta($cleaner->ID, '_aadhaar_number', true),
                'photo_url' => get_user_meta($cleaner->ID, '_photo_url', true),
                'address' => get_user_meta($cleaner->ID, '_cleaner_address', true),
                'state' => get_user_meta($cleaner->ID, 'state', true),
                'city' => get_user_meta($cleaner->ID, 'city', true),
                'created_at' => get_user_meta($cleaner->ID, '_created_at', true),
            ];
        }

        wp_send_json_success($result);
    }

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

        // Update fields
        if (!empty($_POST['cleaner_name'])) {
            wp_update_user([
                'ID' => $cleaner_id,
                'display_name' => sanitize_text_field($_POST['cleaner_name']),
            ]);
        }

        if (!empty($_POST['cleaner_phone'])) {
            update_user_meta($cleaner_id, 'phone', sanitize_text_field($_POST['cleaner_phone']));
        }

        if (!empty($_POST['cleaner_address'])) {
            update_user_meta($cleaner_id, '_cleaner_address', sanitize_textarea_field($_POST['cleaner_address']));
        }

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

        wp_send_json_success(['message' => 'Cleaner deleted successfully']);
    }
}

// Initialize the class
new KSC_Cleaner_API();
