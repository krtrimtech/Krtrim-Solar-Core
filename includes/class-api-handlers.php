<?php
/**
 * Handles all AJAX and REST API endpoints.
 */
class SP_API_Handlers {

    /**
     * Constructor. Hooks into WordPress.
     */
    public function __construct() {
        // REST API
        add_action('rest_api_init', [ $this, 'register_rest_routes' ]);

        // AJAX Handlers
        add_action('wp_ajax_vendor_upload_step', [ $this, 'vendor_upload_step' ]);
        add_action('wp_ajax_nopriv_vendor_upload_step', [ $this, 'vendor_upload_step' ]);
        add_action('wp_ajax_submit_project_bid', [ $this, 'submit_project_bid' ]);
        add_action('wp_ajax_award_project_to_vendor', [ $this, 'award_project_to_vendor' ]);
        add_action('wp_ajax_get_area_manager_data', [ $this, 'get_area_manager_data' ]);
        add_action('wp_ajax_review_vendor_submission', [ $this, 'review_vendor_submission' ]);
        add_action('wp_ajax_create_solar_project', [ $this, 'create_solar_project' ]);
        add_action('wp_ajax_get_area_manager_projects', [ $this, 'get_area_manager_projects' ]);
        add_action('wp_ajax_get_area_manager_project_details', [ $this, 'get_area_manager_project_details' ]);
        add_action('wp_ajax_complete_vendor_registration', [ $this, 'complete_vendor_registration' ]);
        add_action('wp_ajax_nopriv_complete_vendor_registration', [ $this, 'complete_vendor_registration' ]);
        add_action('wp_ajax_update_vendor_status', [ $this, 'update_vendor_status' ]);
        add_action('wp_ajax_create_razorpay_order', [ $this, 'create_razorpay_order' ]);
        add_action('wp_ajax_nopriv_create_razorpay_order', [ $this, 'create_razorpay_order' ]);
        add_action('wp_ajax_filter_projects', [ $this, 'filter_projects' ]);
        add_action('wp_ajax_nopriv_filter_projects', [ $this, 'filter_projects' ]);
        add_action('wp_ajax_vendor_submit_step', [ $this, 'vendor_submit_step' ]);
        add_action('wp_ajax_client_submit_step_comment', [ $this, 'client_submit_step_comment' ]);
        add_action('wp_ajax_assign_area_manager_location', [ $this, 'assign_area_manager_location' ]);
        add_action('wp_ajax_filter_marketplace_projects', [ $this, 'filter_marketplace_projects' ]);
        add_action('wp_ajax_nopriv_filter_marketplace_projects', [ $this, 'filter_marketplace_projects' ]);
        add_action('wp_ajax_get_area_manager_reviews', [ $this, 'get_area_manager_reviews' ]);
        add_action('wp_ajax_get_area_manager_vendor_approvals', [ $this, 'get_area_manager_vendor_approvals' ]);
        add_action('wp_ajax_create_vendor_from_dashboard', [ $this, 'create_vendor_from_dashboard' ]);
        add_action('wp_ajax_create_client_from_dashboard', [ $this, 'create_client_from_dashboard' ]);
        add_action('wp_ajax_get_area_manager_dashboard_stats', [ $this, 'get_area_manager_dashboard_stats' ]);
    }

    public function get_area_manager_dashboard_stats() {
        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager = wp_get_current_user();
        $args = [
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'author' => $manager->ID,
        ];
        $projects = get_posts($args);

        $total_projects = count($projects);
        $completed_projects = 0;
        $in_progress_projects = 0;
        $total_paid_to_vendors = 0;
        $total_company_profit = 0;

        foreach ($projects as $project) {
            $status = get_post_meta($project->ID, '_project_status', true);
            if ($status === 'completed') {
                $completed_projects++;
            } elseif ($status === 'in_progress') {
                $in_progress_projects++;
            }

            $paid = get_post_meta($project->ID, '_paid_to_vendor', true) ?: 0;
            $winning_bid = get_post_meta($project->ID, '_winning_bid_amount', true) ?: 0;
            $profit = $winning_bid - $paid;
            $total_paid_to_vendors += $paid;
            $total_company_profit += $profit;
        }

        wp_send_json_success([
            'total_projects' => $total_projects,
            'completed_projects' => $completed_projects,
            'in_progress_projects' => $in_progress_projects,
            'total_paid_to_vendors' => $total_paid_to_vendors,
            'total_company_profit' => $total_company_profit,
        ]);
        add_action('wp_ajax_get_vendor_earnings_chart_data', [ $this, 'get_vendor_earnings_chart_data' ]);
    }

