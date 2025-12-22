<?php
/**
 * Contact Form 7 Integration for Cleaning Service Bookings
 * 
 * Hooks into CF7 form submissions to create cleaning_service CPT entries.
 * Supports "Pay Before" (Razorpay) and "Pay After" options.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class KSC_CF7_Cleaning_Integration {

    /**
     * Form tag name for identification
     * Set this to match your CF7 form's hidden field or form ID
     */
    const FORM_TAG = 'solar-cleaning-booking';

    /**
     * Initialize hooks
     */
    public function __construct() {
        // Hook into CF7 before send mail to process booking
        add_action('wpcf7_before_send_mail', [$this, 'process_cleaning_booking']);
        
        // Register shortcode for showing calculated price
        add_shortcode('cleaning_price_calculator', [$this, 'render_price_calculator']);
        
        // AJAX for price calculation
        add_action('wp_ajax_calculate_cleaning_price', [$this, 'ajax_calculate_price']);
        add_action('wp_ajax_nopriv_calculate_cleaning_price', [$this, 'ajax_calculate_price']);
        
        // AJAX for Razorpay payment creation
        add_action('wp_ajax_create_cleaning_razorpay_order', [$this, 'create_razorpay_order']);
        add_action('wp_ajax_nopriv_create_cleaning_razorpay_order', [$this, 'create_razorpay_order']);
        
        // AJAX for verifying payment and creating booking
        add_action('wp_ajax_verify_cleaning_payment', [$this, 'verify_cleaning_payment']);
        add_action('wp_ajax_nopriv_verify_cleaning_payment', [$this, 'verify_cleaning_payment']);

        // AJAX for Pay After booking
        add_action('wp_ajax_create_pay_after_booking', [$this, 'create_pay_after_booking']);
        add_action('wp_ajax_nopriv_create_pay_after_booking', [$this, 'create_pay_after_booking']);
        
        add_action('wp_ajax_validate_cleaning_coupon', [$this, 'validate_coupon_ajax']);
        add_action('wp_ajax_nopriv_validate_cleaning_coupon', [$this, 'validate_coupon_ajax']);
    }

    /**
     * Process CF7 form submission for cleaning booking
     */
    public function process_cleaning_booking($contact_form) {
        $form_id = $contact_form->id();
        $submission = WPCF7_Submission::get_instance();
        
        if (!$submission) {
            return;
        }

        $data = $submission->get_posted_data();
        
        // Check if this is a cleaning booking form (by checking for required fields)
        if (empty($data['customer_name']) || empty($data['customer_phone']) || empty($data['system_size_kw'])) {
            return;
        }

        // Skip if payment option is "pay_before" - these are handled via Razorpay callback
        if (!empty($data['payment_option']) && $data['payment_option'] === 'pay_before') {
            return;
        }

        // Create booking for "Pay After" option
        $this->create_cleaning_booking($data);
    }

    /**
     * Create cleaning booking CPT entry
     */
    private function create_cleaning_booking($data, $payment_status = 'pending') {
        $customer_name = sanitize_text_field($data['customer_name']);
        $customer_phone = sanitize_text_field($data['customer_phone']);
        $customer_address = sanitize_textarea_field($data['customer_address'] ?? '');
        $system_size_kw = floatval($data['system_size_kw'] ?? 0);
        $plan_type = sanitize_text_field($data['plan_type'] ?? 'one_time');
        $payment_option = sanitize_text_field($data['payment_option'] ?? 'pay_after');
        $preferred_date = sanitize_text_field($data['preferred_date'] ?? '');
        $preferred_week = sanitize_text_field($data['preferred_week'] ?? '');

        // Calculate visits based on plan
        $visits_total = $this->get_visits_for_plan($plan_type);
        
        // Calculate total amount
        $price_data = $this->calculate_price($system_size_kw, $plan_type);
        $total_amount = $price_data['total'];

        // Apply Coupon if exists
        $coupon_code = isset($data['coupon_code']) ? sanitize_text_field($data['coupon_code']) : '';
        $applied_coupon_id = 0;

        if ($coupon_code) {
            $c_args = [
                'post_type' => 'solar_coupon',
                'meta_key' => '_coupon_code',
                'meta_value' => $coupon_code,
                'posts_per_page' => 1
            ];
            $c_query = new WP_Query($c_args);
            if ($c_query->have_posts()) {
                $c_query->the_post();
                $c_id = get_the_ID();
                $c_expiry = get_post_meta($c_id, '_expiry_date', true);
                
                if (!$c_expiry || strtotime($c_expiry) >= time()) {
                    $c_type = get_post_meta($c_id, '_discount_type', true);
                    $c_amount = get_post_meta($c_id, '_discount_amount', true);
                    
                    if ($c_type == 'percent') {
                        $discount = $total_amount * ($c_amount / 100);
                    } else {
                        $discount = $c_amount;
                    }
                    
                    $total_amount = max(0, $total_amount - $discount);
                    $applied_coupon_id = $c_id;
                }
                wp_reset_postdata();
            }
        }

        // Create post
        $post_id = wp_insert_post([
            'post_type'   => 'cleaning_service',
            'post_title'  => sprintf('Cleaning - %s (%s)', $customer_name, date('Y-m-d H:i')),
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            return false;
        }

        // Save meta data
        update_post_meta($post_id, '_customer_type', 'external');
        update_post_meta($post_id, '_customer_name', $customer_name);
        update_post_meta($post_id, '_customer_phone', $customer_phone);
        update_post_meta($post_id, '_customer_address', $customer_address);
        update_post_meta($post_id, '_system_size_kw', $system_size_kw);
        update_post_meta($post_id, '_plan_type', $plan_type);
        update_post_meta($post_id, '_visits_total', $visits_total);
        update_post_meta($post_id, '_visits_used', 0);
        update_post_meta($post_id, '_payment_status', $payment_status);
        update_post_meta($post_id, '_payment_option', $payment_option);
        update_post_meta($post_id, '_total_amount', $total_amount);
        update_post_meta($post_id, '_created_at', current_time('mysql'));
        
        if ($applied_coupon_id) {
            update_post_meta($post_id, '_applied_coupon_id', $applied_coupon_id);
            update_post_meta($post_id, '_applied_coupon_code', $coupon_code);
        }

        // Handle date preferences
        if (!empty($preferred_date)) {
            update_post_meta($post_id, '_preferred_date', $preferred_date);
        }
        
        if (!empty($preferred_week)) {
            update_post_meta($post_id, '_preferred_week', $preferred_week);
        }

        // Send Admin Notification
        $admin_email = get_option('admin_email');
        $subject = sprintf('New Cleaning Booking: %s - %s Plan', $customer_name, ucfirst(str_replace('_', ' ', $plan_type)));
        
        $message = "New Cleaning Service Booking Received:\n\n";
        $message .= "Customer: " . $customer_name . "\n";
        $message .= "Phone: " . $customer_phone . "\n";
        $message .= "System Size: " . $system_size_kw . " kW\n";
        $message .= "Plan: " . ucfirst(str_replace('_', ' ', $plan_type)) . "\n";
        $message .= "Amount: ₹" . number_format($total_amount) . "\n";
        $message .= "Payment Status: " . ucfirst($payment_status) . "\n";
        $message .= "Preferred Date: " . ($preferred_date ?: 'Not specified') . "\n\n";
        $message .= "View Booking: " . admin_url('post.php?post=' . $post_id . '&action=edit');

        wp_mail($admin_email, $subject, $message);

        // ✅ NEW: Add to Activity Stream / Notification Center
        if (class_exists('SP_Notifications_Manager')) {
            $admins = get_users(['role' => 'administrator']);
            foreach ($admins as $admin) {
                SP_Notifications_Manager::create_notification([
                    'user_id' => $admin->ID,
                    'project_id' => null, // No project ID for cleaning service yet, or could use $post_id if column supports generic post ID
                    'message' => 'New Cleaning Booking: ' . $customer_name . ' (' . ucfirst($plan_type) . ')',
                    'type' => 'info',
                    'status' => 'unread',
                ]);
            }
        }

        return $post_id;
    }

    /**
     * Get number of visits for plan type
     */
    private function get_visits_for_plan($plan_type) {
        switch ($plan_type) {
            case 'monthly': return 12;
            case '6_month': return 6;
            case 'yearly': return 12;
            default: return 1;
        }
    }

    /**
     * Calculate price based on kW and plan
     */
    private function calculate_price($kw, $plan_type) {
        $options = get_option('ksc_cleaning_options', []);
        $price_per_kw = floatval($options['cleaning_price_per_kw'] ?? 50);
        $discount_6month = floatval($options['cleaning_6month_discount'] ?? 10);
        $discount_yearly = floatval($options['cleaning_yearly_discount'] ?? 10);

        $visits = $this->get_visits_for_plan($plan_type);
        $base_price = $price_per_kw * $kw;
        $subtotal = $base_price * $visits;
        
        $discount = 0;
        if ($plan_type === '6_month') {
            $discount = $subtotal * ($discount_6month / 100);
        } elseif ($plan_type === 'yearly') {
            $discount = $subtotal * ($discount_yearly / 100);
        }

        $total = $subtotal - $discount;

        return [
            'base_price' => $base_price,
            'visits' => $visits,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'discount_percent' => ($plan_type === '6_month') ? $discount_6month : (($plan_type === 'yearly') ? $discount_yearly : 0),
            'total' => $total,
        ];
    }

    /**
     * AJAX: Calculate cleaning price
     */
    public function ajax_calculate_price() {
        $kw = floatval($_POST['kw'] ?? 0);
        $plan = sanitize_text_field($_POST['plan'] ?? 'one_time');

        if ($kw <= 0) {
            wp_send_json_error(['message' => 'Invalid system size']);
        }

        $price = $this->calculate_price($kw, $plan);
        wp_send_json_success($price);
    }

    /**
     * AJAX: Create Razorpay order for cleaning payment
     */
    public function create_razorpay_order() {
        $data = $_POST;
        
        // Validate required fields
        if (empty($data['customer_name']) || empty($data['customer_phone']) || empty($data['system_size_kw'])) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        $kw = floatval($data['system_size_kw']);
        $plan = sanitize_text_field($data['plan_type'] ?? 'one_time');
        $price = $this->calculate_price($kw, $plan);

        // Create Razorpay order
        $options = get_option('sp_vendor_options', []);
        $mode = $options['razorpay_mode'] ?? 'test';
        $key_id = ($mode === 'live') ? ($options['razorpay_live_key_id'] ?? '') : ($options['razorpay_test_key_id'] ?? '');
        $key_secret = ($mode === 'live') ? ($options['razorpay_live_key_secret'] ?? '') : ($options['razorpay_test_key_secret'] ?? '');

        if (empty($key_id) || empty($key_secret)) {
            wp_send_json_error(['message' => 'Payment gateway not configured']);
        }

        // Include Razorpay client
        if (!class_exists('SP_Razorpay_Light_Client')) {
            require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/class-razorpay-light-client.php';
        }
        
        $razorpay = new SP_Razorpay_Light_Client($key_id, $key_secret);
        $amount_paise = intval($price['total'] * 100);
        
        $order_data = [
            'amount' => $amount_paise,
            'currency' => 'INR',
            'receipt' => 'cleaning_' . time(),
            'notes' => [
                'customer_name' => sanitize_text_field($data['customer_name']),
                'customer_phone' => sanitize_text_field($data['customer_phone']),
                'plan_type' => $plan,
                'system_kw' => $kw,
                'preferred_date' => sanitize_text_field($data['preferred_date'] ?? ''),
            ],
        ];

        $order = $razorpay->create_order($order_data);

        if (isset($order['error'])) {
            wp_send_json_error(['message' => 'Error creating payment order']);
        }

        // Store pending booking data in transient
        $pending_key = 'cleaning_pending_' . $order['id'];
        set_transient($pending_key, $data, HOUR_IN_SECONDS);

        wp_send_json_success([
            'order_id' => $order['id'],
            'amount' => $amount_paise,
            'key_id' => $key_id,
            'currency' => 'INR',
            'name' => get_bloginfo('name'),
            'description' => sprintf('Solar Cleaning - %s Plan', ucfirst(str_replace('_', ' ', $plan))),
            'prefill' => [
                'name' => sanitize_text_field($data['customer_name']),
                'contact' => sanitize_text_field($data['customer_phone']),
            ],
        ]);
    }

    /**
     * AJAX: Verify payment and create booking
     */
    public function verify_cleaning_payment() {
        $order_id = sanitize_text_field($_POST['razorpay_order_id'] ?? '');
        $payment_id = sanitize_text_field($_POST['razorpay_payment_id'] ?? '');
        $signature = sanitize_text_field($_POST['razorpay_signature'] ?? '');

        if (empty($order_id) || empty($payment_id) || empty($signature)) {
            wp_send_json_error(['message' => 'Invalid payment data']);
        }

        // Get Razorpay keys
        $options = get_option('sp_vendor_options', []);
        $mode = $options['razorpay_mode'] ?? 'test';
        $key_secret = ($mode === 'live') ? ($options['razorpay_live_key_secret'] ?? '') : ($options['razorpay_test_key_secret'] ?? '');

        // Verify signature
        $generated_signature = hash_hmac('sha256', $order_id . '|' . $payment_id, $key_secret);
        
        if ($generated_signature !== $signature) {
            wp_send_json_error(['message' => 'Payment verification failed']);
        }

        // Get pending booking data
        $pending_key = 'cleaning_pending_' . $order_id;
        $data = get_transient($pending_key);

        if (!$data) {
            wp_send_json_error(['message' => 'Booking data expired. Please try again.']);
        }

        // Create the booking with paid status
        $booking_id = $this->create_cleaning_booking($data, 'paid');

        if ($booking_id) {
            update_post_meta($booking_id, '_razorpay_order_id', $order_id);
            update_post_meta($booking_id, '_razorpay_payment_id', $payment_id);
            delete_transient($pending_key);

            wp_send_json_success([
                'message' => 'Booking confirmed!',
                'booking_id' => $booking_id,
            ]);
        } else {
            wp_send_json_error(['message' => 'Error creating booking']);
        }
    }



    /**
     * AJAX Validate Coupon
     */
    public function validate_coupon_ajax() {
        $code = isset($_POST['coupon_code']) ? strtoupper(sanitize_text_field($_POST['coupon_code'])) : '';
        if (empty($code)) wp_send_json_error(['message' => 'Empty code']);

        $args = [
            'post_type' => 'solar_coupon',
            'meta_key' => '_coupon_code',
            'meta_value' => $code,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ];
        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            wp_send_json_error(['message' => 'Invalid coupon code']);
        }

        $query->the_post();
        $post_id = get_the_ID();
        $expiry = get_post_meta($post_id, '_expiry_date', true);

        if ($expiry && strtotime($expiry) < time()) {
            wp_send_json_error(['message' => 'Coupon expired']);
        }

        $type = get_post_meta($post_id, '_discount_type', true);
        $amount = get_post_meta($post_id, '_discount_amount', true);

        wp_send_json_success([
            'valid' => true,
            'code' => $code,
            'type' => $type,
            'amount' => floatval($amount),
            'message' => 'Coupon applied!'
        ]);
        
        wp_reset_postdata();
    }

    /**
     * Calculate discount based on coupon
     */
    private function calculate_discount($total_amount, $coupon_code) {
        if (empty($coupon_code)) {
            return 0;
        }

        $args = [
            'post_type' => 'solar_coupon',
            'meta_key' => '_coupon_code',
            'meta_value' => strtoupper($coupon_code),
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ];
        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return 0; // Invalid coupon
        }

        $query->the_post();
        $post_id = get_the_ID();
        $expiry = get_post_meta($post_id, '_expiry_date', true);

        if ($expiry && strtotime($expiry) < time()) {
            wp_reset_postdata();
            return 0; // Coupon expired
        }

        $type = get_post_meta($post_id, '_discount_type', true);
        $amount = floatval(get_post_meta($post_id, '_discount_amount', true));
        wp_reset_postdata();

        $discount = 0;
        if ($type === 'percentage') {
            $discount = $total_amount * ($amount / 100);
        } elseif ($type === 'fixed_cart') {
            $discount = $amount;
        }

        return min($discount, $total_amount); // Discount cannot exceed total amount
    }

    /**
     * AJAX: Create Pay After Service booking
     */
    public function create_pay_after_booking() {
        // Verify nonce usually, but for public form we check required fields
        $data = $_POST;
        
        if (empty($data['customer_name']) || empty($data['customer_phone']) || empty($data['system_size_kw'])) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        $booking_id = $this->create_cleaning_booking($data, 'pending');

        if ($booking_id) {
            wp_send_json_success([
                'message' => 'Booking created successfully!',
                'booking_id' => $booking_id,
            ]);
        } else {
            wp_send_json_error(['message' => 'Error creating booking. Please try again.']);
        }
    }

    /**
     * Render price calculator shortcode
     */
    public function render_price_calculator() {
        ob_start();
        ?>
        <div id="cleaning-price-calculator" style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin: 20px 0;">
            <h4 style="margin-top: 0;">💰 Estimated Price</h4>
            <div id="price-display">
                <p>Enter system size and select plan to see price.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize the class
add_action('plugins_loaded', function() {
    new KSC_CF7_Cleaning_Integration();
});
