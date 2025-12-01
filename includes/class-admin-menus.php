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
        
        // Debug Panel (Admin Only)
        add_submenu_page(
            'tools.php',
            'Debug Panel',
            'Debug Panel',
            'manage_options',
            'ksc-debug-panel',
            [ $this, 'render_debug_panel' ]
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
     * Render debug panel page
     */
    public function render_debug_panel() {
        require_once plugin_dir_path(dirname(__FILE__)) . '../admin/views/view-debug-panel.php';
    }
}
