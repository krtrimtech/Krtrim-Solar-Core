<?php
/**
 * Cleaning Service Notifications
 * 
 * Handles notifications for cleaning service visits:
 * - Pre-service reminder (1 day before)
 * - Post-service review request
 * 
 * @package Krtrim_Solar_Core
 * @since 1.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Cleaning_Notifications {

    /**
     * Initialize hooks
     */
    public function __construct() {
        // Schedule daily cron for pre-service reminders
        add_action('init', [$this, 'schedule_cron']);
        add_action('ksc_send_cleaning_reminders', [$this, 'send_preservice_reminders']);
        
        // Hook into visit completion for post-service notifications
        add_action('ksc_cleaning_visit_completed', [$this, 'send_postservice_review_request'], 10, 2);
        
        // Trigger post-service notification from API
        add_action('wp_ajax_trigger_cleaning_review_request', [$this, 'trigger_review_request']);
    }

    /**
     * Schedule daily cron job
     */
    public function schedule_cron() {
        if (!wp_next_scheduled('ksc_send_cleaning_reminders')) {
            wp_schedule_event(strtotime('08:00:00'), 'daily', 'ksc_send_cleaning_reminders');
        }
    }

    /**
     * Send pre-service reminders (1 day before)
     */
    public function send_preservice_reminders() {
        // Check if pre-service reminders are enabled in settings
        $options = get_option('sp_notification_options', []);
        if (empty($options['cleaning_preservice_reminder'])) {
            return; // Disabled in settings
        }
        
        global $wpdb;
        
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        // Get visits scheduled for tomorrow that haven't been notified
        $visits = get_posts([
            'post_type' => 'cleaning_visit',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_scheduled_date',
                    'value' => $tomorrow,
                ],
                [
                    'key' => '_status',
                    'value' => 'scheduled',
                ],
                [
                    'key' => '_preservice_notification_sent',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        foreach ($visits as $visit) {
            $visit_id = $visit->ID;
            $service_id = get_post_meta($visit_id, '_service_id', true);
            $cleaner_id = get_post_meta($visit_id, '_cleaner_id', true);
            
            $customer_name = get_post_meta($service_id, '_customer_name', true);
            $customer_phone = get_post_meta($service_id, '_customer_phone', true);
            $customer_address = get_post_meta($service_id, '_customer_address', true);
            $scheduled_time = get_post_meta($visit_id, '_scheduled_time', true) ?: '09:00';
            
            // Notify cleaner
            if ($cleaner_id) {
                SP_Notifications_Manager::create_notification([
                    'user_id' => $cleaner_id,
                    'message' => sprintf(
                        '📅 Tomorrow\'s Visit: %s at %s\n📍 %s\n📞 %s',
                        $customer_name,
                        $scheduled_time,
                        $customer_address ?: 'Address not provided',
                        $customer_phone
                    ),
                    'type' => 'cleaning_reminder',
                ]);
            }

            // Send SMS/WhatsApp to customer (if WhatsApp integration exists)
            $this->send_customer_reminder($customer_phone, $customer_name, $tomorrow, $scheduled_time);

            // Mark as notified
            update_post_meta($visit_id, '_preservice_notification_sent', current_time('mysql'));
        }
        
        // Log the cron run
        update_option('ksc_last_cleaning_reminder_cron', current_time('mysql'));
    }

    /**
     * Send reminder to customer via WhatsApp/SMS
     */
    private function send_customer_reminder($phone, $name, $date, $time) {
        // Check if WhatsApp is enabled for cleaning
        $options = get_option('sp_notification_options', []);
        if (empty($options['cleaning_whatsapp_enabled'])) {
            return; // WhatsApp disabled
        }
        
        // Format message
        $message = sprintf(
            "🧹 Solar Cleaning Reminder!\n\nHi %s,\n\nYour solar panel cleaning is scheduled for tomorrow (%s) at %s.\n\nPlease ensure access to your solar panels.\n\nThank you for choosing our service!",
            $name,
            date('d M Y', strtotime($date)),
            $time
        );

        // Check if WhatsApp notifications are enabled
        $options = get_option('ksc_notification_options', []);
        if (!empty($options['whatsapp_enabled'])) {
            // Store WhatsApp message for sending (integration point)
            do_action('ksc_send_whatsapp_message', $phone, $message);
        }

        // Log the notification attempt
        error_log('[KSC Cleaning] Pre-service reminder sent to: ' . $phone);
    }

    /**
     * Send post-service review request
     */
    public function send_postservice_review_request($visit_id, $service_id) {
        // Check if post-service review is enabled
        $options = get_option('sp_notification_options', []);
        if (empty($options['cleaning_postservice_review'])) {
            return; // Disabled in settings
        }
        $customer_name = get_post_meta($service_id, '_customer_name', true);
        $customer_phone = get_post_meta($service_id, '_customer_phone', true);
        $customer_user_id = get_post_meta($service_id, '_customer_user_id', true);

        // If customer has a user account, create in-app notification
        if ($customer_user_id) {
            SP_Notifications_Manager::create_notification([
                'user_id' => $customer_user_id,
                'message' => '✅ Your solar cleaning has been completed! Please rate our service.',
                'type' => 'cleaning_review_request',
            ]);
        }

        // Send WhatsApp/SMS review request
        $this->send_review_request_message($customer_phone, $customer_name, $visit_id);

        // Mark as notified
        update_post_meta($visit_id, '_postservice_notification_sent', current_time('mysql'));
    }

    /**
     * Send review request message to customer
     */
    private function send_review_request_message($phone, $name, $visit_id) {
        $review_link = add_query_arg([
            'action' => 'review',
            'visit' => $visit_id,
            'token' => wp_hash($visit_id . $phone),
        ], home_url('/cleaning-review/'));

        $message = sprintf(
            "⭐ How was your solar cleaning?\n\nHi %s,\n\nWe've completed your solar panel cleaning today!\n\nPlease take a moment to rate our service:\n%s\n\nThank you for your feedback!",
            $name,
            $review_link
        );

        // Send via WhatsApp (integration point)
        do_action('ksc_send_whatsapp_message', $phone, $message);

        // Log the notification
        error_log('[KSC Cleaning] Review request sent to: ' . $phone);
    }

    /**
     * Trigger review request manually (called when visit is completed)
     */
    public function trigger_review_request() {
        $visit_id = intval($_POST['visit_id'] ?? 0);
        if (!$visit_id) {
            wp_send_json_error(['message' => 'Visit ID required']);
        }

        $service_id = get_post_meta($visit_id, '_service_id', true);
        if (!$service_id) {
            wp_send_json_error(['message' => 'Service not found']);
        }

        $this->send_postservice_review_request($visit_id, $service_id);

        wp_send_json_success(['message' => 'Review request sent']);
    }

    /**
     * Create notification when visit is scheduled
     */
    public static function notify_visit_scheduled($visit_id, $cleaner_id, $scheduled_date) {
        // Check if assignment notifications are enabled
        $options = get_option('sp_notification_options', []);
        if (empty($options['cleaning_assignment_notification'])) {
            return; // Disabled in settings
        }
        
        $service_id = get_post_meta($visit_id, '_service_id', true);
        $customer_name = get_post_meta($service_id, '_customer_name', true);
        $customer_address = get_post_meta($service_id, '_customer_address', true);

        // Notify cleaner about new assignment
        SP_Notifications_Manager::create_notification([
            'user_id' => $cleaner_id,
            'message' => sprintf(
                '🧹 New Cleaning Assigned: %s on %s\n📍 %s',
                $customer_name,
                date('d M Y', strtotime($scheduled_date)),
                $customer_address ?: 'Address TBD'
            ),
            'type' => 'cleaning_assignment',
        ]);
    }
}

// Initialize
new KSC_Cleaning_Notifications();

// Hook: Trigger review request when visit is completed
add_action('ksc_cleaning_visit_completed', function($visit_id, $service_id) {
    // Delay the review request by 2 hours
    wp_schedule_single_event(time() + (2 * HOUR_IN_SECONDS), 'ksc_send_review_request', [$visit_id, $service_id]);
}, 10, 2);

add_action('ksc_send_review_request', function($visit_id, $service_id) {
    $notifications = new KSC_Cleaning_Notifications();
    $notifications->send_postservice_review_request($visit_id, $service_id);
}, 10, 2);
