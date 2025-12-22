<?php
/**
 * Project Notifications Manager
 * 
 * Handles automated WhatsApp notifications for project workflows:
 * - Vendor Step Submission -> Admin/Manager
 * - Bid Submission -> Admin/Manager
 * - Step Reviewed (Approved/Rejected) -> Vendor & Client
 * - Project Awarded -> Vendor
 * 
 * @package Krtrim_Solar_Core
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class KSC_Project_Notifications {

    public function __construct() {
        // Vendor Events
        add_action('sp_vendor_step_submitted', [$this, 'notify_step_submitted'], 10, 2);
        add_action('sp_bid_submitted', [$this, 'notify_bid_submitted'], 10, 4);
        
        // Admin Decision Events
        add_action('sp_step_reviewed', [$this, 'notify_step_reviewed'], 10, 3);
        add_action('sp_project_awarded', [$this, 'notify_project_awarded'], 10, 2);
    }

    /** ---------------------------------------------------------------------------
     *  INCOMING TO ADMIN / MANAGER
     *  --------------------------------------------------------------------------- */

    /**
     * Notify Admin & Area Manager when a vendor submits a step
     */
    public function notify_step_submitted($step_id, $project_id) {
        $project_title = get_the_title($project_id);
        $step_number = $this->get_step_number($step_id);
        $vendor_id = get_post_meta($project_id, '_assigned_vendor_id', true);
        $vendor_name = $this->get_display_name($vendor_id);
        
        $message = sprintf(
            "📝 *Step Submitted*\n\nProject: %s\nStep #%d\nVendor: %s\n\nPlease review in Admin Panel.",
            $project_title,
            $step_number,
            $vendor_name
        );

        // Notify Admin
        $this->notify_admins($message);

        // Notify Area Manager (Project Author)
        $project = get_post($project_id);
        if ($project) {
            $this->send_whatsapp_to_user($project->post_author, $message);
        }
    }

    /**
     * Notify Admin & Area Manager when a bid is submitted
     */
    public function notify_bid_submitted($bid_id, $project_id, $vendor_id, $amount) {
        $project_title = get_the_title($project_id);
        $vendor_name = $this->get_display_name($vendor_id);
        
        $message = sprintf(
            "🔨 *New Bid Received*\n\nProject: %s\nVendor: %s\nAmount: ₹%s\n\nCheck 'Bid Management' to review.",
            $project_title,
            $vendor_name,
            number_format($amount, 2)
        );

        // Notify Admin
        $this->notify_admins($message);

        // Notify Area Manager
        $project = get_post($project_id);
        if ($project) {
            $this->send_whatsapp_to_user($project->post_author, $message);
        }
    }


    /** ---------------------------------------------------------------------------
     *  OUTGOING TO VENDOR / CLIENT
     *  --------------------------------------------------------------------------- */

    /**
     * Notify Vendor & Client when a step is reviewed
     */
    public function notify_step_reviewed($step_id, $project_id, $decision) {
        $project_title = get_the_title($project_id);
        $step_number = $this->get_step_number($step_id);
        $vendor_id = get_post_meta($project_id, '_assigned_vendor_id', true);
        $client_id = get_post_meta($project_id, '_client_user_id', true);
        
        global $wpdb;
        $admin_comment = $wpdb->get_var($wpdb->prepare("SELECT admin_comment FROM {$wpdb->prefix}solar_process_steps WHERE id = %d", $step_id));

        // 1. Notify Vendor
        if ($decision === 'approved') {
            $vendor_msg = sprintf(
                "✅ *Step Approved!*\n\nProject: %s\nStep #%d\n\nGreat work! You can proceed to the next step.",
                $project_title,
                $step_number
            );
        } else {
            $vendor_msg = sprintf(
                "❌ *Step Rejected*\n\nProject: %s\nStep #%d\n\nReason: %s\n\nPlease check the app to resubmit.",
                $project_title,
                $step_number,
                $admin_comment ?: 'Requirements not met'
            );
        }
        $this->send_whatsapp_to_user($vendor_id, $vendor_msg);

        // 2. Notify Client (Only on approval, usually)
        if ($decision === 'approved' && $client_id) {
            $client_msg = sprintf(
                "🚀 *Project Update*\n\nStep #%d for your project '%s' has been completed and approved.",
                $step_number,
                $project_title
            );
            $this->send_whatsapp_to_user($client_id, $client_msg);
        }
    }

    /**
     * Notify Vendor when they win a project
     */
    public function notify_project_awarded($project_id, $vendor_id) {
        $project_title = get_the_title($project_id);
        $amount = get_post_meta($project_id, 'winning_bid_amount', true);

        $message = sprintf(
            "🎉 *Congratulations! You Won!*\n\nProject: %s\nBid Amount: ₹%s\n\nPlease check your dashboard to start the process.",
            $project_title,
            number_format($amount, 2)
        );

        $this->send_whatsapp_to_user($vendor_id, $message);
    }


    /** ---------------------------------------------------------------------------
     *  HELPERS
     *  --------------------------------------------------------------------------- */

    private function notify_admins($message) {
        $admins = get_users(['role' => 'administrator']);
        foreach ($admins as $admin) {
            $this->send_whatsapp_to_user($admin->ID, $message);
        }
    }

    private function send_whatsapp_to_user($user_id, $message) {
        if (!$user_id) return;
        
        $phone = get_user_meta($user_id, 'phone_number', true) ?: get_user_meta($user_id, 'phone', true);
        
        // Sanitize phone
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (empty($phone) || strlen($phone) < 10) return;

        // If WAHA integration hook exists
        do_action('ksc_send_whatsapp_message', $phone, $message);
    }

    private function get_step_number($step_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT step_number FROM {$wpdb->prefix}solar_process_steps WHERE id = %d", $step_id));
    }

    private function get_display_name($user_id) {
        $user = get_userdata($user_id);
        return $user ? $user->display_name : 'User';
    }
}

// Initialize
new KSC_Project_Notifications();
