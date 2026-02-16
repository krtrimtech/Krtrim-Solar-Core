<?php
/**
 * Handles the creation of admin menus and settings pages.
 */
class SP_Admin_Menus {

    /**
     * Constructor. Hooks into WordPress.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        // Disabled - duplicate of action in class-admin-manager-api.php
        // add_action( 'wp_ajax_assign_area_manager_location', [ $this, 'ajax_assign_location' ] );
        
        // Manager Report AJAX Handlers
        add_action( 'wp_ajax_generate_manager_report', [ $this, 'ajax_generate_manager_report' ] );
        add_action( 'wp_ajax_email_manager_report', [ $this, 'ajax_email_manager_report' ] );
        add_action( 'wp_ajax_whatsapp_manager_report', [ $this, 'ajax_whatsapp_manager_report' ] );
        
        // Hide phone number field for Manager role in profile edit
        add_action( 'admin_head', [ $this, 'hide_manager_phone_field' ] );
    }

    /**
     * Register all admin menus and sub-menus.
     */
    public function register_menus() {
        // Main Vendor Approval Page
        add_menu_page(
            'Vendor Approval',
            'Vendor Approval',
            'manage_options',
            'vendor-approval',
            [ $this, 'render_vendor_approval_page' ],
            'dashicons-businessperson',
            25
        );

        // Team Analysis Page
        add_menu_page(
            'Team Analysis',
            'Team Analysis',
            'manage_options',
            'team-analysis',
            [ $this, 'render_team_analysis_page' ],
            'dashicons-chart-line',
            26
        );

        add_submenu_page(
            'team-analysis',
            'Leaderboard',
            'Leaderboard',
            'manage_options',
            'team-analysis',
            [ $this, 'render_team_analysis_page' ]
        );

        // Project Reviews Page
        add_menu_page(
            'Project Reviews',
            'Project Reviews',
            'edit_posts',
            'project-reviews',
            [ $this, 'render_project_reviews_page' ],
            'dashicons-visibility',
            27
        );

        // Consolidated Settings Page
        add_options_page(
            'Krtrim Solar Core Settings',
            'Krtrim Solar Core',
            'manage_options',
            'ksc-settings',
            [ $this, 'render_general_settings_page' ]
        );

        // Bid Management Page (New)
        add_submenu_page(
            'edit.php?post_type=solar_project',
            'Bid Management',
            'Bid Management',
            'manage_options',
            'bid-management',
            [ $this, 'render_bid_management_page' ]
        );

        // Process Step Template Page (New)
        add_submenu_page(
            'edit.php?post_type=solar_project',
            'Process Step Template',
            'Process Step Template',
            'manage_options',
            'process-step-template',
            [ $this, 'render_process_step_template_page' ]
        );
        

    }

