<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SP_Notifications_Manager {

    public function __construct() {
        add_action('wp_ajax_get_user_notifications', array($this, 'ajax_get_user_notifications'));
        add_action('wp_ajax_dismiss_notification', array($this, 'ajax_dismiss_notification'));

        // Hook into application actions
        add_action('sp_vendor_step_submitted', array($this, 'handle_vendor_step_submission'), 10, 2);
        add_action('sp_step_reviewed', array($this, 'handle_step_review'), 10, 3);
        add_action('sp_vendor_approved', array($this, 'handle_vendor_approval'), 10, 1);
    }

    public static function create_notification($args) {
        global $wpdb;
        $table = $wpdb->prefix . 'solar_notifications';

        $defaults = [
            'user_id' => 0,
            'project_id' => null,
            'message' => '',
            'type' => 'info',
            'status' => 'unread',
            'sent_email' => 0,      // ✅ NEW: Track if email was sent
            'sent_whatsapp' => 0,   // ✅ NEW: Track if WhatsApp was prepared
        ];
        $data = wp_parse_args($args, $defaults);

        if (empty($data['user_id']) || empty($data['message'])) {
            return false;
        }

        $wpdb->insert($table, [
            'user_id' => $data['user_id'],
            'project_id' => $data['project_id'],
            'message' => $data['message'],
            'type' => $data['type'],
            'status' => $data['status'],
            'sent_email' => $data['sent_email'],      // ✅ Store email flag
            'sent_whatsapp' => $data['sent_whatsapp'], // ✅ Store WhatsApp flag
            'created_at' => current_time('mysql'),
        ]);
        
        return $wpdb->insert_id; // ✅ Return notification ID for tracking
    }

    public function ajax_get_user_notifications() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in.']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'solar_notifications';
        $user_id = get_current_user_id();

        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND status = 'unread' ORDER BY created_at DESC LIMIT 20",
            $user_id
        ));

        wp_send_json_success($notifications);
    }

    public function ajax_dismiss_notification() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in.']);
        }

        $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
        if (empty($notification_id)) {
            wp_send_json_error(['message' => 'Invalid notification ID.']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'solar_notifications';
        $user_id = get_current_user_id();

        // Ensure the user owns the notification they are dismissing
        $result = $wpdb->update(
            $table,
            ['status' => 'dismissed'],
            ['id' => $notification_id, 'user_id' => $user_id],
            ['%s'],
            ['%d', '%d']
        );

        if ($result) {
            wp_send_json_success(['message' => 'Notification dismissed.']);
        } else {
            wp_send_json_error(['message' => 'Could not dismiss notification.']);
        }
    }

    public function handle_vendor_step_submission($step_id, $project_id) {
        $project = get_post($project_id);
        $vendor = get_user_by('id', $project->post_author);
        $message = sprintf(
            'Vendor %s submitted a new step for project %s.',
            $vendor->display_name,
            $project->post_title
        );

        // Notify admins and managers
        $admins = get_users(['role__in' => ['administrator', 'manager']]);
        foreach ($admins as $admin) {
            self::create_notification([
                'user_id' => $admin->ID,
                'project_id' => $project_id,
                'message' => $message,
                'type' => 'submission',
            ]);
        }

        // ✅ NOTIFY AREA MANAGER - Step Submitted
        $project_state = get_post_meta($project_id, '_project_state', true);
        $project_city = get_post_meta($project_id, '_project_city', true);

        if ($project_state && $project_city) {
            $area_managers = get_users([
                'role' => 'area_manager',
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => 'state',
                        'value' => $project_state,
                    ],
                    [
                        'key' => 'city',
                        'value' => $project_city,
                    ]
                ]
            ]);

            foreach ($area_managers as $area_manager) {
                self::create_notification([
                    'user_id' => $area_manager->ID,
                    'project_id' => $project_id,
                    'message' => $message,
                    'type' => 'vendor_submission',
                ]);
            }
        }
    }

    public function handle_step_review($step_id, $project_id, $decision) {
        $project = get_post($project_id);
        $step = get_post($step_id); // This is not correct, need to get step from db
        global $wpdb;
        $table = $wpdb->prefix . 'solar_process_steps';
        $step_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $step_id));


        if ($decision === 'approved') {
            $client_id = get_post_meta($project_id, '_client_user_id', true);
            if ($client_id) {
                $message = sprintf(
                    'A new step "%s" for your project "%s" has been approved.',
                    $step_data->step_name,
                    $project->post_title
                );
                self::create_notification([
                    'user_id' => $client_id,
                    'project_id' => $project_id,
                    'message' => $message,
                    'type' => 'step_approved',
                ]);
            }
        }
    }

    public function handle_vendor_approval($vendor_id) {
        $vendor = get_user_by('id', $vendor_id);
        $message = sprintf('Vendor %s has been approved.', $vendor->display_name);

        // Notify admins and managers
        $admins = get_users(['role__in' => ['administrator', 'manager']]);
        foreach ($admins as $admin) {
            self::create_notification([
                'user_id' => $admin->ID,
                'message' => $message,
                'type' => 'vendor_approved',
            ]);
        }
        
        // Send email to vendor (if enabled in settings)
        $notification_options = get_option('sp_notification_options');
        if (isset($notification_options['email_vendor_approved']) && $notification_options['email_vendor_approved'] === '1') {
            wp_mail(
                $vendor->user_email,
                'Your account has been approved!',
                'Congratulations, your vendor account has been approved. You can now log in and start bidding on projects.'
            );
        }
    }
}
