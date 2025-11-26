<?php
/**
 * Plugin Name:       Krtrim Solar Core
 * Plugin URI:        https://krtrim.tech/tool
 * Description:       A comprehensive project management and bidding platform for solar companies, developed by Krtrim.
 * Version:           1.0.0
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
	public $version = '1.0.0';
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
		require_once $this->dir_path . 'admin/views/view-vendor-approval.php';
		require_once $this->dir_path . 'admin/views/view-project-reviews.php';
		require_once $this->dir_path . 'admin/views/view-general-settings.php';
		require_once $this->dir_path . 'admin/views/view-team-analysis.php';
		require_once $this->dir_path . 'public/views/view-client-dashboard.php';
		require_once $this->dir_path . 'public/views/view-vendor-dashboard.php';
		require_once $this->dir_path . 'public/views/view-area-manager-dashboard.php';
		require_once $this->dir_path . 'public/views/view-marketplace.php';
		require_once $this->dir_path . 'public/views/view-vendor-registration.php';
	}

	private function init_hooks() {
		new SP_Post_Types_Taxonomies();
		new SP_Admin_Menus();
		new SP_API_Handlers();
		new SP_Admin_Widgets();
		new SP_User_Profile_Fields();
		new SP_Process_Steps_Manager();
		new SP_Notifications_Manager();

		register_activation_hook( $this->file, [ $this, 'activate' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		add_shortcode( 'unified_solar_dashboard', [ $this, 'shortcode_unified_dashboard' ] );
		add_shortcode( 'area_manager_dashboard', 'sp_area_manager_dashboard_shortcode' );
		add_shortcode( 'vendor_registration_form', 'sp_vendor_registration_form_shortcode' );
		add_shortcode( 'solar_project_marketplace', 'sp_project_marketplace_shortcode' );
		
		add_shortcode( 'solar_project_marketplace', 'sp_project_marketplace_shortcode' );
		
		add_filter( 'template_include', [ $this, 'template_include_single_project' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ), [ $this, 'add_plugin_action_links' ] );

        // Area Manager Redirection & Restriction
        add_filter( 'login_redirect', [ $this, 'area_manager_login_redirect' ], 10, 3 );
        add_action( 'admin_init', [ $this, 'restrict_area_manager_admin_access' ] );
	}

    public function area_manager_login_redirect( $redirect_to, $request, $user ) {
        if ( isset( $user->roles ) && is_array( $user->roles ) ) {
            if ( in_array( 'area_manager', $user->roles ) ) {
                return home_url( '/area-manager-dashboard/' );
            }
        }
        return $redirect_to;
    }

    public function restrict_area_manager_admin_access() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        $user = wp_get_current_user();
        if ( in_array( 'area_manager', (array) $user->roles ) ) {
            wp_redirect( home_url( '/area-manager-dashboard/' ) );
            exit;
        }
    }

	public function add_plugin_action_links( $links ) {
		$settings_link = '<a href="options-general.php?page=ksc-settings">Settings</a>';
		$links[] = $settings_link;
		return $links;
	}

	public function activate() {
		sp_create_plugin_essentials();
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
            ]);
        }

		if ( is_page( 'solar-dashboard' ) && in_array( 'solar_client', (array) wp_get_current_user()->roles ) ) {
			$client_id = get_current_user_id();
			$args = array(
				'post_type' => 'solar-project',
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
            wp_enqueue_style('leads-clients-enhanced', $this->dir_url . 'assets/css/leads-clients-enhanced.css', [], '1.0.0');
            wp_enqueue_style('project-modal', $this->dir_url . 'assets/css/project-modal.css', [], '1.0.0');
            wp_enqueue_style('date-picker-enhanced', $this->dir_url . 'assets/css/date-picker-enhanced.css', [], '1.0.0');
            wp_enqueue_style('toast-css', $this->dir_url . 'assets/css/toast.css', [], '1.0.0');
            wp_enqueue_style('password-field-css', $this->dir_url . 'assets/css/password-field.css', [], '1.0.0');
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.7.0', true );
            wp_enqueue_script('project-modal-js', $this->dir_url . 'assets/js/project-modal.js', ['jquery'], '1.0.0', true);
			wp_enqueue_script( 'area-manager-dashboard-js', $this->dir_url . 'assets/js/area-manager-dashboard.js', [ 'jquery', 'chart-js' ], '1.0.3', true );
			wp_localize_script('area-manager-dashboard-js', 'sp_area_dashboard_vars', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'create_project_nonce' => wp_create_nonce('sp_create_project_nonce_field'),
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
                'reset_password_nonce' => wp_create_nonce('reset_password_nonce'),
				'states_cities_json_url' => $this->dir_url . 'assets/data/indian-states-cities.json',
			]);
		}

		if ( is_page( 'project-marketplace' ) ) {
			wp_enqueue_script( 'marketplace-js', $this->dir_url . 'assets/js/marketplace.js', ['jquery'], $this->version, true );

			$json_file = $this->dir_path . 'assets/data/indian-states-cities.json';
			$states_cities = json_decode( file_get_contents( $json_file ), true );

			wp_localize_script( 'marketplace-js', 'marketplace_vars', [
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'filter_projects_nonce' ),
				'states_cities' => $states_cities['states'],
			]);
		}
	}

	public function enqueue_admin_scripts( $hook ) {
		wp_enqueue_style( 'ksc-admin-styles', $this->dir_url . 'assets/css/admin.css', [], $this->version );
		wp_enqueue_script( 'ksc-admin-scripts', $this->dir_url . 'assets/js/admin.js', [ 'jquery' ], $this->version, true );

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

function run_krtrim_solar_core() {
	return Krtrim_Solar_Core::instance();
}
run_krtrim_solar_core();

function sp_create_plugin_essentials() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$bids_table_name = $wpdb->prefix . 'project_bids';
	$sql_bids        = "CREATE TABLE $bids_table_name ( id mediumint(9) NOT NULL AUTO_INCREMENT, project_id bigint(20) NOT NULL, vendor_id bigint(20) NOT NULL, bid_amount decimal(10, 2) NOT NULL, bid_type varchar(10) NOT NULL DEFAULT 'open', bid_details text, created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL, PRIMARY KEY  (id) ) $charset_collate;";
	dbDelta( $sql_bids );

	$payments_table_name = $wpdb->prefix . 'solar_vendor_payments';
	$sql_payments        = "CREATE TABLE $payments_table_name ( id mediumint(9) NOT NULL AUTO_INCREMENT, vendor_id bigint(20) NOT NULL, razorpay_payment_id varchar(255) NOT NULL, razorpay_order_id varchar(255) NOT NULL, amount decimal(10, 2) NOT NULL, states_purchased text, cities_purchased text, payment_status varchar(50) NOT NULL, payment_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL, PRIMARY KEY  (id) ) $charset_collate;";
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
		'solar_vendor' => [
			'display_name' => 'Solar Vendor',
			'capabilities' => [ 'read' => true ],
		],
		'solar_client' => [
			'display_name' => 'Solar Client',
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
		['title' => 'Vendor Registration', 'slug' => 'vendor-registration', 'content' => '[vendor_registration_form]'],
		['title' => 'Project Marketplace', 'slug' => 'project-marketplace', 'content' => '[solar_project_marketplace]']
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