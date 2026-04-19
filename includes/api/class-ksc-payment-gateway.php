<?php
/**
 * Central Payment Gateway
 * 
 * Handles all Razorpay verification logic and routes post-payment actions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KSC_Payment_Gateway {

    public function __construct() {
        add_action('wp_ajax_verify_ksc_payment', [$this, 'verify_payment_endpoint']);
        add_action('wp_ajax_nopriv_verify_ksc_payment', [$this, 'verify_payment_endpoint']);
    }

    /**
     * Single unified endpoint to verify Razorpay callbacks
     */
    public function verify_payment_endpoint() {
        // We will log errors if anything fails for easier debugging
        if (!isset($_POST['razorpay_payment_id']) || !isset($_POST['razorpay_order_id']) || !isset($_POST['razorpay_signature']) || !isset($_POST['context'])) {
            wp_send_json_error(['message' => 'Missing payment parameters']);
            exit;
        }

        $payment_id = sanitize_text_field($_POST['razorpay_payment_id']);
        $order_id = sanitize_text_field($_POST['razorpay_order_id']);
        $signature = sanitize_text_field($_POST['razorpay_signature']);
        $context = sanitize_text_field($_POST['context']);
        $extra_data = isset($_POST['extra_data']) ? json_decode(stripslashes($_POST['extra_data']), true) : [];

        // 1. Verify Razorpay Signature
        if (!class_exists('SP_Razorpay_Light_Client')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'class-razorpay-light-client.php';
        }
        $razorpay = new SP_Razorpay_Light_Client();
        
        $is_valid = $razorpay->verify_signature([
            'razorpay_order_id' => $order_id,
            'razorpay_payment_id' => $payment_id,
            'razorpay_signature' => $signature
        ]);

        if (!$is_valid) {
            wp_send_json_error(['message' => 'Payment signature verification failed.']);
            exit;
        }

        // 2. Route the successful payment based on context
        try {
            switch ($context) {
                case 'cleaning_booking':
                    $this->process_cleaning_success($order_id, $payment_id, $extra_data);
                    break;
                case 'vendor_registration':
                case 'vendor_coverage':
                    $this->process_vendor_coverage_success($order_id, $payment_id, $extra_data);
                    break;
                default:
                    wp_send_json_error(['message' => 'Unknown payment context.']);
                    exit;
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Server error while processing payment: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * Handle successful Cleaning Booking payment
     */
    private function process_cleaning_success($order_id, $payment_id, $extra_data) {
        $pending_key = 'cleaning_booking_' . $order_id;
        $booking_data = get_transient($pending_key);

        if (!$booking_data) {
            wp_send_json_error(['message' => 'Booking session expired. Please contact support.']);
            exit;
        }

        // Create the CPT entry
        if (!class_exists('KSC_CF7_Cleaning_Integration')) {
            require_once plugin_dir_path(dirname(__DIR__)) . 'integrations/class-cf7-cleaning-integration.php';
        }
        $cfm = new KSC_CF7_Cleaning_Integration();
        
        $booking_id = 0;
        if (is_array($booking_data)) {
            // Data is the raw form data array stored during order creation
            $booking_id = $cfm->create_cleaning_booking($booking_data, 'paid');
        } else if (is_numeric($booking_data)) {
            // Data is already a booking ID (fallback)
            $booking_id = intval($booking_data);
        }

        if (!$booking_id || is_wp_error($booking_id)) {
             wp_send_json_error(['message' => 'Failed to create booking entry in database.']);
             exit;
        }

        // Update booking with payment details
        update_post_meta($booking_id, '_payment_status', 'paid');
        update_post_meta($booking_id, '_razorpay_order_id', $order_id);
        update_post_meta($booking_id, '_razorpay_payment_id', $payment_id);
        delete_transient($pending_key);

        // Send Notifications (Invoice + Bell)
        $cfm->send_client_notifications($booking_id);

        wp_send_json_success([
            'message' => 'Payment verified & booking confirmed!',
            'booking_id' => $booking_id,
        ]);
        exit;
    }

    /**
     * Handle successful Vendor Coverage payment
     */
    private function process_vendor_coverage_success($order_id, $payment_id, $extra_data) {
        $vendor_id = get_current_user_id();
        
        // During initial registration, they aren't logged in yet, so we pass vendor_id in extra_data
        if (!$vendor_id && isset($extra_data['vendor_id'])) {
            $vendor_id = intval($extra_data['vendor_id']);
        }
        
        if (!$vendor_id) {
            wp_send_json_error(['message' => 'You must be logged in or provide a valid vendor ID.']);
            exit;
        }

        if (!isset($extra_data['states']) || !isset($extra_data['cities']) || !isset($extra_data['amount'])) {
            wp_send_json_error(['message' => 'Missing coverage data.']);
            exit;
        }

        $states = is_array($extra_data['states']) ? array_map('sanitize_text_field', $extra_data['states']) : [];
        $cities = is_array($extra_data['cities']) ? array_map('sanitize_text_field', $extra_data['cities']) : [];
        $amount = floatval($extra_data['amount']);

        // Update Vendor Meta
        $current_states = get_user_meta($vendor_id, 'purchased_states', true) ?: [];
        $current_cities = get_user_meta($vendor_id, 'purchased_cities', true) ?: [];

        if (!is_array($current_states)) $current_states = [$current_states];
        if (!is_array($current_cities)) $current_cities = [$current_cities];

        $new_states = array_unique(array_merge($current_states, $states));
        $new_cities = array_unique(array_merge($current_cities, $cities));

        update_user_meta($vendor_id, 'purchased_states', $new_states);
        update_user_meta($vendor_id, 'purchased_cities', $new_cities);

        // Log payment in custom table
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'solar_vendor_payments',
            [
                'vendor_id' => $vendor_id,
                'razorpay_payment_id' => $payment_id,
                'razorpay_order_id' => $order_id,
                'amount' => $amount,
                'states_purchased' => implode(', ', $states),
                'cities_purchased' => implode(', ', $cities),
                'payment_status' => 'completed',
            ],
            ['%d', '%s', '%s', '%f', '%s', '%s', '%s']
        );
        
        // Finalize Approval Status
        update_user_meta($vendor_id, 'vendor_payment_status', 'completed');
        
        if (!class_exists('KSC_Public_API')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'class-public-api.php';
        }
        $public_api = new KSC_Public_API();
        $public_api->check_auto_approval($vendor_id);

        // Create bell notification for the vendor
        SP_Notifications_Manager::create_notification([
            'user_id' => $vendor_id,
            'message' => 'Coverage area expanded successfully! Added States:' . (!empty($states) ? implode(', ', $states) : 'None') . '. Cities: ' . (!empty($cities) ? implode(', ', $cities) : 'None') . '. Payment: ₹' . number_format($amount, 0),
            'type' => 'coverage_updated'
        ]);

        // Notifications
        if (class_exists('KSC_Email_Templates')) {
            $vendor_user = get_userdata($vendor_id);
            if ($vendor_user) {
                $site_name = get_bloginfo('name');
                $html_content = KSC_Email_Templates::get_vendor_invoice_html($vendor_id, $payment_id, $amount, $states, $cities);
                KSC_Email_Templates::send_styled_email($vendor_user->user_email, "Tax Invoice - Coverage Expansion", $html_content);
            }
        }

        wp_send_json_success([
            'message' => 'Payment verified & coverage areas updated.',
            'new_states' => $new_states,
            'new_cities' => $new_cities
        ]);
        exit;
    }
}

// Initialize
new KSC_Payment_Gateway();