    public function get_vendor_earnings_chart_data() {
        if (!is_user_logged_in() || !in_array('solar_vendor', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $vendor_id = get_current_user_id();
        $earnings = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $earnings[$month] = 0;
        }

        global $wpdb;
        $posts_table = $wpdb->prefix . 'posts';
        $postmeta_table = $wpdb->prefix . 'postmeta';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT p.post_date, pm.meta_value as paid_amount
            FROM {$posts_table} p
            JOIN {$postmeta_table} pm ON p.ID = pm.post_id
            JOIN {$postmeta_table} pm2 ON p.ID = pm2.post_id
            WHERE p.post_type = 'solar_project'
            AND pm.meta_key = '_paid_to_vendor'
            AND pm2.meta_key = '_assigned_vendor_id'
            AND pm2.meta_value = %d
            AND p.post_date >= %s",
            $vendor_id,
            date('Y-m-01', strtotime('-11 months'))
        ));

        foreach ($results as $result) {
            $month = date('Y-m', strtotime($result->post_date));
            if (isset($earnings[$month])) {
                $earnings[$month] += (float)$result->paid_amount;
            }
        }

        wp_send_json_success(['labels' => array_keys($earnings), 'data' => array_values($earnings)]);
    }

    public function create_client_from_dashboard() {
        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager_id = get_current_user_id();
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];

        if (username_exists($username)) {
            wp_send_json_error(['message' => 'Username already exists.']);
        }

        if (email_exists($email)) {
            wp_send_json_error(['message' => 'Email already exists.']);
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }

        $user = new WP_User($user_id);
        $user->set_role('solar_client');

        update_user_meta($user_id, '_created_by_area_manager', $manager_id);

        wp_send_json_success(['message' => 'Client created successfully.']);
    }

    public function create_vendor_from_dashboard() {
        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager_id = get_current_user_id();
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];

        if (username_exists($username)) {
            wp_send_json_error(['message' => 'Username already exists.']);
        }

        if (email_exists($email)) {
            wp_send_json_error(['message' => 'Email already exists.']);
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }

        $user = new WP_User($user_id);
        $user->set_role('solar_vendor');

        update_user_meta($user_id, '_created_by_area_manager', $manager_id);

        wp_send_json_success(['message' => 'Vendor created successfully.']);
    }

    public function get_area_manager_vendor_approvals() {
        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager = wp_get_current_user();
        $args = [
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'author' => $manager->ID,
            'fields' => 'ids',
        ];
        $project_ids = get_posts($args);

        if (empty($project_ids)) {
            wp_send_json_success(['vendors' => []]);
        }

        global $wpdb;
        $bids_table = $wpdb->prefix . 'project_bids';
        $vendors = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT u.* FROM {$wpdb->users} u JOIN {$bids_table} b ON u.ID = b.vendor_id WHERE b.project_id IN (" . implode(',', $project_ids) . ")",
            'pending'
        ));

        wp_send_json_success(['vendors' => $vendors]);
    }

    public function get_area_manager_reviews() {
        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager = wp_get_current_user();
        $args = [
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'author' => $manager->ID,
            'fields' => 'ids',
        ];
        $project_ids = get_posts($args);

        if (empty($project_ids)) {
            wp_send_json_success(['reviews' => []]);
        }

        global $wpdb;
        $steps_table = $wpdb->prefix . 'solar_process_steps';
        $reviews = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$steps_table} WHERE project_id IN (" . implode(',', $project_ids) . ") AND admin_status = %s ORDER BY created_at DESC",
            'pending'
        ));

        wp_send_json_success(['reviews' => $reviews]);
    }

    public function filter_marketplace_projects() {
        check_ajax_referer('filter_projects_nonce', 'nonce');

        $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        $budget = isset($_POST['budget']) ? floatval($_POST['budget']) : 0;

        $args = [
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'meta_query' => ['relation' => 'AND'],
            'tax_query' => ['relation' => 'AND'],
        ];

        if (!empty($state)) {
            $args['meta_query'][] = [
                'key' => 'project_state',
                'value' => $state,
                'compare' => '=',
            ];
        }

        if (!empty($city)) {
            $args['tax_query'][] = [
                'taxonomy' => 'project_city',
                'field' => 'name',
                'terms' => $city,
            ];
        }

        if (!empty($budget)) {
            $args['meta_query'][] = [
                'key' => 'total_project_cost',
                'value' => $budget,
                'type' => 'NUMERIC',
                'compare' => '<=',
            ];
        }

        $projects_query = new WP_Query($args);
        $projects_data = [];

        if ($projects_query->have_posts()) {
            while ($projects_query->have_posts()) {
                $projects_query->the_post();
                $project_id = get_the_ID();
                
                $state = get_post_meta($project_id, 'project_state', true);
                $city_terms = get_the_terms($project_id, 'project_city');
                $city = 'N/A';
                if ($city_terms && !is_wp_error($city_terms)) {
                    $city = $city_terms[0]->name;
                }

                $location = 'N/A';
                if ($city != 'N/A' && $state) {
                    $location = $city . ', ' . $state;
                } elseif ($city != 'N/A') {
                    $location = $city;
                } elseif ($state) {
                    $location = $state;
                }

                $projects_data[] = [
                    'id' => $project_id,
                    'title' => get_the_title(),
                    'location' => $location,
                    'budget' => get_post_meta($project_id, 'total_project_cost', true),
                    'link' => get_permalink(),
                ];
            }
        }
        wp_reset_postdata();

        wp_send_json_success(['projects' => $projects_data]);
    }

    public function assign_area_manager_location() {
        check_ajax_referer('assign_location_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager_id = isset($_POST['manager_id']) ? intval($_POST['manager_id']) : 0;
        $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';

        if (empty($manager_id) || empty($state) || empty($city)) {
            wp_send_json_error(['message' => 'Invalid data provided.']);
        }

        update_user_meta($manager_id, 'assigned_state', $state);
        update_user_meta($manager_id, 'assigned_city', $city);

        wp_send_json_success(['message' => 'Location assigned successfully.']);
    }

    public function client_submit_step_comment() {
        check_ajax_referer('client_comment_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in.']);
        }

        $user = wp_get_current_user();
        if (!in_array('solar_client', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            wp_send_json_error(['message' => 'You do not have permission to comment.']);
        }

        $step_id = isset($_POST['step_id']) ? intval($_POST['step_id']) : 0;
        $comment_text = isset($_POST['comment_text']) ? sanitize_textarea_field($_POST['comment_text']) : '';

        if (empty($step_id) || empty($comment_text)) {
            wp_send_json_error(['message' => 'Invalid data provided.']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'solar_process_steps';

        // Optionally, you could append comments instead of replacing
        $existing_comment = $wpdb->get_var($wpdb->prepare("SELECT client_comment FROM $table WHERE id = %d", $step_id));
        $new_comment = $existing_comment ? $existing_comment . "\n\n" . $comment_text : $comment_text;

        $result = $wpdb->update(
            $table,
            ['client_comment' => $new_comment],
            ['id' => $step_id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Comment added successfully.']);
        } else {
            wp_send_json_error(['message' => 'Failed to save your comment.']);
        }
    }

    public function vendor_submit_step() {
        $step_id = isset($_POST['step_id']) ? intval($_POST['step_id']) : 0;
        check_ajax_referer('solar_upload_' . $step_id, 'solar_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
        }

        $user = wp_get_current_user();
        if (!in_array('solar_vendor', (array) $user->roles) && !in_array('vendor', (array) $user->roles)) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

        if (!isset($_FILES['step_image'])) {
            wp_send_json_error(['message' => 'No image uploaded']);
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('step_image', $project_id);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => 'Upload failed: ' . $attachment_id->get_error_message()]);
        }

        $image_url = wp_get_attachment_url($attachment_id);
        $vendor_comment = sanitize_textarea_field($_POST['vendor_comment']);

        if (empty($vendor_comment)) {
            wp_send_json_error(['message' => 'Comment required']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'solar_process_steps';

        $wpdb->update(
            $table,
            [
                'image_url' => $image_url,
                'vendor_comment' => $vendor_comment,
                'admin_status' => 'pending',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $step_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        do_action('sp_vendor_step_submitted', $step_id, $project_id);

        wp_send_json_success(['message' => 'Step submitted successfully! The page will now reload.']);
    }

    /**
     * Register all REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('solar/v1', '/client-notifications', [
            'methods' => 'GET',
            'callback' => [ $this, 'client_get_notifications_rest' ],
            'permission_callback' => function () {
                return is_user_logged_in() && (current_user_can('solar_client') || current_user_can('administrator'));
            },
        ]);

        register_rest_route('solar/v1', '/client-comments', [
            'methods' => 'POST',
            'callback' => [ $this, 'client_submit_comment_rest' ],
            'permission_callback' => function () {
                return is_user_logged_in() && (current_user_can('solar_client') || current_user_can('administrator'));
            },
        ]);

        register_rest_route('solar/v1', '/vendor-notifications', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_vendor_notifications_rest' ],
            'permission_callback' => function () {
                return is_user_logged_in() && (current_user_can('solar_vendor') || current_user_can('vendor'));
            },
        ]);

        register_rest_route('solar/v1', '/vendor-notifications/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [ $this, 'delete_vendor_notification_rest' ],
            'permission_callback' => function () {
                return is_user_logged_in() && (current_user_can('solar_vendor') || current_user_can('vendor'));
            },
        ]);
    }

    // --- All handler functions will be moved here ---
    
    public function client_get_notifications_rest(WP_REST_Request $request) {
        $user = wp_get_current_user();
        $client_id = $user->ID;

        global $wpdb;
        $steps_table = $wpdb->prefix . 'solar_process_steps';

        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT sp.*, p.post_title FROM {$steps_table} sp JOIN {$wpdb->posts} p ON sp.project_id = p.ID JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'client_user_id' AND pm.meta_value = %d WHERE sp.admin_status IN ('approved', 'rejected') AND sp.image_url IS NOT NULL ORDER BY sp.approved_date DESC LIMIT 10",
            $client_id
        ));

        $formatted = [];
        foreach ($notifications as $notif) {
            $icon = $notif->admin_status === 'approved' ? '✅' : '❌';
            $formatted[] = [
                'id' => $notif->id,
                'type' => $notif->admin_status,
                'icon' => $icon,
                'title' => ucfirst($notif->admin_status) . ' Step',
                'message' => 'Step ' . $notif->step_number . ' for ' . substr($notif->post_title, 0, 25),
                'time_ago' => human_time_diff(strtotime($notif->approved_date), current_time('timestamp')) . ' ago',
            ];
        }

        return rest_ensure_response($formatted);
    }

    public function client_submit_comment_rest(WP_REST_Request $request) {
        $step_id = intval($request->get_param('step_id'));
        $comment_text = sanitize_textarea_field($request->get_param('comment_text'));

        if (empty($comment_text)) {
            return new WP_Error('empty_comment', 'Comment cannot be empty', ['status' => 400]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'solar_process_steps';

        $existing_comment = $wpdb->get_var($wpdb->prepare("SELECT client_comment FROM $table WHERE id = %d", $step_id));
        $updated_comment = trim($existing_comment . "\n\n" . "Client: " . $comment_text);

        $updated = $wpdb->update(
            $table,
            ['client_comment' => $updated_comment, 'updated_at' => current_time('mysql')],
            ['id' => $step_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('db_error', 'Failed to update comment', ['status' => 500]);
        }

        return rest_ensure_response(['message' => 'Comment submitted successfully']);
    }

    public function get_vendor_notifications_rest(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        if (!$user_id) return new WP_Error('no_user', 'Not logged in', ['status' => 401]);

        global $wpdb;
        $steps_table = $wpdb->prefix . 'solar_process_steps';
        $posts_table = $wpdb->posts;
        $postmeta_table = $wpdb->postmeta;

        $step_notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT sp.id, sp.project_id, sp.step_number, sp.step_name, sp.admin_status, sp.admin_comment, sp.approved_date AS date, p.post_title FROM {$steps_table} sp JOIN {$posts_table} p ON sp.project_id = p.ID JOIN {$postmeta_table} pm ON p.ID = pm.post_id WHERE pm.meta_key = 'assigned_vendor_id' AND pm.meta_value = %s AND sp.image_url IS NOT NULL AND sp.approved_date IS NOT NULL AND sp.admin_status IN ('approved', 'rejected') ORDER BY sp.approved_date DESC LIMIT 20",
            $user_id
        ));

        $formatted = [];
        foreach ($step_notifications as $notif) {
            $time_ago = human_time_diff(strtotime($notif->date), current_time('timestamp')) . ' ago';
            $title = $notif->post_title ? substr($notif->post_title, 0, 35) : 'Project';
            
            if ($notif->admin_status === 'approved') {
                $formatted[] = [
                    'id' => $notif->id,
                    'type' => 'approved',
                    'icon' => '✅',
                    'title' => 'Step Approved',
                    'message' => "<strong>" . esc_html($title) . "</strong> - Step {" . $notif->step_number . "} " . esc_html($notif->step_name),
                    'time_ago' => $time_ago,
                ];
            } elseif ($notif->admin_status === 'rejected') {
                $comment = $notif->admin_comment ? substr($notif->admin_comment, 0, 50) : 'Please resubmit';
                $formatted[] = [
                    'id' => $notif->id,
                    'type' => 'rejected',
                    'icon' => '❌',
                    'title' => 'Step Rejected',
                    'message' => "<strong>" . esc_html($title) . "</strong> - " . esc_html($comment) . "...",
                    'time_ago' => $time_ago,
                ];
            }
        }

        return rest_ensure_response(['notifications' => $formatted]);
    }

    public function delete_vendor_notification_rest(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        if (!$user_id) return new WP_Error('unauthorized', 'Not logged in', ['status' => 401]);

        $notif_id = intval($request->get_param('id'));
        if (!$notif_id) return new WP_Error('no_id', 'Notification ID missing', ['status' => 400]);

        return rest_ensure_response(['message' => 'Notification dismissed (simulated).']);
    }

    public function vendor_upload_step() {
        check_ajax_referer('solar_upload_' . $_POST['step_id'], 'solar_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
        }

        $user = wp_get_current_user();
        if (!in_array('vendor', (array) $user->roles) && !in_array('solar_vendor', (array) $user->roles)) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $step_id = intval($_POST['step_id']);
        $project_id = intval($_POST['project_id']);

        if (!isset($_FILES['step_image'])) {
            wp_send_json_error(['message' => 'No image uploaded']);
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('step_image', $project_id);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => 'Upload failed: ' . $attachment_id->get_error_message()]);
        }

        $image_url = wp_get_attachment_url($attachment_id);
        $vendor_comment = sanitize_textarea_field($_POST['vendor_comment']);

        if (empty($vendor_comment)) {
            wp_send_json_error(['message' => 'Comment required']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'solar_process_steps';

        $wpdb->update(
            $table,
            ['image_url' => $image_url, 'vendor_comment' => $vendor_comment, 'admin_status' => 'pending', 'updated_at' => current_time('mysql')],
            ['id' => $step_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        $project_status = get_post_meta($project_id, 'project_status', true);
        if (in_array($project_status, ['pending', 'assigned'])) {
            update_post_meta($project_id, 'project_status', 'in_progress');
        }

        wp_send_json_success(['message' => 'Uploaded successfully!']);
    }

    public function submit_project_bid() {
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        check_ajax_referer('submit_bid_nonce_' . $project_id, 'submit_bid_nonce');

        if (!is_user_logged_in() || !in_array('solar_vendor', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Only vendors can submit bids.']);
        }

        $vendor_id = get_current_user_id();
        $bid_amount = isset($_POST['bid_amount']) ? floatval($_POST['bid_amount']) : 0;
        $bid_details = isset($_POST['bid_details']) ? sanitize_textarea_field($_POST['bid_details']) : '';
        $bid_type = isset($_POST['bid_type']) && in_array($_POST['bid_type'], ['open', 'hidden']) ? $_POST['bid_type'] : 'open';

        if (empty($project_id) || empty($bid_amount)) {
            wp_send_json_error(['message' => 'Project ID and bid amount are required.']);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'project_bids';

        $result = $wpdb->insert(
            $table_name,
            [
                'project_id' => $project_id,
                'vendor_id' => $vendor_id,
                'bid_amount' => $bid_amount,
                'bid_details' => $bid_details,
                'bid_type' => $bid_type,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%f', '%s', '%s', '%s']
        );

        if ($result) {
            wp_send_json_success(['message' => 'Bid submitted successfully!']);
        } else {
            wp_send_json_error(['message' => 'Failed to save bid to the database.']);
        }
    }

    public function award_project_to_vendor() {
        check_ajax_referer('award_bid_nonce', 'nonce');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;
        $bid_amount = isset($_POST['bid_amount']) ? floatval($_POST['bid_amount']) : 0;

        if (empty($project_id) || empty($vendor_id)) {
            wp_send_json_error(['message' => 'Invalid project or vendor ID.']);
        }

        $project = get_post($project_id);
        $manager = wp_get_current_user();

        if (!$project || $project->post_author != $manager->ID) {
            wp_send_json_error(['message' => 'You do not have permission to award this project.']);
        }

        update_post_meta($project_id, 'winning_vendor_id', $vendor_id);
        update_post_meta($project_id, 'winning_bid_amount', $bid_amount);
        update_post_meta($project_id, 'assigned_vendor_id', $vendor_id);
        update_post_meta($project_id, 'total_project_cost', $bid_amount);
        update_post_meta($project_id, '_project_status', 'assigned');

        wp_update_post(['ID' => $project_id, 'post_status' => 'assigned']);

        $winning_vendor = get_userdata($vendor_id);
        $project_title = get_the_title($project_id);
        if ($winning_vendor) {
            $notification_options = get_option('sp_notification_options');
            $vendor_phone = get_user_meta($vendor_id, 'phone', true);
            $subject = 'Congratulations! You Won the Bid for Project: ' . $project_title;
            $email_message = "<p>Congratulations! Your bid of ₹" . number_format($bid_amount, 2) . " for project '" . $project_title . "' has been accepted.</p>";
            $email_message .= "<p>Please log in to your dashboard to view project details.</p>";
            $whatsapp_message = "Congratulations! Your bid of ₹" . number_format($bid_amount, 2) . " for project '" . $project_title . "' has been accepted. Please log in to your dashboard to view project details.";

            $whatsapp_data = null;
            if (isset($notification_options['whatsapp_enable']) && !empty($vendor_phone)) {
                $whatsapp_data = [
                    'phone' => '91' . preg_replace('/\D/', '', $vendor_phone),
                    'message' => urlencode($whatsapp_message)
                ];
            }
        }

        wp_send_json_success([
            'message' => 'Project awarded successfully!',
            'whatsapp_data' => $whatsapp_data
        ]);
    }

    public function get_area_manager_data() {
        check_ajax_referer('sp_area_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager_id = isset($_POST['manager_id']) ? intval($_POST['manager_id']) : 0;
        if (empty($manager_id)) {
            wp_send_json_error(['message' => 'Invalid Area Manager ID.']);
        }

        $city_id = get_user_meta($manager_id, 'assigned_city', true);
        if (empty($city_id)) {
            wp_send_json_error(['message' => 'This manager is not assigned to a city.']);
        }

        $args = [
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'project_city',
                    'field'    => 'term_id',
                    'terms'    => $city_id,
                ],
            ],
        ];

        $projects = new WP_Query($args);
        
        $stats = [
            'total_projects' => 0,
            'completed' => 0,
            'in_progress' => 0,
            'assigned' => 0,
            'pending' => 0,
        ];
        $clients = [];
        $vendors = [];

        if ($projects->have_posts()) {
            $stats['total_projects'] = $projects->post_count;
            while ($projects->have_posts()) {
                $projects->the_post();
                $project_id = get_the_ID();
                $status = get_post_status($project_id) == 'publish' ? (get_post_meta($project_id, '_project_status', true) ?: 'pending') : get_post_status($project_id);

                if (isset($stats[$status])) {
                    $stats[$status]++;
                }

                $client_id = get_post_meta($project_id, '_client_user_id', true);
                if ($client_id && !isset($clients[$client_id])) {
                    $client_user = get_userdata($client_id);
                    if($client_user) $clients[$client_id] = $client_user->display_name;
                }

                $vendor_id = get_post_meta($project_id, '_assigned_vendor_id', true);
                if ($vendor_id && !isset($vendors[$vendor_id])) {
                    $vendor_user = get_userdata($vendor_id);
                    if($vendor_user) $vendors[$vendor_id] = $vendor_user->display_name;
                }
            }
        }
        wp_reset_postdata();

        wp_send_json_success([
            'stats' => $stats,
            'clients' => $clients,
            'vendors' => $vendors,
        ]);
    }

    public function review_vendor_submission() {
        check_ajax_referer('sp_review_nonce', 'nonce');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $step_id = isset($_POST['step_id']) ? intval($_POST['step_id']) : 0;
        $decision = isset($_POST['decision']) && in_array($_POST['decision'], ['approved', 'rejected']) ? $_POST['decision'] : '';
        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';

        if (empty($step_id) || empty($decision)) {
            wp_send_json_error(['message' => 'Invalid step ID or decision.']);
        }

        global $wpdb;
        $steps_table = $wpdb->prefix . 'solar_process_steps';
        $submission = $wpdb->get_row($wpdb->prepare("SELECT project_id FROM {$steps_table} WHERE id = %d", $step_id));

        if (!$submission) {
            wp_send_json_error(['message' => 'Invalid submission.']);
        }

        $project = get_post($submission->project_id);
        $manager = wp_get_current_user();

        if (!$project || $project->post_author != $manager->ID) {
            wp_send_json_error(['message' => 'You do not have permission to review this submission.']);
        }

        $wpdb->update(
            $steps_table,
            [
                'admin_status' => $decision,
                'admin_comment' => $comment,
                'approved_date' => current_time('mysql'),
            ],
            ['id' => $step_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        $submission = $wpdb->get_row($wpdb->prepare("SELECT project_id, step_number, step_name FROM {$steps_table} WHERE id = %d", $step_id));
        $whatsapp_data = null;

        if ($submission) {
            $project_id = $submission->project_id;
            $vendor_id = get_post_meta($project_id, '_assigned_vendor_id', true);
            $vendor = get_userdata($vendor_id);
            $project_title = get_the_title($project_id);

            if ($vendor) {
                $notification_options = get_option('sp_notification_options');
                $vendor_phone = get_user_meta($vendor_id, 'phone', true);
                $whatsapp_message = '';

                if ($decision === 'approved') {
                    if (isset($notification_options['email_submission_approved']) && $notification_options['email_submission_approved'] === '1') {
                        $subject = 'Project Step Approved: ' . $project_title;
                        $email_message = "<p>Good news! Step " . $submission->step_number . " (" . $submission->step_name . ") for project '" . $project_title . "' has been approved.</p>";
                        if ($comment) $email_message .= "<p>Admin comment: " . esc_html($comment) . "</p>";
                        wp_mail($vendor->user_email, $subject, $email_message, ['Content-Type: text/html; charset=UTF-8']);
                    }
                    if (isset($notification_options['whatsapp_enable']) && isset($notification_options['whatsapp_submission_approved']) && $notification_options['whatsapp_submission_approved'] === '1' && !empty($vendor_phone)) {
                        $whatsapp_message = "Good news! Step " . $submission->step_number . " (" . $submission->step_name . ") for project '" . $project_title . "' has been approved.";
                        if ($comment) $whatsapp_message .= "\nAdmin comment: " . esc_html($comment);
                        $whatsapp_data = [
                            'phone' => '91' . preg_replace('/\D/', '', $vendor_phone),
                            'message' => urlencode($whatsapp_message)
                        ];
                    }

                } else { // rejected
                    if (isset($notification_options['email_submission_rejected']) && $notification_options['email_submission_rejected'] === '1') {
                        $subject = 'Project Step Rejected: ' . $project_title;
                        $email_message = "<p>Step " . $submission->step_number . " (" . $submission->step_name . ") for project '" . $project_title . "' has been rejected.</p>";
                        if ($comment) $email_message .= "<p>Reason: " . esc_html($comment) . "</p>";
                        wp_mail($vendor->user_email, $subject, $email_message, ['Content-Type: text/html; charset=UTF-8']);
                    }
                    if (isset($notification_options['whatsapp_enable']) && isset($notification_options['whatsapp_submission_rejected']) && $notification_options['whatsapp_submission_rejected'] === '1' && !empty($vendor_phone)) {
                        $whatsapp_message = "Step " . $submission->step_number . " (" . $submission->step_name . ") for project '" . $project_title . "' has been rejected.";
                        if ($comment) $whatsapp_message .= "\nReason: " . esc_html($comment);
                         $whatsapp_data = [
                            'phone' => '91' . preg_replace('/\D/', '', $vendor_phone),
                            'message' => urlencode($whatsapp_message)
                        ];
                    }
                }
            }
        }

        wp_send_json_success([
            'message' => 'Submission status updated successfully.',
            'whatsapp_data' => $whatsapp_data
        ]);
    }

    public function create_solar_project() {
        check_ajax_referer('sp_create_project_nonce_field', 'sp_create_project_nonce');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager = wp_get_current_user();
        $data = $_POST;

        $project_data = [
            'post_title'   => sanitize_text_field($data['project_title']),
            'post_status'  => 'publish',
            'post_author'  => $manager->ID,
            'post_type'    => 'solar_project',
        ];
        $project_id = wp_insert_post($project_data);

        if (is_wp_error($project_id)) {
            wp_send_json_error(['message' => 'Could not create project: ' . $project_id->get_error_message()]);
        }

        $fields = [
            'project_state',
            'project_city',
            'project_status',
            'client_user_id',
            'solar_system_size_kw',
            'client_address',
            'client_phone_number',
            'project_start_date',
            'vendor_assignment_method',
            'assigned_vendor_id',
            'paid_to_vendor',
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                update_post_meta($project_id, '_' . $field, sanitize_text_field($data[$field]));
            }
        }

        wp_send_json_success(['message' => 'Project created successfully!', 'project_id' => $project_id]);
    }

    public function get_area_manager_projects() {
        check_ajax_referer('sp_area_dashboard_vars', 'security');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager = wp_get_current_user();

        $args = [
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'author' => $manager->ID,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $projects_query = new WP_Query($args);
        $projects_data = [];

        if ($projects_query->have_posts()) {
            while ($projects_query->have_posts()) {
                $projects_query->the_post();
                $project_id = get_the_ID();
                global $wpdb;
                $steps_table = $wpdb->prefix . 'solar_process_steps';
                $pending_submissions = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$steps_table} WHERE project_id = %d AND admin_status = 'pending'",
                    $project_id
                ));

                $projects_data[] = [
                    'id' => $project_id,
                    'title' => get_the_title(),
                    'status' => get_post_meta($project_id, '_project_status', true) ?: 'pending',
                    'pending_submissions' => $pending_submissions,
                ];
            }
        }
        wp_reset_postdata();

        wp_send_json_success(['projects' => $projects_data]);
    }

    public function get_area_manager_project_details() {
        check_ajax_referer('sp_project_details_nonce', 'nonce');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        if (empty($project_id)) {
            wp_send_json_error(['message' => 'Invalid project ID.']);
        }

        $manager = wp_get_current_user();
        $project = get_post($project_id);

        if (!$project || $project->post_author != $manager->ID) {
            wp_send_json_error(['message' => 'You do not have permission to view this project.']);
        }

        // Get project meta
        $meta = [
            'System Size' => get_post_meta($project_id, '_solar_system_size_kw', true) . ' kW',
            'Client Address' => get_post_meta($project_id, '_client_address', true),
            'Status' => get_post_meta($project_id, '_project_status', true),
        ];

        // Get vendor submissions
        global $wpdb;
        $steps_table = $wpdb->prefix . 'solar_process_steps';
        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$steps_table} WHERE project_id = %d ORDER BY step_number ASC",
            $project_id
        ));

        // Get project bids
        $bids_table = $wpdb->prefix . 'project_bids';
        $bids = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, u.display_name as vendor_name FROM {$bids_table} b JOIN {$wpdb->users} u ON b.vendor_id = u.ID WHERE b.project_id = %d ORDER BY b.created_at DESC",
            $project_id
        ));

        wp_send_json_success([
            'title' => $project->post_title,
            'meta' => $meta,
            'submissions' => $submissions,
            'bids' => $bids,
        ]);
    }

    public function complete_vendor_registration() {
        check_ajax_referer('vendor_registration_nonce', 'nonce');

        $registration_data = json_decode(stripslashes($_POST['registration_data']), true);
        $payment_response = json_decode(stripslashes($_POST['payment_response']), true);
        
        if (empty($registration_data) || empty($payment_response)) {
            wp_send_json_error(['message' => 'Invalid registration or payment data.']);
        }

        if (!class_exists('SP_Razorpay_Light_Client')) {
            require_once plugin_dir_path(__FILE__) . 'class-razorpay-light-client.php';
        }

        $razorpay_client = new SP_Razorpay_Light_Client();
        $signature_valid = $razorpay_client->verify_signature([
            'razorpay_order_id' => sanitize_text_field($payment_response['razorpay_order_id']),
            'razorpay_payment_id' => sanitize_text_field($payment_response['razorpay_payment_id']),
            'razorpay_signature' => sanitize_text_field($payment_response['razorpay_signature']),
        ]);

        if (!$signature_valid) {
            wp_send_json_error(['message' => 'Payment verification failed. The signature is not valid.']);
            return;
        }
        
        $basic_info = $registration_data['basic_info'];
        $coverage = $registration_data['coverage'];
        
        if (email_exists($basic_info['email'])) {
            wp_send_json_error(['message' => 'This email address is already registered.']);
        }
        
        $username = sanitize_user(str_replace('@', '_', $basic_info['email']));
        $user_id = wp_create_user($username, $basic_info['password'], $basic_info['email']);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }
        
        $user = new WP_User($user_id);
        $user->set_role('solar_vendor');
        
        update_user_meta($user_id, 'first_name', sanitize_text_field($basic_info['full_name']));
        update_user_meta($user_id, 'company_name', sanitize_text_field($basic_info['company_name']));
        update_user_meta($user_id, 'phone', sanitize_text_field($basic_info['phone']));
        
        $state_ids = array_column($coverage['states'], 'id');
        $city_ids = array_column($coverage['cities'], 'id');
        
        update_user_meta($user_id, 'purchased_states', $state_ids);
        update_user_meta($user_id, 'purchased_cities', $city_ids);
        update_user_meta($user_id, 'total_coverage_payment', floatval($coverage['total_amount']));
        update_user_meta($user_id, 'coverage_payment_date', current_time('mysql'));
        
        update_user_meta($user_id, 'vendor_payment_status', 'completed');
        update_user_meta($user_id, 'email_verified', 'no');
        update_user_meta($user_id, 'account_approved', 'no');
        
        global $wpdb;
        $payment_table = $wpdb->prefix . 'solar_vendor_payments';
        $wpdb->insert($payment_table, [
            'vendor_id' => $user_id,
            'razorpay_payment_id' => sanitize_text_field($payment_response['razorpay_payment_id']),
            'razorpay_order_id' => sanitize_text_field($payment_response['razorpay_order_id']),
            'amount' => floatval($coverage['total_amount']),
            'states_purchased' => wp_json_encode($coverage['states']),
            'cities_purchased' => wp_json_encode($coverage['cities']),
            'payment_status' => 'completed',
            'payment_date' => current_time('mysql'),
        ]);
        
        wp_send_json_success(['message' => 'Registration completed! Please check your email to verify your account.']);
    }

    public function update_vendor_status() {
        check_ajax_referer('sp_vendor_approval_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (empty($user_id) || !in_array($status, ['yes', 'no'])) {
            wp_send_json_error(['message' => 'Invalid data provided.']);
        }

        update_user_meta($user_id, 'account_approved', $status);

        if ($status === 'yes') {
            do_action('sp_vendor_approved', $user_id);
        }

        wp_send_json_success(['message' => 'Vendor status updated.']);
    }

    public function filter_projects() {
        // ... (existing function)
    }

    public function create_razorpay_order() {
        check_ajax_referer('vendor_registration_nonce', 'nonce');

        if (!class_exists('SP_Razorpay_Light_Client')) {
            require_once plugin_dir_path(__FILE__) . 'class-razorpay-light-client.php';
        }

        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Invalid amount for payment.']);
        }

        $client = new SP_Razorpay_Light_Client();
        $receipt_id = 'vendor_reg_' . time();
        
        $order_data = $client->create_order($amount * 100, 'INR', $receipt_id);

        if ($order_data['success']) {
            wp_send_json_success(['order_id' => $order_data['data']['id']]);
        } else {
            wp_send_json_error(['message' => 'Could not create Razorpay order: ' . $order_data['message']]);
        }
    }
}