    public function render_vendor_approval_page() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/view-vendor-approval.php';
        sp_render_vendor_approval_page();
    }

    public function render_team_analysis_page() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/view-team-analysis.php';
        sp_render_team_analysis_page();
    }

    public function render_project_reviews_page() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/view-project-reviews.php';
        sp_render_project_reviews_page();
    }

    public function render_general_settings_page() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/view-general-settings.php';
        sp_render_general_settings_page();
    }

    public function render_bid_management_page() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/view-bid-management.php';
        sp_render_bid_management_page();
    }

    public function render_process_step_template_page() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/view-process-step-template.php';
        sp_render_process_step_template_page();
    }
    

    
    /**
     * AJAX: Generate manager performance report
     */
    public function ajax_generate_manager_report() {
        check_ajax_referer('manager_report_nonce', 'nonce');
        
        $manager_id = intval($_POST['manager_id']);
        $manager = get_userdata($manager_id);
        
        if (!$manager || !in_array('area_manager', (array)$manager->roles)) {
            wp_send_json_error(['message' => 'Invalid manager']);
        }
        
        // Get manager stats (same logic as detail page)
        $projects = get_posts([
            'post_type' => 'solar_project',
            'author' => $manager_id,
            'posts_per_page' => -1,
            'post_status' => ['publish', 'completed', 'assigned', 'in_progress', 'pending']
        ]);
        
        $stats = ['completed' => 0, 'in_progress' => 0, 'pending' => 0, 'total' => count($projects)];
        $total_revenue = 0;
        $total_paid_to_vendors = 0;
        
        foreach ($projects as $project) {
            $status = get_post_meta($project->ID, 'project_status', true) ?: 'pending';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
            
            $total_revenue += floatval(get_post_meta($project->ID, '_total_project_cost', true) ?: 0);
            $total_paid_to_vendors += floatval(get_post_meta($project->ID, '_vendor_paid_amount', true) ?: 0);
        }
        
        $company_profit = $total_revenue - $total_paid_to_vendors;
        $location = get_user_meta($manager_id, 'city', true) . ', ' . get_user_meta($manager_id, 'state', true);
        
        // Generate report text
        $report = "═══════════════════════════════════════\n";
        $report .= "   AREA MANAGER PERFORMANCE REPORT\n";
        $report .= "═══════════════════════════════════════\n\n";
        $report .= "Manager: " . $manager->display_name . "\n";
        $report .= "Email: " . $manager->user_email . "\n";
        $report .= "Location: " . $location . "\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $report .= "───────────────────────────────────────\n";
        $report .= "PROJECT STATISTICS\n";
        $report .= "───────────────────────────────────────\n";
        $report .= "Total Projects: " . $stats['total'] . "\n";
        $report .= "Completed: " . $stats['completed'] . "\n";
        $report .= "In Progress: " . $stats['in_progress'] . "\n";
        $report .= "Pending: " . $stats['pending'] . "\n\n";
        $report .= "───────────────────────────────────────\n";
        $report .= "FINANCIAL SUMMARY\n";
        $report .= "───────────────────────────────────────\n";
        $report .= "Total Revenue: ₹" . number_format($total_revenue, 2) . "\n";
        $report .= "Paid to Vendors: ₹" . number_format($total_paid_to_vendors, 2) . "\n";
        $report .= "Company Profit: ₹" . number_format($company_profit, 2) . "\n\n";
        $report .= "═══════════════════════════════════════\n";
        
        $filename = 'manager_report_' . sanitize_file_name($manager->display_name) . '_' . date('Y-m-d') . '.txt';
        
        wp_send_json_success([
            'report_text' => $report,
            'filename' => $filename
        ]);
    }
    
    /**
     * AJAX: Email manager report
     */
    public function ajax_email_manager_report() {
        check_ajax_referer('manager_report_nonce', 'nonce');
        
        $manager_id = intval($_POST['manager_id']);
        $email = sanitize_email($_POST['email']);
        
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email address']);
        }
        
        $manager = get_userdata($manager_id);
        if (!$manager) {
            wp_send_json_error(['message' => 'Invalid manager']);
        }
        
        // Generate report (reuse same logic)
        $projects = get_posts([
            'post_type' => 'solar_project',
            'author' => $manager_id,
            'posts_per_page' => -1
        ]);
        
        $total = count($projects);
        $total_revenue = 0;
        foreach ($projects as $p) {
            $total_revenue += floatval(get_post_meta($p->ID, '_total_project_cost', true) ?: 0);
        }
        
        $subject = 'Performance Report - ' . $manager->display_name;
        $message = "Performance report for " . $manager->display_name . "\n\n";
        $message .= "Total Projects: " . $total . "\n";
        $message .= "Total Revenue: ₹" . number_format($total_revenue, 2) . "\n\n";
        $message .= "For detailed report, please login to the admin panel.";
        
        $sent = wp_mail($email, $subject, $message);
        
        if ($sent) {
            wp_send_json_success(['message' => 'Report sent to ' . $email]);
        } else {
            wp_send_json_error(['message' => 'Failed to send email']);
        }
    }
    
    /**
     * AJAX: Share manager report via WhatsApp
     */
    public function ajax_whatsapp_manager_report() {
        check_ajax_referer('manager_report_nonce', 'nonce');
        
        $manager_id = intval($_POST['manager_id']);
        $manager = get_userdata($manager_id);
        
        if (!$manager || !in_array('area_manager', (array)$manager->roles)) {
            wp_send_json_error(['message' => 'Invalid manager']);
        }
        
        // Get manager phone
        $phone = get_user_meta($manager_id, 'phone', true);
        
        if (empty($phone)) {
            wp_send_json_error(['message' => 'Manager phone number not found']);
        }
        
        // Get quick stats
        $projects = get_posts([
            'post_type' => 'solar_project',
            'author' => $manager_id,
            'posts_per_page' => -1
        ]);
        
        $total = count($projects);
        $total_revenue = 0;
        $total_vendors = 0;
        
        foreach ($projects as $p) {
            $total_revenue += floatval(get_post_meta($p->ID, '_total_project_cost', true) ?: 0);
            $total_vendors += floatval(get_post_meta($p->ID, '_vendor_paid_amount', true) ?: 0);
        }
        
        $profit = $total_revenue - $total_vendors;
        
        // Create WhatsApp message
        $message = "📊 *Performance Report*\n\n";
        $message .= "Manager: " . $manager->display_name . "\n";
        $message .= "───────────────\n";
        $message .= "📈 Total Projects: *" . $total . "*\n";
        $message .= "💰 Total Revenue: *₹" . number_format($total_revenue, 2) . "*\n";
        $message .= "💸 Paid to Vendors: *₹" . number_format($total_vendors, 2) . "*\n";
        $message .= "💵 Company Profit: *₹" . number_format($profit, 2) . "*\n\n";
        $message .= "Generated: " . date('d M Y, h:i A');
        
        $whatsapp_data = [
            'phone' => '91' . preg_replace('/\D/', '', $phone),
            'message' => urlencode($message)
        ];
        
        wp_send_json_success(['whatsapp_data' => $whatsapp_data]);
    }
    
    /**
     * AJAX: Assign location to area manager
     */
    public function ajax_assign_location() {
        check_ajax_referer('assign_location_nonce', 'nonce');
        
        $manager_id = intval($_POST['manager_id']);
        $state = sanitize_text_field($_POST['state']);
        $city = sanitize_text_field($_POST['city']);
        
        if (!$manager_id || !$state || !$city) {
            wp_send_json_error(['message' => 'Invalid data']);
        }
        
        update_user_meta($manager_id, 'state', $state);
        update_user_meta($manager_id, 'city', $city);
        
        wp_send_json_success(['message' => 'Location assigned successfully']);
    }
    
    /**
     * Hide phone number field for Manager role in user profile edit page
     */
    public function hide_manager_phone_field() {
        $screen = get_current_screen();
        
        // Only on user edit or profile pages
        if ( ! $screen || ( $screen->id !== 'user-edit' && $screen->id !== 'profile' ) ) {
            return;
        }
        
        // Get the user being edited
        $user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : get_current_user_id();
        $user = get_userdata( $user_id );
        
        // Check if user has manager role
        if ( $user && in_array( 'manager', (array) $user->roles ) ) {
            ?>
            <style>
                /* Hide phone number field for Manager role */
                .user-phone-wrap,
                tr.user-phone-wrap,
                .form-field.user-phone-wrap {
                    display: none !important;
                }
            </style>
            <?php
        }
    }

}
