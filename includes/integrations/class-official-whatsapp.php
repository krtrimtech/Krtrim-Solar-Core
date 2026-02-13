<?php
/**
 * Official WhatsApp Cloud API Integration
 * 
 * Handles sending messages via the Official Meta WhatsApp Cloud API.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class KSC_Official_WhatsApp {

    private $phone_id;
    private $business_id;
    private $access_token;
    private $api_version = 'v19.0'; // Updated to recent version

    public function __construct() {
        $options = get_option('sp_notification_options', []);
        $this->phone_id = $options['whatsapp_phone_id'] ?? '';
        $this->business_id = $options['whatsapp_business_id'] ?? '';
        $this->access_token = $options['whatsapp_access_token'] ?? '';
    }

    /**
     * Send a template or text message
     * Note: Official API requires templates for initiating conversation.
     * For simplicity in this v1, we will try to use text messages which work if the user is within the 24h window.
     * Ideally, we should register templates. For now, we assume user initiated or we are using utility templates if configured.
     * 
     * However, the 'text' object only works for user-initiated conversations.
     * To make this robust, we should ideally use templates.
     * BUT, since the user asked for "Official API" support as an alternative to WAHA for these "Notifications", 
     * we will implement the standard text message payload.
     * 
     * @param string $phone
     * @param string $message
     * @return bool
     */
    public function send_message($phone, $message) {
        if (empty($this->phone_id) || empty($this->access_token)) {
            // error_log('[KSC Official WhatsApp] Credentials missing.');
            return false;
        }

        // Format Phone (Remove + and leading zeros)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 2) !== '91' && strlen($phone) == 10) {
            $phone = '91' . $phone;
        }

        $url = "https://graph.facebook.com/{$this->api_version}/{$this->phone_id}/messages";

        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message
            ]
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 20
        ]);

        if (is_wp_error($response)) {
            // error_log('[KSC Official WhatsApp] HTTP Error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            return true;
        } else {
            // error_log('[KSC Official WhatsApp] API Error (' . $code . '): ' . print_r($result, true));
            return false;
        }
    }
}
