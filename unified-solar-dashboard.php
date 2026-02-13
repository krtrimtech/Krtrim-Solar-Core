<?php
/**
 * Plugin Name:       Krtrim Solar Core
 * Plugin URI:        https://krtrim.tech/wordpress
 * Description:       A comprehensive project management and bidding platform for solar companies, developed by Krtrim.
 * Version:           1.3.0
 * Author:            Krtrim
 * Author URI:        https://krtrim.tech
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       krtrim-solar-core
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class.
 */
final class Krtrim_Solar_Core {

	private static $instance = null;
	public $version = '1.3.2';
	public $file = __FILE__;
	public $dir_path;
	public $dir_url;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->define_constants();
		$this->load_dependencies();
		$this->init_hooks();
	}

	private function define_constants() {
		$this->dir_path = plugin_dir_path( $this->file );
		$this->dir_url  = plugin_dir_url( $this->file );
	}

	private function load_dependencies() {
		require_once $this->dir_path . 'includes/class-post-types-taxonomies.php';
		require_once $this->dir_path . 'includes/class-admin-menus.php';
		require_once $this->dir_path . 'includes/class-api-handlers.php';
		require_once $this->dir_path . 'includes/ajax-get-project-details.php';
		require_once $this->dir_path . 'includes/class-admin-widgets.php';
		require_once $this->dir_path . 'includes/class-razorpay-light-client.php';
		require_once $this->dir_path . 'includes/class-custom-metaboxes.php';
		require_once $this->dir_path . 'includes/class-user-profile-fields.php';
		require_once $this->dir_path . 'includes/class-process-steps-manager.php';
		require_once $this->dir_path . 'includes/class-notifications-manager.php';
		require_once $this->dir_path . 'includes/class-error-logger.php';
        
        // Components
        require_once $this->dir_path . 'includes/components/class-lead-manager-component.php';

		require_once $this->dir_path . 'includes/post-types/class-cleaning-cpts.php';
        require_once $this->dir_path . 'includes/post-types/class-coupon-cpt.php';
		require_once $this->dir_path . 'includes/api/class-cleaner-api.php';
		require_once $this->dir_path . 'includes/api/class-cleaning-services-api.php';
		require_once $this->dir_path . 'includes/class-cleaning-notifications.php';
		require_once $this->dir_path . 'includes/integrations/class-whatsapp-handler.php';
		require_once $this->dir_path . 'includes/integrations/class-official-whatsapp.php';
		require_once $this->dir_path . 'includes/integrations/class-cf7-cleaning-integration.php';
		require_once $this->dir_path . 'includes/class-project-notifications.php';
		
		// Admin view files (required by SP_Admin_Menus class)
		require_once $this->dir_path . 'admin/views/view-vendor-approval.php';
		require_once $this->dir_path . 'admin/views/view-project-reviews.php';
		require_once $this->dir_path . 'admin/views/view-bid-management.php';
		require_once $this->dir_path . 'admin/views/view-general-settings.php';
		require_once $this->dir_path . 'admin/views/view-team-analysis.php';
		require_once $this->dir_path . 'admin/views/view-process-step-template.php';
		
		// Public view files (for shortcodes)
		require_once $this->dir_path . 'public/views/view-client-dashboard.php';
		require_once $this->dir_path . 'public/views/view-vendor-dashboard.php';
		require_once $this->dir_path . 'public/views/view-area-manager-dashboard.php';
		require_once $this->dir_path . 'public/views/view-manager-dashboard.php';
		require_once $this->dir_path . 'public/views/view-sales-manager-dashboard.php';
		require_once $this->dir_path . 'public/views/view-marketplace.php';
		require_once $this->dir_path . 'public/views/view-vendor-registration.php';
		require_once $this->dir_path . 'public/views/view-vendor-status.php';
		require_once $this->dir_path . 'public/views/view-cleaner-dashboard.php';
		require_once $this->dir_path . 'public/views/view-cleaning-booking.php';
	}

	private function init_hooks() {
		new SP_Post_Types_Taxonomies();
		new SP_Admin_Menus();
		new SP_Admin_Widgets();
		new SP_User_Profile_Fields();
		new SP_Process_Steps_Manager();
		new SP_Notifications_Manager();
		new SP_API_Handlers(); // Must be AFTER Process Steps and Notifications managers

		register_activation_hook( $this->file, [ $this, 'activate' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		add_shortcode( 'unified_solar_dashboard', [ $this, 'shortcode_unified_dashboard' ] );
		add_shortcode( 'area_manager_dashboard', 'sp_area_manager_dashboard_shortcode' );
		add_shortcode( 'manager_dashboard', 'sp_manager_dashboard_shortcode' );
		add_shortcode( 'sales_manager_dashboard', 'sp_sales_manager_dashboard_shortcode' );
		add_shortcode( 'vendor_registration_form', 'sp_vendor_registration_form_shortcode' );
		add_shortcode( 'solar_project_marketplace', 'sp_project_marketplace_shortcode' );
		add_shortcode( 'vendor_status_dashboard', 'sp_vendor_status_dashboard_shortcode' );
		add_shortcode( 'cleaner_dashboard', 'ksc_cleaner_dashboard_shortcode' );
		add_shortcode( 'solar_cleaning_booking', 'ksc_render_cleaning_booking_form' );
		
		add_filter( 'template_include', [ $this, 'template_include_single_project' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ), [ $this, 'add_plugin_action_links' ] );

        // Authentication & Access Control for All Roles
        add_filter( 'login_redirect', [ $this, 'custom_login_redirect' ], 10, 3 );
        add_action( 'admin_init', [ $this, 'restrict_wp_admin_access' ] );
        add_filter( 'logout_redirect', [ $this, 'custom_logout_redirect' ] );
        
        // Note: Default featured image filters removed - methods were not implemented
        // add_filter('post_thumbnail_html', [$this, 'filter_default_project_image'], 10, 5);
        // add_filter('post_thumbnail_url', [$this, 'filter_default_project_image_url'], 10, 2);
        
		
		// Cascade delete: Clean up related data when project is deleted
		add_action('before_delete_post', [$this, 'cleanup_project_data'], 10, 2);
	}

    /**
     * Redirect users to appropriate dashboard after login based on role
     */
    public function custom_login_redirect( $redirect_to, $request, $user ) {
        if ( isset( $user->roles ) && is_array( $user->roles ) ) {
            // Sales Manager
            if ( in_array( 'sales_manager', $user->roles ) ) {
                return home_url( '/sales-manager-dashboard/' );
            }
            // Area Manager
            if ( in_array( 'area_manager', $user->roles ) ) {
                return home_url( '/area-manager-dashboard/' );
            }
            // Solar Client
            if ( in_array( 'solar_client', $user->roles ) ) {
                return home_url( '/solar-dashboard/' );
            }
            // Solar Vendor
            if ( in_array( 'solar_vendor', $user->roles ) ) {
                // Check if vendor is approved
                $account_approved = get_user_meta( $user->ID, 'account_approved', true );
                if ( $account_approved === 'yes' ) {
                    return home_url( '/solar-dashboard/' );
                } else {
                    // Send to status page if not approved
                    return home_url( '/vendor-status/' );
                }
            }
            // Manager (regional manager with multi-state access)
            if ( in_array( 'manager', $user->roles ) ) {
                return home_url( '/manager-dashboard/' );
            }
        }
        return $redirect_to; // Default for administrators
    }

    /**
     * Restrict WordPress admin access for non-admin roles
     * Redirects clients, vendors, and area managers to their dashboards
     * Administrators and Managers have full wp-admin access
     */
    public function restrict_wp_admin_access() {
        // Allow AJAX requests and WordPress heartbeat
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        $user = wp_get_current_user();
        $restricted_roles = [ 'solar_client', 'solar_vendor', 'area_manager', 'sales_manager' ];
        
        // Check if user has any restricted role
        foreach ( $restricted_roles as $role ) {
            if ( in_array( $role, (array) $user->roles ) ) {
                // Define redirect mapping for each role
                $redirect_map = [
                    'sales_manager'  => '/sales-manager-dashboard/',
                    'area_manager'   => '/area-manager-dashboard/',
                    'solar_client'   => '/solar-dashboard/',
                    'solar_vendor'   => '/solar-dashboard/',
                ];
                
                $redirect_url = isset( $redirect_map[$role] ) 
                    ? home_url( $redirect_map[$role] ) 
                    : home_url( '/solar-dashboard/' );
                    
                wp_redirect( $redirect_url );
                exit;
            }
        }
    }

    /**
     * Redirect to login page after logout
     */
    public function custom_logout_redirect() {
        return wp_login_url();
    }

    /**
     * Cascade delete: Clean up related data when a solar project is deleted
     * Removes orphaned records from custom tables
     */
    public function cleanup_project_data($post_id, $post) {
        // Only proceed if this is a solar_project post type
        if (!$post || $post->post_type !== 'solar_project') {
            return;
        }

        global $wpdb;

        // Delete all bids for this project
        $bids_table = $wpdb->prefix . 'project_bids';
        $deleted_bids = $wpdb->delete($bids_table, ['project_id' => $post_id], ['%d']);
        
        // Delete all process steps for this project
        $steps_table = $wpdb->prefix . 'solar_process_steps';
        $deleted_steps = $wpdb->delete($steps_table, ['project_id' => $post_id], ['%d']);
        
        // Delete all notifications for this project
        $notifications_table = $wpdb->prefix . 'solar_notifications';
        $deleted_notifications = $wpdb->delete($notifications_table, ['project_id' => $post_id], ['%d']);

        // Log cleanup for debugging (optional)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Solar Project #%d deleted. Cleaned up: %d bids, %d steps, %d notifications',
                $post_id,
                $deleted_bids === false ? 0 : $deleted_bids,
                $deleted_steps === false ? 0 : $deleted_steps,
                $deleted_notifications === false ? 0 : $deleted_notifications
            ));
        }
    }


    /**
     * Provide default featured image HTML for solar projects when no featured image is set
     * Works everywhere: Elementor loops, archives, standard templates
     */
    public function default_project_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
        if ( get_post_type( $post_id ) === 'solar_project' && empty( $html ) ) {
            $default_image = get_option( 'ksc_default_project_image', '' );
            $default_url = $default_image ?: 'https://via.placeholder.com/400x250/667eea/ffffff?text=Solar+Project';
            
            $attr_string = '';
            if ( is_array( $attr ) ) {
                foreach ( $attr as $name => $value ) {
                    $attr_string .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
                }
            }
            
            $html = sprintf(
                '<img src="%s" alt="%s" class="attachment-%s size-%s wp-post-image"%s>',
                esc_url( $default_url ),
                esc_attr( get_the_title( $post_id ) ),
                esc_attr( $size ),
                esc_attr( $size ),
                $attr_string
            );
        }
        return $html;
    }

    /**
     * Provide default featured image URL for solar projects when no featured image is set
     * Works everywhere: Elementor loops, get_the_post_thumbnail_url(), etc.
     */
    public function default_project_thumbnail_url( $url, $post_id, $size ) {
        if ( get_post_type( $post_id ) === 'solar_project' && empty( $url ) ) {
            $default_image = get_option( 'ksc_default_project_image', '' );
            $url = $default_image ?: 'https://via.placeholder.com/400x250/667eea/ffffff?text=Solar+Project';
        }
        return $url;
    }

	public function add_plugin_action_links( $links ) {
		$settings_link = '<a href="options-general.php?page=ksc-settings">Settings</a>';
		$links[] = $settings_link;
		return $links;
	}

	public function activate() {
		sp_create_plugin_essentials();
		
		// Create error log table
		require_once $this->dir_path . 'includes/class-error-logger.php';
		KSC_Error_Logger::create_table();
		
		flush_rewrite_rules();
	}

	public function enqueue_public_scripts() {
		wp_enqueue_style( 'ksc-public-styles', $this->dir_url . 'assets/css/dashboard.css', [], $this->version );
		wp_enqueue_style( 'ksc-public-styles', $this->dir_url . 'assets/css/dashboard.css', [], $this->version );
		
        if ( is_page( 'solar-dashboard' ) ) {
            wp_enqueue_script( 'ksc-public-scripts', $this->dir_url . 'assets/js/dashboard.js', [ 'jquery' ], $this->version, true );
            wp_localize_script( 'ksc-public-scripts', 'ksc_dashboard_vars', [
                'rest_api_nonce' => wp_create_nonce( 'wp_rest' ),
                'client_api_url' => rest_url( 'solar/v1/client-notifications' ),
                'vendor_api_url' => rest_url( 'solar/v1/vendor-notifications' ),
                'admin_ajax_url' => admin_url( 'admin-ajax.php' ),
                'get_earnings_chart_data_nonce' => wp_create_nonce( 'get_earnings_chart_data_nonce' ),
                'client_comments_url' => rest_url( 'solar/v1/client-comments' ),
                'vendor_notifications_url' => rest_url( 'solar/v1/vendor-notifications/' ),
                'vendor_coverage_nonce' => wp_create_nonce( 'vendor_registration_nonce' ),  // For vendor coverage payment
            ]);
        }

		if ( is_page( 'solar-dashboard' ) && in_array( 'solar_client', (array) wp_get_current_user()->roles ) ) {
			$client_id = get_current_user_id();
			$args = array(
				'post_type' => 'solar_project',
				'posts_per_page' => 1,
				'post_status' => 'publish',
				'meta_query' => array(
					array(
						'key' => 'client_user_id',
						'value' => $client_id,
					)
				)
			);
			$project_query = new WP_Query($args);
			if ($project_query->have_posts()) {
				$project_query->the_post();
				$project_id = get_the_ID();
				$total_project_cost = get_post_meta($project_id, '_total_project_cost', true);
				$paid_amount = get_post_meta($project_id, '_paid_amount', true);
				$balance = $total_project_cost - $paid_amount;
				wp_localize_script( 'ksc-public-scripts', 'payment_data', [
					'total' => $total_project_cost,
					'paid' => $paid_amount,
					'balance' => $balance,
				]);
			}
			wp_reset_postdata();
		}

		global $post;
		if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'area_manager_dashboard' ) || is_page( 'area-manager-dashboard' ) ) ) {
            wp_enqueue_style('area-manager-modern', $this->dir_url . 'assets/css/area-manager-modern.css', [], '1.0.0');
            wp_enqueue_style('lead-component-css', $this->dir_url . 'assets/css/components/lead-component.css', [], '1.0.1');
            wp_enqueue_style('leads-clients-enhanced', $this->dir_url . 'assets/css/leads-clients-enhanced.css', [], '1.0.0');
            wp_enqueue_style('project-modal', $this->dir_url . 'assets/css/project-modal.css', [], '1.0.0');
            wp_enqueue_style('date-picker-enhanced', $this->dir_url . 'assets/css/date-picker-enhanced.css', [], '1.0.0');
            wp_enqueue_style('toast-css', $this->dir_url . 'assets/css/toast.css', [], '1.0.0');
            wp_enqueue_style('password-field-css', $this->dir_url . 'assets/css/password-field.css', [], '1.0.0');
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.7.0', true );
            wp_enqueue_script('ksc-dashboard-utils', $this->dir_url . 'assets/js/components/dashboard-utils.js', ['jquery'], $this->version, true);
            wp_enqueue_script('ksc-project-modal-component', $this->dir_url . 'assets/js/components/project-modal-component.js', ['jquery', 'ksc-dashboard-utils'], $this->version, true);
            wp_enqueue_script('project-modal-js', $this->dir_url . 'assets/js/project-modal.js', ['jquery'], '1.0.0', true);
            wp_enqueue_script('lead-component-js', $this->dir_url . 'assets/js/components/lead-component.js', ['jquery', 'ksc-dashboard-utils'], '1.0.6', true);
            wp_enqueue_style('cleaner-component-css', $this->dir_url . 'assets/css/components/cleaner-component.css', [], '1.0.2');
            wp_enqueue_script('cleaner-component-js', $this->dir_url . 'assets/js/components/cleaner-component.js', ['jquery', 'ksc-dashboard-utils'], '1.0.1', true);
            wp_enqueue_script('ksc-team-analysis-component', $this->dir_url . 'assets/js/components/team-analysis-component.js', ['jquery'], $this->version, true);
			wp_enqueue_script( 'area-manager-dashboard-js', $this->dir_url . 'assets/js/area-manager-dashboard.js', [ 'jquery', 'chart-js', 'lead-component-js', 'cleaner-component-js', 'ksc-dashboard-utils', 'ksc-project-modal-component', 'ksc-team-analysis-component' ], '1.0.9', true );
			wp_localize_script('area-manager-dashboard-js', 'sp_area_dashboard_vars', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'create_project_nonce' => wp_create_nonce('sp_create_project_nonce_field'),
				'update_project_nonce' => wp_create_nonce('sp_update_project_nonce'),
				'project_details_nonce' => wp_create_nonce('sp_project_details_nonce'),
				'review_submission_nonce' => wp_create_nonce('sp_review_nonce'),
				'award_bid_nonce' => wp_create_nonce('award_bid_nonce'),
				'get_dashboard_stats_nonce' => wp_create_nonce('get_dashboard_stats_nonce'),
				'get_projects_nonce' => wp_create_nonce('get_projects_nonce'),
				'get_reviews_nonce' => wp_create_nonce('get_reviews_nonce'),
				'get_vendor_approvals_nonce' => wp_create_nonce('get_vendor_approvals_nonce'),
				'create_client_nonce' => wp_create_nonce('create_client_nonce'),
                'get_leads_nonce' => wp_create_nonce('get_leads_nonce'),
                'create_lead_nonce' => wp_create_nonce('create_lead_nonce'),
                'delete_lead_nonce' => wp_create_nonce('delete_lead_nonce'),
                'send_message_nonce' => wp_create_nonce('send_message_nonce'),
                'get_clients_nonce' => wp_create_nonce('get_clients_nonce'),
                'get_clients_nonce' => wp_create_nonce('get_clients_nonce'),
                'reset_password_nonce' => wp_create_nonce('reset_password_nonce'),
                'cleaner_nonce' => wp_create_nonce('ksc_cleaner_nonce'),
				'states_cities_json_url' => $this->dir_url . 'assets/data/indian-states-cities.json',
			]);
		}

		// ✅ MANAGER DASHBOARD (Regional Manager with multi-state access)
		global $post;
		$is_manager_dash = is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'manager_dashboard' ) || is_page( 'manager-dashboard' ) );
		
        if ( $is_manager_dash ) {
			wp_enqueue_style('area-manager-modern', $this->dir_url . 'assets/css/area-manager-modern.css', [], '1.0.0');
            wp_enqueue_style('cleaner-component-css', $this->dir_url . 'assets/css/components/cleaner-component.css', [], '1.0.2');
			wp_enqueue_style('lead-component-css', $this->dir_url . 'assets/css/components/lead-component.css', [], '1.0.1');
            wp_enqueue_style('leads-clients-enhanced', $this->dir_url . 'assets/css/leads-clients-enhanced.css', [], '1.0.0');
            wp_enqueue_style('project-modal', $this->dir_url . 'assets/css/project-modal.css', [], '1.0.0');
            wp_enqueue_style('date-picker-enhanced', $this->dir_url . 'assets/css/date-picker-enhanced.css', [], '1.0.0');
            wp_enqueue_style('toast-css', $this->dir_url . 'assets/css/toast.css', [], '1.0.0');
            wp_enqueue_style('password-field-css', $this->dir_url . 'assets/css/password-field.css', [], '1.0.0');
            wp_enqueue_style('manager-dashboard-overrides', $this->dir_url . 'assets/css/manager-dashboard-overrides.css', [], '1.0.0');
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.7.0', true );
            wp_enqueue_script('ksc-dashboard-utils', $this->dir_url . 'assets/js/components/dashboard-utils.js', ['jquery'], $this->version, true);
            wp_enqueue_script('ksc-project-modal-component', $this->dir_url . 'assets/js/components/project-modal-component.js', ['jquery', 'ksc-dashboard-utils'], $this->version, true);
            wp_enqueue_script('project-modal-js', $this->dir_url . 'assets/js/project-modal.js', ['jquery'], '1.0.0', true);
            wp_enqueue_script('lead-component-js', $this->dir_url . 'assets/js/components/lead-component.js', ['jquery', 'ksc-dashboard-utils'], '1.0.6', true);
            wp_enqueue_script('cleaner-component-js', $this->dir_url . 'assets/js/components/cleaner-component.js', ['jquery', 'ksc-dashboard-utils'], '1.0.0', true);
            wp_enqueue_script('ksc-team-analysis-component', $this->dir_url . 'assets/js/components/team-analysis-component.js', ['jquery'], $this->version, true);
			wp_enqueue_script( 'manager-dashboard-js', $this->dir_url . 'assets/js/manager-dashboard.js', [ 'jquery', 'chart-js', 'lead-component-js', 'cleaner-component-js', 'ksc-dashboard-utils', 'ksc-project-modal-component', 'ksc-team-analysis-component' ], '1.0.4', true );
			wp_localize_script('manager-dashboard-js', 'sp_area_dashboard_vars', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'create_project_nonce' => wp_create_nonce('sp_create_project_nonce_field'),
				'update_project_nonce' => wp_create_nonce('sp_update_project_nonce'),
				'project_details_nonce' => wp_create_nonce('sp_project_details_nonce'),
				'review_submission_nonce' => wp_create_nonce('sp_review_nonce'),
				'award_bid_nonce' => wp_create_nonce('award_bid_nonce'),
				'get_dashboard_stats_nonce' => wp_create_nonce('get_dashboard_stats_nonce'),
				'get_projects_nonce' => wp_create_nonce('get_projects_nonce'),
				'get_reviews_nonce' => wp_create_nonce('get_reviews_nonce'),
				'get_vendor_approvals_nonce' => wp_create_nonce('get_vendor_approvals_nonce'),
				'create_client_nonce' => wp_create_nonce('create_client_nonce'),
                'get_leads_nonce' => wp_create_nonce('get_leads_nonce'),
                'create_lead_nonce' => wp_create_nonce('create_lead_nonce'),
                'delete_lead_nonce' => wp_create_nonce('delete_lead_nonce'),
                'send_message_nonce' => wp_create_nonce('send_message_nonce'),
                'get_clients_nonce' => wp_create_nonce('get_clients_nonce'),
                'get_clients_nonce' => wp_create_nonce('get_clients_nonce'),
                'reset_password_nonce' => wp_create_nonce('reset_password_nonce'),
                'cleaner_nonce' => wp_create_nonce('ksc_cleaner_nonce'),
				'states_cities_json_url' => $this->dir_url . 'assets/data/indian-states-cities.json',
			]);
		}

		// ✅ SALES MANAGER DASHBOARD
		global $post;
		if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'sales_manager_dashboard' ) || is_page( 'sales-manager-dashboard' ) ) ) {
			wp_enqueue_style('area-manager-modern', $this->dir_url . 'assets/css/area-manager-modern.css', [], '1.0.0');
			wp_enqueue_style('lead-component-css', $this->dir_url . 'assets/css/components/lead-component.css', [], '1.0.1');
			wp_enqueue_style('sales-manager-dashboard-css', $this->dir_url . 'assets/css/sales-manager-dashboard.css', ['area-manager-modern', 'lead-component-css'], '1.0.1');
			wp_enqueue_style('toast-css', $this->dir_url . 'assets/css/toast.css', [], '1.0.0');
			wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.7.0', true);
            wp_enqueue_script('ksc-dashboard-utils', $this->dir_url . 'assets/js/components/dashboard-utils.js', ['jquery'], $this->version, true);
			wp_enqueue_script('lead-component-js', $this->dir_url . 'assets/js/components/lead-component.js', ['jquery', 'ksc-dashboard-utils'], '1.0.6', true);
			wp_enqueue_script('sales-manager-dashboard-js', $this->dir_url . 'assets/js/sales-manager-dashboard.js', ['jquery', 'chart-js', 'lead-component-js', 'ksc-dashboard-utils'], '1.0.2', true);
			wp_localize_script('sales-manager-dashboard-js', 'sm_vars', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('sp_sales_manager_nonce'),
			]);
		}

		// ✅ VENDOR REGISTRATION PAGE
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'vendor_registration_form' ) ) {
			// Enqueue styles
			wp_enqueue_style('vendor-registration-css', $this->dir_url . 'assets/css/vendor-registration.css', [], '1.0.0');
			
			// Enqueue Razorpay
			wp_enqueue_script('razorpay-checkout', 'https://checkout.razorpay.com/v1/checkout.js', [], null, true);
			
			// Enqueue custom vendor registration script
			wp_enqueue_script(
				'vendor-registration-js',
				$this->dir_url . 'assets/js/vendor-registration.js',
				['jquery'],
				'1.0.0',
				true
			);

			// Get vendor options and determine correct Razorpay key
			$options = get_option('sp_vendor_options');
			$mode = isset($options['razorpay_mode']) ? $options['razorpay_mode'] : 'test';
			
			// Select correct key based on mode
			if ($mode === 'live') {
				$razorpay_key = isset($options['razorpay_live_key_id']) ? $options['razorpay_live_key_id'] : '';
			} else {
				$razorpay_key = isset($options['razorpay_test_key_id']) ? $options['razorpay_test_key_id'] : '';
			}

			// Localize script with config
			wp_localize_script('vendor-registration-js', 'vendor_reg_vars', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'razorpay_key_id' => $razorpay_key,
				'per_state_fee' => isset($options['per_state_fee']) ? $options['per_state_fee'] : 500,
				'per_city_fee' => isset($options['per_city_fee']) ? $options['per_city_fee'] : 100,
				'nonce' => wp_create_nonce('vendor_registration_nonce'),
			]);
		}

		// Load marketplace JS only on marketplace pages
		global $post;
		if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'solar_project_marketplace' ) || is_page( 'project-marketplace' ) ) ) {
			wp_enqueue_script('marketplace-js', $this->dir_url . 'assets/js/marketplace.js', ['jquery'], $this->version, true);
			
			$json_file = $this->dir_path . 'assets/data/indian-states-cities.json';
			if (file_exists($json_file)) {
				$states_cities = json_decode(file_get_contents($json_file), true);
				
				wp_localize_script('marketplace-js', 'marketplace_vars', [
					'ajax_url' => admin_url('admin-ajax.php'),
					'nonce' => wp_create_nonce('filter_projects_nonce'),
					'states_cities' => isset($states_cities['states']) ? $states_cities['states'] : [],
				]);
			}
		}


		// Load bid submission JS on single project pages
		if (is_singular('solar_project')) {
			wp_enqueue_script('project-bid-js', $this->dir_url . 'assets/js/project-bid.js', ['jquery'], $this->version, true);
			wp_localize_script('project-bid-js', 'project_bid_vars', [
				'ajax_url' => admin_url('admin-ajax.php'),
			]);
		}

		// Load client dashboard enhancements
		global $post;
		if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'unified_solar_dashboard')) {
			// Enqueue Chart.js from CDN
			wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', [], '3.9.1', true);
			
			// Enqueue client dashboard CSS
			wp_enqueue_style('client-dashboard-enhancements', $this->dir_url . 'assets/css/client-dashboard-enhancements.css', [], $this->version);
		}
	}

	public function enqueue_admin_scripts( $hook ) {
		// Enqueue admin styles
		wp_enqueue_style( 'sp-admin-styles', $this->dir_url . 'assets/css/admin-styles.css', [], $this->version );

		// Enqueue jQuery UI sortable for Process Step Template page
		if ( isset($_GET['page']) && $_GET['page'] === 'process-step-template' ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}

		// Enqueue Chart.js for Team Analysis page
		if ( $hook === 'toplevel_page_team-analysis' ) {
			wp_enqueue_script(
				'chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
				[],
				'3.9.1',
				true
			);
		}

		// Enqueue admin scripts for bid management
		if ( $hook === 'solar_project_page_bid-management' ) {
			wp_enqueue_script(
				'sp-bid-management',
				$this->dir_url . 'assets/js/bid-management.js',
				[ 'jquery' ],
				$this->version,
				true
			);
			wp_localize_script(
				'sp-bid-management',
				'spBidManagement',
				[
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'award_bid_nonce' ),
				]
			);
		}

		// Enqueue Project Reviews JS
		if ( $hook === 'toplevel_page_project-reviews' ) {
			wp_enqueue_script(
				'sp-project-reviews',
				$this->dir_url . 'assets/js/project-reviews.js',
				[ 'jquery' ],
				$this->version,
				true
			);
		}
		
		// Only load admin.js if it exists
		if ( file_exists( $this->dir_path . 'assets/js/admin.js' ) ) {
			wp_enqueue_script( 'ksc-admin-scripts', $this->dir_url . 'assets/js/admin.js', [ 'jquery' ], time(), true );
		}

		if ( 'user-edit.php' === $hook || 'profile.php' === $hook ) {
			wp_enqueue_script( 'user-profile-js', $this->dir_url . 'assets/js/user-profile.js', ['jquery'], $this->version, true );

			$json_file = $this->dir_path . 'assets/data/indian-states-cities.json';
			$states_cities = json_decode( file_get_contents( $json_file ), true );
			
			$user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : get_current_user_id();

			wp_localize_script( 'user-profile-js', 'user_profile_vars', [
				'states_cities' => $states_cities['states'],
				'selected_state' => get_user_meta( $user_id, 'state', true ),
				'selected_city' => get_user_meta( $user_id, 'city', true ),
			]);
		}
	}
	
	public function shortcode_unified_dashboard() {
		if ( ! is_user_logged_in() ) {
			return '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">login</a> to view your dashboard.</p>';
		}

		$current_user = wp_get_current_user();
		$user_roles   = $current_user->roles;

		ob_start();
		if ( in_array( 'solar_client', $user_roles, true ) || in_array( 'administrator', $user_roles, true ) ) {
			render_solar_client_dashboard();
		} elseif ( in_array( 'solar_vendor', $user_roles, true ) || in_array( 'vendor', $user_roles, true ) ) {
			render_solar_vendor_dashboard();
		} else {
			echo '<p>You do not have the required role to view this dashboard.</p>';
		}
		return ob_get_clean();
	}
	
	public function template_include_single_project( $template ) {
		if ( is_singular( 'solar_project' ) ) {
			$new_template = $this->dir_path . 'public/views/single-solar_project.php';
			if ( '' !== $new_template ) {
				return $new_template;
			}
		}
		return $template;
	}
}
add_action('admin_init', 'sp_create_plugin_essentials');

