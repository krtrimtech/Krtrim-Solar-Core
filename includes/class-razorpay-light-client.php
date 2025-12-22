<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SP_Razorpay_Light_Client {
    private $key_id;
    private $key_secret;
    const API_URL = 'https://api.razorpay.com/v1/';

    public function __construct() {
        $options = get_option('sp_vendor_options');
        $mode = isset($options['razorpay_mode']) ? $options['razorpay_mode'] : 'test';

        if ($mode === 'live') {
            $this->key_id = isset($options['razorpay_live_key_id']) ? $options['razorpay_live_key_id'] : '';
            $this->key_secret = isset($options['razorpay_live_key_secret']) ? $options['razorpay_live_key_secret'] : '';
        } else {
            $this->key_id = isset($options['razorpay_test_key_id']) ? $options['razorpay_test_key_id'] : '';
            $this->key_secret = isset($options['razorpay_test_key_secret']) ? $options['razorpay_test_key_secret'] : '';
        }
    }

    /**
     * Creates a Razorpay Order.
     *
     * @param float $amount The order amount in the smallest currency unit (e.g., paise for INR).
     * @param string $currency The currency code (e.g., 'INR').
     * @param string $receipt_id A unique identifier for the receipt.
     * @return array The API response from Razorpay.
     */
    /**
     * Creates a Razorpay Order.
     *
     * @param array $data Order data (amount, currency, receipt, notes, etc.)
     * @return array The API response from Razorpay.
     */
    public function create_order($data) {
        $url = self::API_URL . 'orders';
        
        // Ensure defaults
        $body = wp_parse_args($data, [
            'currency' => 'INR',
            'receipt' => uniqid(),
            'notes' => []
        ]);
        
        // If args were passed individually (legacy support), not needed here since we control calls, but good for safety if used elsewhere
        if (!is_array($data)) {
            // Fallback if someone calls it with (amount, currency, receipt) - though PHP would error on arg count first if we change signature.
            // Let's just stick to array support as we are fixing the specific broken call.
        }

        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->key_id . ':' . $this->key_secret),
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 60,
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($response_body, true);
        
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code >= 200 && $http_code < 300) {
            // Razorpay returns the order object directly on success
            if (isset($decoded_body['id'])) {
                return $decoded_body; // Return the whole object to be compatible with array access like $order['id']
            }
            return ['success' => true, 'data' => $decoded_body];
        } else {
            $error_message = isset($decoded_body['error']['description']) ? $decoded_body['error']['description'] : 'An unknown error occurred with Razorpay.';
            return ['success' => false, 'message' => $error_message];
        }
    }

    /**
     * Verifies the payment signature.
     *
     * @param array $attributes An array containing razorpay_order_id, razorpay_payment_id, and razorpay_signature.
     * @return bool True if the signature is valid, false otherwise.
     */
    public function verify_signature(array $attributes) {
        $expected_signature = $attributes['razorpay_signature'];
        $order_id = $attributes['razorpay_order_id'];
        $payment_id = $attributes['razorpay_payment_id'];

        $payload = $order_id . '|' . $payment_id;

        $generated_signature = hash_hmac('sha256', $payload, $this->key_secret);

        return hash_equals($generated_signature, $expected_signature);
    }
}
