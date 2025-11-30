<?php
/**
 * Public API Class
 * 
 * Handles public (non-authenticated) AJAX endpoints.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Public_API {
    
    public function __construct() {
        // Vendor registration
        add_action('wp_ajax_complete_vendor_registration', [$this, 'complete_vendor_registration']);
        add_action('wp_ajax_nopriv_complete_vendor_registration', [$this, 'complete_vendor_registration']);
        
        // Email verification
        add_action('init', [$this, 'verify_vendor_email']);
        add_action('wp_ajax_resend_verification_email', [$this, 'resend_verification_email']);
        
        // Coverage areas
        add_action('wp_ajax_get_coverage_areas', [$this, 'get_coverage_areas']);
        add_action('wp_ajax_nopriv_get_coverage_areas', [$this, 'get_coverage_areas']);
        
        // Email checking
        add_action('wp_ajax_check_email_exists', [$this, 'check_email_exists']);
        add_action('wp_ajax_nopriv_check_email_exists', [$this, 'check_email_exists']);
    }
    
    /**
     * Complete vendor registration
     */
    public function complete_vendor_registration() {
        check_ajax_referer('vendor_registration_nonce', 'nonce');
        
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $company_name = sanitize_text_field($_POST['company_name']);
        $phone = sanitize_text_field($_POST['phone']);
        $states = isset($_POST['states']) && is_array($_POST['states']) ? $_POST['states'] : [];
        $cities = isset($_POST['cities']) && is_array($_POST['cities']) ? $_POST['cities'] : [];
        $payment_response = json_decode(stripslashes($_POST['payment_response']), true);
        
        if (username_exists($username)) {
            wp_send_json_error(['message' => 'Username already exists.']);
        }
        
        if (email_exists($email)) {
            wp_send_json_error(['message' => 'Email already registered.']);
        }
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }
        
        $user = new WP_User($user_id);
        $user->set_role('solar_vendor');
        
        update_user_meta($user_id, 'company_name', $company_name);
        update_user_meta($user_id, 'phone', $phone);
        update_user_meta($user_id, 'purchased_states', $states);
        update_user_meta($user_id, 'purchased_cities', $cities);
        
        // Store payment details
        if (!empty($payment_response)) {
            global $wpdb;
            $table = $wpdb->prefix . 'solar_vendor_payments';
            $wpdb->insert($table, [
                'vendor_id' => $user_id,
                'razorpay_payment_id' => $payment_response['razorpay_payment_id'],
                'razorpay_order_id' => $payment_response['razorpay_order_id'] ?? '',
                'amount' => floatval($_POST['amount']),
                'states_purchased' => json_encode($states),
                'cities_purchased' => json_encode($cities),
                'payment_status' => 'completed',
                'payment_date' => current_time('mysql')
            ]);
            
            update_user_meta($user_id, 'vendor_payment_status', 'completed');
        }
        
        // Generate verification token
        $token = wp_generate_password(32, false);
        update_user_meta($user_id, 'email_verification_token', $token);
        update_user_meta($user_id, 'email_verified', 'no');
        update_user_meta($user_id, 'account_approved', 'pending');
        
        // Send verification email
        $verify_url = add_query_arg([
            'action' => 'verify_vendor_email',
            'token' => $token,
            'user' => $user_id
        ], home_url());
        
        $subject = 'Verify Your Email - Solar Dashboard';
        $message = sprintf(
            "Please verify your email address by clicking the link below:\n\n%s\n\nOnce verified, your account will be automatically approved.",
            $verify_url
        );
        
        wp_mail($email, $subject, $message);
        
        wp_send_json_success([
            'message' => 'Registration successful! Please check your email to verify your account.',
            'user_id' => $user_id
        ]);
    }
    
    /**
     * Verify vendor email
     */
    public function verify_vendor_email() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'verify_vendor_email') {
            return;
        }
        
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
        
        if (empty($token) || empty($user_id)) {
            wp_die('Invalid verification link.');
        }
        
        $stored_token = get_user_meta($user_id, 'email_verification_token', true);
        
        if ($token !== $stored_token) {
            wp_die('Invalid or expired verification token.');
        }
        
        update_user_meta($user_id, 'email_verified', 'yes');
        delete_user_meta($user_id, 'email_verification_token');
        
        // Check for auto-approval
        $this->check_auto_approval($user_id);
        
        $is_approved = get_user_meta($user_id, 'account_approved', true) === 'yes';
        
        if ($is_approved) {
            $message = 'Email verified successfully! Your account has been automatically approved. You can now <a href="' . wp_login_url() . '">login</a> and start bidding on projects.';
        } else {
            $message = 'Email verified successfully! Your account is pending payment verification. You will be automatically approved once payment is confirmed.';
        }
        
        wp_die($message);
    }
    
    /**
     * Resend verification email
     */
    public function resend_verification_email() {
        check_ajax_referer('resend_email_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
        }
        
        $user = wp_get_current_user();
        
        if (!in_array('solar_vendor', $user->roles)) {
            wp_send_json_error(['message' => 'Not authorized']);
        }
        
        $email_verified = get_user_meta($user->ID, 'email_verified', true);
        if ($email_verified === 'yes') {
            wp_send_json_error(['message' => 'Your email is already verified']);
        }
        
        $token = wp_generate_password(32, false);
        update_user_meta($user->ID, 'email_verification_token', $token);
        
        $verify_url = add_query_arg([
            'action' => 'verify_vendor_email',
            'token' => $token,
            'user' => $user->ID
        ], home_url());
        
        $subject = 'Verify Your Email - Solar Dashboard';
        $message = sprintf(
            "Please verify your email address by clicking the link below:\n\n%s\n\n",
            $verify_url
        );
        
        $sent = wp_mail($user->user_email, $subject, $message);
        
        if ($sent) {
            wp_send_json_success(['message' => 'Verification email sent! Please check your inbox.']);
        } else {
            wp_send_json_error(['message' => 'Failed to send email. Please try again later.']);
        }
    }
    
    /**
     * Get coverage areas (states/cities)
     */
    public function get_coverage_areas() {
        $json_file = plugin_dir_path(dirname(__FILE__)) . '../assets/data/indian-states-cities.json';
        
        if (!file_exists($json_file)) {
            wp_send_json_error(['message' => 'Coverage data not found']);
            return;
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['states'])) {
            wp_send_json_error(['message' => 'Invalid coverage data']);
            return;
        }
        
        wp_send_json_success($data['states']);
    }
    
    /**
     * Check if email exists
     */
    public function check_email_exists() {
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($email)) {
            wp_send_json_error(['message' => 'Email is required']);
        }
        
        $exists = email_exists($email);
        
        wp_send_json_success(['exists' => (bool)$exists]);
    }
    
    /**
     * Check if vendor meets criteria for auto-approval
     */
    private function check_auto_approval($user_id) {
        $current_status = get_user_meta($user_id, 'account_approved', true);
        if ($current_status === 'yes') {
            return;
        }
        
        $payment_complete = get_user_meta($user_id, 'vendor_payment_status', true) === 'completed';
        $email_verified = get_user_meta($user_id, 'email_verified', true) === 'yes';
        
        if ($payment_complete && $email_verified) {
            update_user_meta($user_id, 'account_approved', 'yes');
            update_user_meta($user_id, 'account_approved_date', current_time('mysql'));
            update_user_meta($user_id, 'account_approved_by', 'auto');
            update_user_meta($user_id, 'approval_method', 'auto');
            
            do_action('sp_vendor_approved', $user_id);
            
            error_log("Vendor $user_id auto-approved after email verification");
        }
    }
}
