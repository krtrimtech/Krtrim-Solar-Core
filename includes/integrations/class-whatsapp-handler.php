<?php
/**
 * WhatsApp Handler Class
 * 
 * Central handler for dispatching WhatsApp messages via selected provider:
 * 1. WAHA (WhatsApp HTTP API)
 * 2. Official WhatsApp Cloud API
 * 
 * @package Krtrim_Solar_Core
 * @since 1.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class KSC_WhatsApp_Handler {

    /**
     * Initialize the handler
     */
    public function __construct() {
        // Hook into the plugin's WhatsApp notification action
        add_action('ksc_send_whatsapp_message', [$this, 'send_message'], 10, 2);
    }

    /**
     * Send WhatsApp message via selected provider
     * 
     * @param string $phone Phone number
     * @param string $message Message text
     * @return bool Success status
     */
    public function send_message($phone, $message) {
        $notification_options = get_option('sp_notification_options', []);
        $provider = $notification_options['whatsapp_provider'] ?? 'waha';

        if ($provider === 'official') {
            return $this->send_via_official($phone, $message);
        } else {
            return $this->send_via_waha($phone, $message);
        }
    }

    /**
     * Send via Official Cloud API
     */
    private function send_via_official($phone, $message) {
        if (!class_exists('KSC_Official_WhatsApp')) {
            error_log('[KSC WhatsApp] Official integration class not found.');
            return false;
        }
        
        $official = new KSC_Official_WhatsApp();
        return $official->send_message($phone, $message);
    }

    /**
     * Send via WAHA API
     */
    private function send_via_waha($phone, $message) {
        $options = get_option('sp_notification_options', []);
        $api_url = $options['waha_api_url'] ?? '';
        $session_name = $options['waha_session_name'] ?? 'default';
        $api_key = $options['waha_api_key'] ?? '';

        if (empty($api_url)) {
            // Fallback to old options if migration hasn't happened yet (optional safety)
            $old_options = get_option('ksc_waha_options', []);
            $api_url = $old_options['api_url'] ?? '';
            $session_name = $old_options['session_name'] ?? 'default';
            $api_key = $old_options['api_key'] ?? '';

            if (empty($api_url)) {
                error_log('[KSC WAHA] API URL not configured');
                return false;
            }
        }

        // Format phone number
        $phone = preg_replace('/\D/', '', $phone); 
        if (strlen($phone) == 10) {
            $phone = '91' . $phone; // Add India country code
        }
        $chat_id = $phone . '@c.us';

        $endpoint = rtrim($api_url, '/') . '/api/sendText';
        
        $headers = ['Content-Type' => 'application/json'];
        if (!empty($api_key)) {
            $headers['X-Api-Key'] = $api_key;
        }

        $body = [
            'session' => $session_name,
            'chatId' => $chat_id,
            'text' => $message,
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            error_log('[KSC WAHA] Error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        
        if ($code >= 200 && $code < 300) {
            return true;
        } else {
            error_log('[KSC WAHA] Failed (' . $code . '): ' . wp_remote_retrieve_body($response));
            return false;
        }
    }
}

// Initialize
new KSC_WhatsApp_Handler();