function run_krtrim_solar_core() {
	return Krtrim_Solar_Core::instance();
}
run_krtrim_solar_core();

function sp_create_plugin_essentials() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$bids_table_name = $wpdb->prefix . 'project_bids';
	$sql_bids = "CREATE TABLE $bids_table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		project_id bigint(20) NOT NULL,
		vendor_id bigint(20) NOT NULL,
		bid_amount decimal(10, 2) NOT NULL,
		bid_type varchar(10) NOT NULL DEFAULT 'open',
		bid_details text,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) $charset_collate;";
	dbDelta( $sql_bids );

	$payments_table_name = $wpdb->prefix . 'solar_vendor_payments';
	$sql_payments = "CREATE TABLE $payments_table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		vendor_id bigint(20) NOT NULL,
		razorpay_payment_id varchar(255) NOT NULL,
		razorpay_order_id varchar(255) NOT NULL,
		amount decimal(10, 2) NOT NULL,
		states_purchased text,
		cities_purchased text,
		payment_status varchar(50) NOT NULL,
		payment_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) $charset_collate;";
	dbDelta( $sql_payments );

	$table_process = $wpdb->prefix . 'solar_process_steps';
    $sql_process = "CREATE TABLE IF NOT EXISTS $table_process (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        project_id bigint(20) NOT NULL,
        step_number int(11) NOT NULL,
        step_name varchar(200) NOT NULL,
        image_url varchar(500),
        vendor_comment text,
        client_comment text,
        admin_comment text,
        admin_status varchar(20) DEFAULT 'pending',
        approved_date datetime,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY project_id (project_id),
        KEY admin_status (admin_status)
    ) $charset_collate;";
	dbDelta( $sql_process );

    $table_notifications = $wpdb->prefix . 'solar_notifications';
    $sql_notifications = "CREATE TABLE IF NOT EXISTS $table_notifications (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        project_id bigint(20),
        message text NOT NULL,
        type varchar(50) DEFAULT 'info',
        status varchar(20) DEFAULT 'unread',
        sent_email tinyint(1) DEFAULT 0,
        sent_whatsapp tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset_collate;";
	dbDelta( $sql_notifications );

	// Lead follow-ups table for Sales Manager tracking
	$table_followups = $wpdb->prefix . 'solar_lead_followups';
	$sql_followups = "CREATE TABLE IF NOT EXISTS $table_followups (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		lead_id bigint(20) NOT NULL,
		sales_manager_id bigint(20) NOT NULL,
		activity_type varchar(50) NOT NULL,
		activity_date datetime NOT NULL,
		notes text,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY lead_id (lead_id),
		KEY sales_manager_id (sales_manager_id),
		KEY activity_date (activity_date)
	) $charset_collate;";
	dbDelta( $sql_followups );

    // ✅ ACTIVITY LOGS TABLE (Hierarchical Visibility)
    $table_logs = $wpdb->prefix . 'solar_activity_logs';
    $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        user_role varchar(50) NOT NULL,
        action_type varchar(50) NOT NULL,
        target_id bigint(20),
        target_name varchar(255),
        details text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY user_role (user_role),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta( $sql_logs );

	// Create custom roles
	$roles = [
		'manager'      => [
			'display_name' => 'Manager',
			'capabilities' => [ 'read' => true ],
		],
		'area_manager' => [
			'display_name' => 'Area Manager',
			'capabilities' => [
				'read'         => true,
				'edit_posts'   => true,
				'delete_posts' => true,
				'create_users' => true,  // Required to create client users
			],
		],
		'sales_manager' => [
			'display_name' => 'Sales Manager',
			'capabilities' => [ 'read' => true ],
		],
		'solar_vendor' => [
			'display_name' => 'Solar Vendor',
			'capabilities' => [ 'read' => true ],
		],
		'solar_client' => [
			'display_name' => 'Solar Client',
			'capabilities' => [ 'read' => true ],
		],
		'solar_cleaner' => [
			'display_name' => 'Solar Cleaner',
			'capabilities' => [ 'read' => true ],
		],
	];

	foreach ( $roles as $role => $details ) {
		if ( ! get_role( $role ) ) {
			add_role( $role, $details['display_name'], $details['capabilities'] );
		}
	}

	$pages_to_create = [
		['title' => 'Dashboard', 'slug' => 'solar-dashboard', 'content' => '[unified_solar_dashboard]'],
		['title' => 'Area Manager Dashboard', 'slug' => 'area-manager-dashboard', 'content' => '[area_manager_dashboard]'],
		['title' => 'Sales Manager Dashboard', 'slug' => 'sales-manager-dashboard', 'content' => '[sales_manager_dashboard]'],
		['title' => 'Manager Dashboard', 'slug' => 'manager-dashboard', 'content' => '[manager_dashboard]'],
		['title' => 'Cleaner Dashboard', 'slug' => 'cleaner-dashboard', 'content' => '[cleaner_dashboard]'],
		['title' => 'Vendor Registration', 'slug' => 'vendor-registration', 'content' => '[vendor_registration_form]'],
		['title' => 'Project Marketplace', 'slug' => 'project-marketplace', 'content' => '[solar_project_marketplace]'],
		['title' => 'Vendor Status', 'slug' => 'vendor-status', 'content' => '[vendor_status_dashboard]'],
		['title' => 'Book Solar Cleaning', 'slug' => 'book-solar-cleaning', 'content' => '[solar_cleaning_booking]']
	];

	foreach ( $pages_to_create as $page ) {
		if ( ! get_page_by_path( $page['slug'], OBJECT, 'page' ) ) {
			wp_insert_post(
				[
					'post_title'   => wp_strip_all_tags( $page['title'] ),
					'post_content' => $page['content'],
					'post_status'  => 'publish',
					'post_author'  => 1,
					'post_type'    => 'page',
					'post_name'    => $page['slug'],
				]
			);
		}
	}
}