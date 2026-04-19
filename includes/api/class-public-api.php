<?php
/**
 * Public API Class
 * 
 * Handles public (non-authenticated) AJAX endpoints.
 * Includes Razorpay payment order creation.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.1
 * @updated 2025-11-30 - Added Razorpay order creation
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Public_API {
    
    public function __construct() {
        // Debug logging
        // error_log('KSC_Public_API: Constructor called');
        
        // Vendor registration
        add_action('wp_ajax_complete_vendor_registration', [$this, 'complete_vendor_registration']);
        add_action('wp_ajax_nopriv_complete_vendor_registration', [$this, 'complete_vendor_registration']);
        
        // Razorpay order creation
        add_action('wp_ajax_create_razorpay_order', [$this, 'create_razorpay_order']);  // ✅ Add this
        add_action('wp_ajax_nopriv_create_razorpay_order', [$this, 'create_razorpay_order']);  // ✅ Keep this
        // error_log('KSC_Public_API: Razorpay action registered');
        
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
        // error_log('KSC_Public_API: complete_vendor_registration called');
        check_ajax_referer('vendor_registration_nonce', 'nonce');
        
        // Parse registration data
        $registration_data = isset($_POST['registration_data']) ? json_decode(stripslashes($_POST['registration_data']), true) : [];
        $payment_response = isset($_POST['payment_response']) ? json_decode(stripslashes($_POST['payment_response']), true) : [];
        
        if (empty($registration_data)) {
            // error_log('KSC_Public_API: No registration data found');
            wp_send_json_error(['message' => 'Invalid registration data']);
        }
        
        $basic_info = $registration_data['basic_info'] ?? [];
        $coverage = $registration_data['coverage'] ?? [];
        
        $email = sanitize_email($basic_info['email'] ?? '');
        
        $user_id = 0;
        $is_new_user = true;
        
        if (email_exists($email)) {
            $existing_user = get_user_by('email', $email);
            // If the user exists but hasn't completed payment, allow updating their info
            if (get_user_meta($existing_user->ID, 'vendor_payment_status', true) !== 'completed') {
                $user_id = $existing_user->ID;
                $is_new_user = false;
            } else {
                wp_send_json_error(['message' => 'Email already registered and active.']);
            }
        }
        
        if ($is_new_user) {
            // Generate username from email prefix
            $email_parts = explode('@', $email);
            $base_username = sanitize_user($email_parts[0]);
            $username = $base_username;
            $counter = 1;
            
            // Ensure username is unique
            while (username_exists($username)) {
                $username = $base_username . $counter;
                $counter++;
            }
            
            $password = $basic_info['password'] ?? '';
            $user_id = wp_create_user($username, $password, $email);
            
            if (is_wp_error($user_id)) {
                wp_send_json_error(['message' => $user_id->get_error_message()]);
            }
            
            $user = new WP_User($user_id);
            $user->set_role('solar_vendor');
        }

        $company_name = sanitize_text_field($basic_info['company_name'] ?? '');
        $phone = sanitize_text_field($basic_info['phone'] ?? '');
        $full_name = sanitize_text_field($basic_info['full_name'] ?? '');
        
        $states = $coverage['states'] ?? [];
        $cities = $coverage['cities'] ?? [];
        $amount = floatval($registration_data['total_amount'] ?? 0);
        
        // Update user meta
        update_user_meta($user_id, 'first_name', $full_name); // Store full name
        update_user_meta($user_id, 'company_name', $company_name);
        update_user_meta($user_id, 'phone', $phone);
        update_user_meta($user_id, 'purchased_states', $states);
        update_user_meta($user_id, 'purchased_cities', $cities);
        
        // Check if this is the final callback where payment is complete
        if (!empty($payment_response)) {
            // This case won't be hit anymore by the JS, but we leave it for legacy fallback
            // We now use verify_ksc_payment webhook logic instead
        }
        
        if ($is_new_user) {
            // Generate verification token
            $token = wp_generate_password(32, false);
            update_user_meta($user_id, 'email_verification_token', $token);
            update_user_meta($user_id, 'email_verified', 'no');
            update_user_meta($user_id, 'account_approved', 'pending');
            update_user_meta($user_id, 'vendor_payment_status', 'pending');
            
            // Send verification email to Vendor
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
            
            // Notify Admin
            wp_new_user_notification($user_id, null, 'admin');
        }
        
        wp_send_json_success([
            'message' => 'Account created/updated successfully! Proceeding to payment.',
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
        
        // Log the user in automatically so they can seamlessly continue to payment
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Check for auto-approval
        $this->check_auto_approval($user_id);
        
        // Redirect back to registration page to resume at step 2
        $redirect_url = home_url('/vendor-registration/?vreg_resume=2');
        
        // Try wp_redirect first (works if no headers sent yet)
        if (!headers_sent()) {
            wp_redirect($redirect_url);
            exit;
        }
        
        // FALLBACK: If redirect failed (in-app browser, headers already sent, etc.)
        // Show a friendly page with a manual link so the user is never stuck.
        $login_url = wp_login_url($redirect_url);
        wp_die(
            '<div style="text-align:center; padding:40px; font-family:sans-serif;">'
            . '<h2 style="color:#10b981;">✅ Email Verified Successfully!</h2>'
            . '<p style="font-size:16px; color:#4b5563;">Your email has been verified. You can now continue your registration.</p>'
            . '<p style="margin-top:20px;">'
            . '<a href="' . esc_url($redirect_url) . '" style="display:inline-block; background:#667eea; color:#fff; padding:12px 28px; border-radius:8px; text-decoration:none; font-weight:600;">Continue Registration →</a>'
            . '</p>'
            . '<p style="margin-top:15px; font-size:14px; color:#9ca3af;">If you opened this link on a different device, '
            . '<a href="' . esc_url($login_url) . '">log in here</a> to continue.</p>'
            . '</div>',
            'Email Verified — Solar Dashboard'
        );
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
     * Create Razorpay order for vendor registration payment
     */
    public function create_razorpay_order() {
        // error_log('=== KSC_Public_API: create_razorpay_order() START ===');
        // error_log('KSC_Public_API: POST data: ' . print_r($_POST, true));
        // error_log('KSC_Public_API: User logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
        // error_log('KSC_Public_API: Current user ID: ' . get_current_user_id());
        
        check_ajax_referer('vendor_registration_nonce', 'nonce');
        // error_log('KSC_Public_API: Nonce verified');
        
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        // error_log('KSC_Public_API: Amount: ' . $amount);
        
        if ($amount <= 0) {
            // error_log('KSC_Public_API: Amount validation failed');
            wp_send_json_error(['message' => 'Invalid amount']);
        }
        
        // Get Razorpay settings
        $options = get_option('sp_vendor_options');
        $mode = isset($options['razorpay_mode']) ? $options['razorpay_mode'] : 'test';
        
        // Select correct credentials based on mode
        if ($mode === 'live') {
            $key_id = isset($options['razorpay_live_key_id']) ? $options['razorpay_live_key_id'] : '';
            $key_secret = isset($options['razorpay_live_key_secret']) ? $options['razorpay_live_key_secret'] : '';
        } else {
            $key_id = isset($options['razorpay_test_key_id']) ? $options['razorpay_test_key_id'] : '';
            $key_secret = isset($options['razorpay_test_key_secret']) ? $options['razorpay_test_key_secret'] : '';
        }
        
        if (empty($key_id) || empty($key_secret)) {
            wp_send_json_error(['message' => 'Razorpay payment gateway is not configured. Please contact administrator.']);
        }
        
        // Load Razorpay client
        require_once plugin_dir_path(dirname(__FILE__)) . 'class-razorpay-light-client.php';
        
        try {
            // Initialize with key_id and key_secret (constructor will be modified to accept them)
            $razorpay = new SP_Razorpay_Light_Client();
            
            // Create order - convert amount to paise
            $amount_in_paise = $amount * 100;
            $receipt_id = 'vendor_reg_' . time();
            
            $order_data = [
                'amount' => $amount_in_paise,
                'currency' => 'INR',
                'receipt' => $receipt_id,
                'notes' => [
                    'registration' => 'vendor_coverage'
                ]
            ];
            $result = $razorpay->create_order($order_data);
            
            if (isset($result['success']) && !$result['success']) {
                $error_msg = isset($result['message']) ? $result['message'] : 'Failed to create payment order';
                wp_send_json_error(['message' => $error_msg]);
            }
            
            $order_id = null;
            if (isset($result['id'])) {
                $order_id = $result['id'];
            } else if (isset($result['data']['id'])) {
                $order_id = $result['data']['id'];
            }
            
            if ($order_id) {
                wp_send_json_success([
                    'key' => $key_id,  // Razorpay public key for frontend
                    'order_id' => $order_id,
                    'amount' => $amount_in_paise,
                    'currency' => 'INR'
                ]);
            } else {
                wp_send_json_error(['message' => 'Failed to retrieve order ID from Razorpay']);
            }
        } catch (Exception $e) {
            // error_log('Razorpay order creation error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Payment order creation failed: ' . $e->getMessage()]);
        }
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
    public function check_auto_approval($user_id) {
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
            
            // error_log("Vendor $user_id auto-approved after email verification");
        }
    }
}