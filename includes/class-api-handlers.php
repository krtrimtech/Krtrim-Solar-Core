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
        add_action('wp_ajax_get_area_manager_dashboard_stats', [ $this, 'get_area_manager_dashboard_stats' ]);
        
        // Lead Management
        add_action('wp_ajax_get_area_manager_leads', [ $this, 'get_area_manager_leads' ]);
        add_action('wp_ajax_create_solar_lead', [ $this, 'create_solar_lead' ]);
        add_action('wp_ajax_delete_solar_lead', [ $this, 'delete_solar_lead' ]);
        add_action('wp_ajax_send_lead_message', [ $this, 'send_lead_message' ]);

        // Dashboard Stats
        add_action('wp_ajax_get_area_manager_dashboard_stats', [ $this, 'get_area_manager_dashboard_stats' ]);
        
        // Client Management
        add_action('wp_ajax_get_area_manager_clients', [ $this, 'get_area_manager_clients' ]);
        add_action('wp_ajax_create_client_from_dashboard', [ $this, 'create_client_from_dashboard' ]);
        add_action('wp_ajax_reset_client_password', [ $this, 'reset_client_password' ]);
        
        // Payment Management
        add_action('wp_ajax_record_client_payment', [ $this, 'record_client_payment' ]);
        
        // Vendor Email Verification
        add_action('wp_ajax_verify_vendor_email', [ $this, 'verify_vendor_email' ]);
        add_action('wp_ajax_nopriv_verify_vendor_email', [ $this, 'verify_vendor_email' ]);
        add_action('wp_ajax_resend_verification_email', [ $this, 'resend_verification_email']);
        
        // Vendor Registration - Coverage Areas
        add_action('wp_ajax_get_coverage_areas', [ $this, 'get_coverage_areas' ]);
        add_action('wp_ajax_nopriv_get_coverage_areas', [ $this, 'get_coverage_areas' ]);
    }

    public function get_area_manager_dashboard_stats() {
        // Flexible nonce verification
        $nonce_verified = false;
        if (isset($_POST['nonce'])) {
            $nonce_verified = wp_verify_nonce($_POST['nonce'], 'create_project_nonce') || 
                             wp_verify_nonce($_POST['nonce'], 'dashboard_nonce');
        }
        
        if (!$nonce_verified) {
            // Allow without nonce for now (temporary)
            error_log('Dashboard stats: Nonce verification skipped');
        }

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager = wp_get_current_user();
        
        // Get all projects for this area manager
        $args = [
            'post_type' => 'solar_project',
            'posts_per_page' => -1,
            'author' => $manager->ID
        ];
        $projects = get_posts($args);

        $total_projects = count($projects);
        $completed_projects = 0;
        $in_progress_projects = 0;
        $pending_projects = 0;
        $total_revenue = 0;
        $total_costs = 0;
        $total_profit = 0;
        
        // Client payment tracking
        $total_client_payments = 0;
        $total_outstanding = 0;

        // Monthly data arrays (last 6 months)
        $months = [];
        $monthly_projects = [];
        $monthly_revenue = [];
        $monthly_costs = [];
        $monthly_payments = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $month = date('M', strtotime("-$i months"));
            $months[] = $month;
            $monthly_projects[$month] = 0;
            $monthly_revenue[$month] = 0;
            $monthly_costs[$month] = 0;
            $monthly_payments[$month] = 0;
        }

        foreach ($projects as $project) {
            $status = get_post_meta($project->ID, '_project_status', true);
            $project_cost = floatval(get_post_meta($project->ID, '_total_project_cost', true) ?: 0);
            $vendor_cost = floatval(get_post_meta($project->ID, '_vendor_paid_amount', true) ?: 0);
            $paid_amount = floatval(get_post_meta($project->ID, '_paid_amount', true) ?: 0);
            $profit = floatval(get_post_meta($project->ID, '_company_profit', true) ?: ($project_cost - $vendor_cost));

            // Count by status
            if ($status === 'completed') {
                $completed_projects++;
            } elseif ($status === 'in_progress') {
                $in_progress_projects++;
            } else {
                $pending_projects++;
            }

            // Financial totals
            $total_revenue += $project_cost;
            $total_costs += $vendor_cost;
            $total_profit += $profit;
            
            // Client payment totals
            $total_client_payments += $paid_amount;
            $total_outstanding += ($project_cost - $paid_amount);

            // Monthly breakdown
            $project_month = date('M', strtotime($project->post_date));
            if (isset($monthly_projects[$project_month])) {
                $monthly_projects[$project_month]++;
                $monthly_revenue[$project_month] += $project_cost;
                $monthly_costs[$project_month] += $vendor_cost;
                $monthly_payments[$project_month] += $paid_amount;
            }
        }

        // Calculate profit margin and collection rate
        $profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;
        $collection_rate = $total_revenue > 0 ? ($total_client_payments / $total_revenue) * 100 : 0;

        // Get lead stats
        $lead_args = [
            'post_type' => 'solar_lead',
            'posts_per_page' => -1,
            'author' => $manager->ID
        ];
        $leads = get_posts($lead_args);
        $total_leads = count($leads);
        $converted_leads = 0;
        $pending_leads = 0;
        $lost_leads = 0;

        foreach ($leads as $lead) {
            $lead_status = get_post_meta($lead->ID, '_lead_status', true);
            if ($lead_status === 'converted') {
                $converted_leads++;
            } elseif ($lead_status === 'lost') {
                $lost_leads++;
            } else {
                $pending_leads++;
            }
        }

        $conversion_rate = $total_leads > 0 ? ($converted_leads / $total_leads) * 100 : 0;

        // Count pending reviews (submissions with pending status)
        global $wpdb;
        $pending_reviews = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_author = %d 
            AND p.post_type = 'solar_project'
            AND pm.meta_key = '_admin_status' 
            AND pm.meta_value = 'pending'
        ", $manager->ID));

        wp_send_json_success([
            'total_projects' => $total_projects,
            'total_revenue' => round($total_revenue, 2),
            'total_costs' => round($total_costs, 2),
            'total_profit' => round($total_profit, 2),
            'profit_margin' => round($profit_margin, 2),
            // Client payment stats
            'total_client_payments' => round($total_client_payments, 2),
            'total_outstanding' => round($total_outstanding, 2),
            'collection_rate' => round($collection_rate, 2),
            // Other stats
            'total_leads' => $total_leads,
            'conversion_rate' => round($conversion_rate, 2),
            'pending_reviews' => intval($pending_reviews),
            'project_status' => [
                'pending' => $pending_projects,
                'in_progress' => $in_progress_projects,
                'completed' => $completed_projects
            ],
            'monthly_data' => [
                'labels' => $months,
                'values' => array_values($monthly_projects)
            ],
            'financial_data' => [
                'revenue' => array_values($monthly_revenue),
                'costs' => array_values($monthly_costs),
                'payments' => array_values($monthly_payments)
            ],
            'lead_data' => [
                'converted' => $converted_leads,
                'pending' => $pending_leads,
                'lost' => $lost_leads
            ]
        ]);
    }

    public function get_vendor_earnings_chart_data() {
        check_ajax_referer('get_earnings_chart_data_nonce', 'nonce');

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

    public function get_area_manager_leads() {
        check_ajax_referer('get_leads_nonce', 'nonce');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager_id = get_current_user_id();
        $args = [
            'post_type' => 'solar_lead',
            'posts_per_page' => -1,
            'author' => $manager_id,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $leads_query = new WP_Query($args);
        $leads = [];

        if ($leads_query->have_posts()) {
            while ($leads_query->have_posts()) {
                $leads_query->the_post();
                $leads[] = [
                    'id' => get_the_ID(),
                    'name' => get_the_title(),
                    'email' => get_post_meta(get_the_ID(), '_lead_email', true),
                    'phone' => get_post_meta(get_the_ID(), '_lead_phone', true),
                    'status' => get_post_meta(get_the_ID(), '_lead_status', true) ?: 'new',
                    'notes' => get_the_content(),
                    'date' => get_the_date('Y-m-d'),
                    'created_at' => get_the_date('Y-m-d'),
                ];
            }
        }
        wp_reset_postdata();

        wp_send_json_success(['leads' => $leads]);
    }

    public function create_solar_lead() {
        check_ajax_referer('create_lead_nonce', 'nonce');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes']);

        if (empty($name) || empty($phone)) {
            wp_send_json_error(['message' => 'Name and Phone are required.']);
        }

        $lead_data = [
            'post_title' => $name,
            'post_content' => $notes,
            'post_status' => 'publish',
            'post_type' => 'solar_lead',
            'post_author' => get_current_user_id(),
        ];

        $lead_id = wp_insert_post($lead_data);

        if (is_wp_error($lead_id)) {
            wp_send_json_error(['message' => 'Error creating lead.']);
        }

        update_post_meta($lead_id, '_lead_email', $email);
        update_post_meta($lead_id, '_lead_phone', $phone);
        update_post_meta($lead_id, '_lead_status', $status);

        wp_send_json_success(['message' => 'Lead created successfully!']);
    }

    public function delete_solar_lead() {
        check_ajax_referer('delete_lead_nonce', 'nonce');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $lead_id = intval($_POST['lead_id']);
        $lead = get_post($lead_id);

        if (!$lead || $lead->post_type !== 'solar_lead' || $lead->post_author != get_current_user_id()) {
            wp_send_json_error(['message' => 'Invalid lead or permission denied.']);
        }

        wp_delete_post($lead_id, true);
        wp_send_json_success(['message' => 'Lead deleted successfully.']);
    }

    public function send_lead_message() {
        check_ajax_referer('send_message_nonce', 'nonce');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $lead_id = intval($_POST['lead_id']);
        $type = sanitize_text_field($_POST['type']); // 'email' or 'whatsapp'
        $message_content = sanitize_textarea_field($_POST['message']);

        $lead = get_post($lead_id);
        if (!$lead || $lead->post_type !== 'solar_lead' || $lead->post_author != get_current_user_id()) {
            wp_send_json_error(['message' => 'Invalid lead.']);
        }

        $email = get_post_meta($lead_id, '_lead_email', true);
        $phone = get_post_meta($lead_id, '_lead_phone', true);

        if ($type === 'email') {
            if (empty($email)) {
                wp_send_json_error(['message' => 'Lead does not have an email address.']);
            }
            $subject = 'Message from ' . get_bloginfo('name');
            $sent = wp_mail($email, $subject, $message_content);
            if ($sent) {
                wp_send_json_success(['message' => 'Email sent successfully.']);
            } else {
                wp_send_json_error(['message' => 'Failed to send email.']);
            }
        } elseif ($type === 'whatsapp') {
            if (empty($phone)) {
                wp_send_json_error(['message' => 'Lead does not have a phone number.']);
            }
            // For WhatsApp, we return the URL for the frontend to open
            $whatsapp_url = "https://wa.me/" . preg_replace('/\D/', '', $phone) . "?text=" . urlencode($message_content);
            wp_send_json_success(['message' => 'Opening WhatsApp...', 'whatsapp_url' => $whatsapp_url]);
        } else {
            wp_send_json_error(['message' => 'Invalid message type.']);
        }
    }

    public function create_client_from_dashboard() {
        check_ajax_referer('create_client_nonce', 'nonce');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager_id = get_current_user_id();
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $name = sanitize_text_field($_POST['name']);

        if (empty($username) || empty($email) || empty($password) || empty($name)) {
            wp_send_json_error(['message' => 'All fields are required.']);
        }

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
        
        // Split name into first and last
        $name_parts = explode(' ', $name, 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $first_name,
            'last_name' => $last_name
        ]);

        // Link to Area Manager
        update_user_meta($user_id, '_created_by_area_manager', get_current_user_id());

        wp_send_json_success(['message' => 'Client account created successfully.']);
    }

    public function create_vendor_from_dashboard() {
        check_ajax_referer('create_vendor_nonce', 'nonce');

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
        check_ajax_referer('get_vendor_approvals_nonce', 'nonce');

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
        check_ajax_referer('get_reviews_nonce', 'nonce');

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
        // Clean any previous output
        if (ob_get_length()) {
            ob_clean();
        }
        
        try {
            error_log('Marketplace filter called with data: ' . print_r($_POST, true));
            
            $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
            $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';

            $args = [
                'post_type' => 'solar_project',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_vendor_assignment_method',
                        'value' => 'bidding',
                        'compare' => '='
                    ]
                ]
            ];

            // Add state filter if provided
            if (!empty($state)) {
                $args['meta_query'][] = [
                    'key' => '_project_state',
                    'value' => $state,
                    'compare' => '='
                ];
            }

            // Add city filter if provided
            if (!empty($city)) {
                $args['meta_query'][] = [
                    'key' => '_project_city',
                    'value' => $city,
                    'compare' => '='
                ];
            }

            $query = new WP_Query($args);
            
            error_log('Found ' . $query->found_posts . ' projects');

            if ($query->have_posts()) {
            ob_start();
            while ($query->have_posts()) {
                $query->the_post();
                $project_id = get_the_ID();
                
                // Get all meta data with defaults
                $state_val = get_post_meta($project_id, '_project_state', true);
                $city_val = get_post_meta($project_id, '_project_city', true);
                $system_size = get_post_meta($project_id, '_solar_system_size_kw', true);
                $location = trim($city_val . ', ' . $state_val, ', ');
                
                // Get featured image or default
                $thumbnail_url = get_the_post_thumbnail_url($project_id, 'medium');
                if (!$thumbnail_url) {
                    // Get default image from settings
                    $default_image = get_option('ksc_default_project_image', '');
                    $thumbnail_url = $default_image ?: 'https://via.placeholder.com/400x250/667eea/ffffff?text=Solar+Project';
                }
                ?>
                <div class="project-card">
                    <div class="project-card-image">
                        <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
                        <div class="project-card-badge">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="5"></circle>
                                <line x1="12" y1="1" x2="12" y2="3"></line>
                                <line x1="12" y1="21" x2="12" y2="23"></line>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                <line x1="1" y1="12" x2="3" y2="12"></line>
                                <line x1="21" y1="12" x2="23" y2="12"></line>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                            </svg>
                            Open for Bids
                        </div>
                    </div>
                    <div class="project-card-content">
                        <h3 class="project-card-title"><?php echo esc_html(get_the_title()); ?></h3>
                        
                        <div class="project-card-details">
                            <div class="project-detail-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <span><?php echo esc_html($location ?: 'Location not specified'); ?></span>
                            </div>
                            
                            <?php if ($system_size): ?>
                            <div class="project-detail-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="9" y1="9" x2="15" y2="15"></line>
                                    <line x1="15" y1="9" x2="9" y2="15"></line>
                                </svg>
                                <span><?php echo esc_html($system_size); ?> kW System</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <a href="<?php echo esc_url(get_permalink()); ?>" class="project-card-btn">
                            View Details & Bid
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                    </div>
                </div>
                <?php
            }
            $html = ob_get_clean();
            wp_reset_postdata();
            
            // Add CSS for beautiful cards
            $html .= '<style>
                .project-card {
                    background: #ffffff;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
                    transition: all 0.3s ease;
                    margin-bottom: 24px;
                }
                .project-card:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 12px 24px rgba(102, 126, 234, 0.15);
                }
                .project-card-image {
                    position: relative;
                    height: 200px;
                    overflow: hidden;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                }
                .project-card-image img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    transition: transform 0.3s ease;
                }
                .project-card:hover .project-card-image img {
                    transform: scale(1.05);
                }
                .project-card-badge {
                    position: absolute;
                    top: 12px;
                    right: 12px;
                    background: rgba(255, 255, 255, 0.95);
                    color: #667eea;
                    padding: 6px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                }
                .project-card-content {
                    padding: 20px;
                }
                .project-card-title {
                    font-size: 18px;
                    font-weight: 700;
                    color: #1a202c;
                    margin: 0 0 16px 0;
                    line-height: 1.4;
                }
                .project-card-details {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                    margin-bottom: 20px;
                }
                .project-detail-item {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    color: #4a5568;
                    font-size: 14px;
                }
                .project-detail-item svg {
                    color: #667eea;
                    flex-shrink: 0;
                }
                .project-card-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: #ffffff;
                    padding: 12px 24px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 14px;
                    transition: all 0.3s ease;
                    width: 100%;
                    justify-content: center;
                }
                .project-card-btn:hover {
                    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
                    transform: translateX(2px);
                    color: #ffffff;
                }
                .project-card-btn svg {
                    transition: transform 0.3s ease;
                }
                .project-card-btn:hover svg {
                    transform: translateX(4px);
                }
                
                /* Grid layout for cards */
                #project-listings-container {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                    gap: 24px;
                    padding: 20px 0;
                }
                
                @media (max-width: 768px) {
                    #project-listings-container {
                        grid-template-columns: 1fr;
                    }
                }
            </style>';
            
            wp_send_json_success(['html' => $html, 'count' => $query->found_posts]);
        } else {
            wp_send_json_success(['html' => '', 'count' => 0]);
        }
        } catch (Exception $e) {
            error_log('Marketplace error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Server error: ' . $e->getMessage()]);
        }
        
        wp_die(); // Always end AJAX handlers with wp_die()
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

    public function get_area_manager_clients() {
        check_ajax_referer('get_clients_nonce', 'nonce');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $manager_id = get_current_user_id();
        $args = [
            'role' => 'solar_client',
            'meta_key' => '_created_by_area_manager',
            'meta_value' => $manager_id,
            'fields' => ['ID', 'display_name', 'user_email', 'user_login'],
        ];

        $clients = get_users($args);
        $data = [];

        foreach ($clients as $client) {
            $data[] = [
                'id' => $client->ID,
                'name' => $client->display_name,
                'email' => $client->user_email,
                'username' => $client->user_login,
            ];
        }

        wp_send_json_success(['clients' => $data]);
    }

    public function reset_client_password() {
        check_ajax_referer('reset_password_nonce', 'nonce');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $client_id = intval($_POST['client_id']);
        $new_password = $_POST['new_password'];

        if (empty($client_id) || empty($new_password)) {
            wp_send_json_error(['message' => 'Invalid data.']);
        }

        // Verify client belongs to this area manager
        $created_by = get_user_meta($client_id, '_created_by_area_manager', true);
        if ($created_by != get_current_user_id()) {
            wp_send_json_error(['message' => 'You do not have permission to manage this client.']);
        }

        wp_set_password($new_password, $client_id);

        wp_send_json_success(['message' => 'Password reset successfully.']);
    }

    /**
     * Record a client payment for a project
     */
    public function record_client_payment() {
        check_ajax_referer('record_payment_nonce', 'nonce');

        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
        $payment_note = isset($_POST['payment_note']) ? sanitize_textarea_field($_POST['payment_note']) : '';

        if (empty($project_id) || $payment_amount <= 0) {
            wp_send_json_error(['message' => 'Invalid project or payment amount.']);
        }

        // Verify project belongs to this area manager
        $project = get_post($project_id);
        if (!$project || $project->post_type !== 'solar_project' || $project->post_author != get_current_user_id()) {
            wp_send_json_error(['message' => 'You do not have permission to manage this project.']);
        }

        // Get current paid amount and total cost
        $current_paid = floatval(get_post_meta($project_id, '_paid_amount', true) ?: 0);
        $total_cost = floatval(get_post_meta($project_id, '_total_project_cost', true) ?: 0);

        // Add new payment to current total
        $new_paid_amount = $current_paid + $payment_amount;

        // Check if payment exceeds total cost
        if ($new_paid_amount > $total_cost) {
            wp_send_json_error(['message' => 'Payment amount exceeds total project cost. Remaining balance: ₹' . number_format($total_cost - $current_paid, 2)]);
        }

        // Update paid amount
        update_post_meta($project_id, '_paid_amount', $new_paid_amount);

        // Optionally store payment history in a log (you can enhance this later)
        // For now, we'll just update the meta

        $balance = $total_cost - $new_paid_amount;

        wp_send_json_success([
            'message' => 'Payment recorded successfully!',
            'paid_amount' => $new_paid_amount,
            'balance' => $balance,
            'total_cost' => $total_cost
        ]);
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

        // ✅ NOTIFY CLIENT - Vendor Assigned
        $client_id = get_post_meta($project_id, '_client_user_id', true);
        if ($client_id) {
            $vendor_name = get_user_meta($vendor_id, 'company_name', true);
            if (empty($vendor_name)) {
                $vendor_name = $winning_vendor->display_name;
            }
            SP_Notifications_Manager::create_notification([
                'user_id' => $client_id,
                'project_id' => $project_id,
                'message' => sprintf('Vendor "%s" has been assigned to your project', $vendor_name),
                'type' => 'vendor_assigned',
            ]);
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

        $result = SP_Process_Steps_Manager::process_step_review($step_id, $decision, $comment, $manager->ID);

        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Submission status updated successfully.',
                'whatsapp_data' => $result['whatsapp_data']
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
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
            'post_content' => isset($data['project_description']) ? wp_kses_post($data['project_description']) : '',
            'post_status'  => 'publish',
            'post_author'  => $manager->ID,
            'post_type'    => 'solar_project',
        ];
        $project_id = wp_insert_post($project_data);

        if (is_wp_error($project_id)) {
            wp_send_json_error(['message' => 'Could not create project: ' . $project_id->get_error_message()]);
        }

        if (isset($data['vendor_assignment_method'])) {
            $method = sanitize_text_field($data['vendor_assignment_method']);
            update_post_meta($project_id, '_vendor_assignment_method', $method);

            if ($method === 'manual') {
                if (isset($data['assigned_vendor_id'])) {
                    update_post_meta($project_id, '_assigned_vendor_id', sanitize_text_field($data['assigned_vendor_id']));
                }
                if (isset($data['paid_to_vendor'])) {
                    update_post_meta($project_id, '_paid_to_vendor', sanitize_text_field($data['paid_to_vendor']));
                }
                update_post_meta($project_id, '_project_status', 'assigned');
                wp_update_post(['ID' => $project_id, 'post_status' => 'assigned']);
            } else {
                // Bidding mode
                delete_post_meta($project_id, '_assigned_vendor_id');
                delete_post_meta($project_id, '_paid_to_vendor');
            }
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
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                update_post_meta($project_id,  '_' . $field, sanitize_text_field($data[$field]));
            }
        }

        // Save financial meta field (total project cost only)
        $total_project_cost = isset($data['total_project_cost']) ? floatval($data['total_project_cost']) : 0;
        update_post_meta($project_id, '_total_project_cost', $total_project_cost);
        
        // Vendor payment comes from bid/manual assignment, so we'll calculate profit later
        // For now, set vendor_paid_amount based on manual assignment if provided
        if (isset($data['paid_to_vendor']) && !empty($data['paid_to_vendor'])) {
            $vendor_paid_amount = floatval($data['paid_to_vendor']);
            update_post_meta($project_id, '_vendor_paid_amount', $vendor_paid_amount);
            
            // Calculate profit
            $company_profit = $total_project_cost - $vendor_paid_amount;
            $profit_margin = $total_project_cost > 0 ? ($company_profit / $total_project_cost) * 100 : 0;
            update_post_meta($project_id, '_company_profit', $company_profit);
            update_post_meta($project_id, '_profit_margin_percentage', $profit_margin);
        }


        // ✅ NOTIFY CLIENT - Project Created
        $client_id = isset($data['client_user_id']) ? sanitize_text_field($data['client_user_id']) : '';
        if ($client_id) {
            SP_Notifications_Manager::create_notification([
                'user_id' => $client_id,
                'project_id' => $project_id,
                'message' => sprintf('Your solar project "%s" has been created', $project_title),
                'type' => 'project_created',
            ]);
        }

        wp_send_json_success(['message' => 'Project created successfully!', 'project_id' => $project_id]);
    }

    public function get_area_manager_projects() {
        check_ajax_referer('get_projects_nonce', 'nonce');

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
                    'project_state' => get_post_meta($project_id, '_project_state', true),
                    'project_city' => get_post_meta($project_id, '_project_city', true),
                    'solar_system_size_kw' => get_post_meta($project_id, '_solar_system_size_kw', true),
                   'total_cost' => get_post_meta($project_id, '_total_project_cost', true),
                    'start_date' => get_post_meta($project_id, '_project_start_date', true),
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
        
        // Generate email verification token
        $token = wp_generate_password(32, false);
        update_user_meta($user_id, 'email_verification_token', $token);
        update_user_meta($user_id, 'email_verification_sent_date', current_time('mysql'));
        
        // Create verification URL
        $verify_url = add_query_arg([
            'action' => 'verify_vendor_email',
            'token' => $token,
            'user' => $user_id
        ], home_url('/'));
        
        // Send verification email
        $subject = 'Verify Your Email - Solar Vendor Registration';
        $message = sprintf(
            "Welcome to our Solar Vendor Platform!\n\n" .
            "Thank you for registering. Please verify your email address by clicking the link below:\n\n" .
            "%s\n\n" .
            "Once your email is verified, your account will be automatically approved and you can start bidding on projects.\n\n" .
            "If you didn't register for this account, please ignore this email.\n\n" .
            "Best regards,\n" .
            "Solar Dashboard Team",
            $verify_url
        );
        
        wp_mail($basic_info['email'], $subject, $message);
        
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
        
        // Check for auto-approval (in case email was somehow already verified)
        $this->check_auto_approval($user_id);
        
        wp_send_json_success(['message' => 'Registration completed! Please check your email to verify your account and get instant approval.']);
    }

    public function update_vendor_status() {
        check_ajax_referer('sp_vendor_approval_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';

        if (empty($user_id) || !in_array($status, ['yes', 'no', 'denied'])) {
            wp_send_json_error(['message' => 'Invalid data provided.']);
        }

        // Update approval status
        update_user_meta($user_id, 'account_approved', $status);

        if ($status === 'yes') {
            // ✅ MANUAL APPROVAL - BYPASSES all checks
            update_user_meta($user_id, 'account_approved_date', current_time('mysql'));
            update_user_meta($user_id, 'account_approved_by', get_current_user_id());
            update_user_meta($user_id, 'approval_method', 'manual');
            
            if (!empty($reason)) {
                update_user_meta($user_id, 'manual_approval_reason', $reason);
            }
            
            // Trigger approval hook (sends notifications)
            do_action('sp_vendor_approved', $user_id);
            
            $message = 'Vendor manually approved successfully.';
        } elseif ($status === 'denied') {
            update_user_meta($user_id, 'account_denied_date', current_time('mysql'));
            update_user_meta($user_id, 'account_denied_by', get_current_user_id());
            
            if (!empty($reason)) {
                update_user_meta($user_id, 'denial_reason', $reason);
            }
            
            // ✅ VENDOR REJECTION NOTIFICATIONS
            $vendor = get_userdata($user_id);
            $notification_options = get_option('sp_notification_options');
            
            // In-app notification
            SP_Notifications_Manager::create_notification([
                'user_id' => $user_id,
                'message' => 'Your vendor application has been denied.',
                'type' => 'vendor_denied',
            ]);
            
            // Email notification (if enabled)
            if (isset($notification_options['email_vendor_rejected']) && $notification_options['email_vendor_rejected'] === '1') {
                $email_subject = 'Vendor Application Update';
                $email_message = "Your vendor application has been reviewed.\n\n";
                $email_message .= "Unfortunately, we are unable to approve your application at this time.\n\n";
                if (!empty($reason)) {
                    $email_message .= "Reason: " . $reason . "\n\n";
                }
                $email_message .= "If you have questions, please contact support.";
                
                wp_mail($vendor->user_email, $email_subject, $email_message);
            }
            
            $message = 'Vendor denied.';
        } else {
            $message = 'Vendor status updated.';
        }

        wp_send_json_success(['message' => $message]);
    }
    
    /**
     * Get Coverage Areas (States/Cities) for Vendor Registration
     */
    public function get_coverage_areas() {
        $json_file = plugin_dir_path(__FILE__) . '../assets/data/indian-states-cities.json';
        
        if (!file_exists($json_file)) {
            wp_send_json_error(['message' => 'Coverage data not found']);
            return;
        }
        
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['states'])) {
            wp_send_json_error(['message' => 'Invalid coverage data']);
            return;
        }
        
        wp_send_json_success($data['states']);
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

    /**
     * Check if vendor meets criteria for auto-approval
     * Triggers when both payment is completed AND email is verified
     */
    private function check_auto_approval($user_id) {
        // Already approved? Skip
        $current_status = get_user_meta($user_id, 'account_approved', true);
        if ($current_status === 'yes') {
            return;
        }
        
        // Check both conditions
        $payment_complete = get_user_meta($user_id, 'vendor_payment_status', true) === 'completed';
        $email_verified = get_user_meta($user_id, 'email_verified', true) === 'yes';
        
        if ($payment_complete && $email_verified) {
            // ✅ AUTO-APPROVE
            update_user_meta($user_id, 'account_approved', 'yes');
            update_user_meta($user_id, 'account_approved_date', current_time('mysql'));
            update_user_meta($user_id, 'account_approved_by', 'auto');
            update_user_meta($user_id, 'approval_method', 'auto');
            
            // Trigger approval action (sends notifications)
            do_action('sp_vendor_approved', $user_id);
            
            error_log("Vendor $user_id auto-approved after email verification");
        }
    }

    /**
     * Handle email verification link clicks
     * URL: /?action=verify_vendor_email&token=xxx&user=123
     */
    public function verify_vendor_email() {
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
        
        if (empty($token) || empty($user_id)) {
            wp_die('Invalid verification link.');
        }
        
        // Validate token
        $stored_token = get_user_meta($user_id, 'email_verification_token', true);
        
        if ($token !== $stored_token) {
            wp_die('Invalid or expired verification token. Please request a new verification email.');
        }
        
        // Check if already verified
        $already_verified = get_user_meta($user_id, 'email_verified', true) === 'yes';
        
        if ($already_verified) {
            wp_die('Your email is already verified. You can now <a href="' . wp_login_url() . '">login to your account</a>.');
        }
        
        // Mark email as verified
        update_user_meta($user_id, 'email_verified', 'yes');
        update_user_meta($user_id, 'email_verified_date', current_time('mysql'));
        
        // Delete token (one-time use)
        delete_user_meta($user_id, 'email_verification_token');
        
        // ✅ CHECK FOR AUTO-APPROVAL
        $this->check_auto_approval($user_id);
        
        // Check if they got approved
        $is_approved = get_user_meta($user_id, 'account_approved', true) === 'yes';
        
        if ($is_approved) {
            $message = 'Email verified successfully! Your account has been automatically approved. You can now <a href="' . wp_login_url() . '">login</a> and start bidding on projects.';
        } else {
            $message = 'Email verified successfully! Your account is pending payment verification. You will be automatically approved once payment is confirmed.';
        }
        
        wp_die($message);
    }

    /**
     * Resend verification email to vendor
     */
    public function resend_verification_email() {
        check_ajax_referer('resend_email_nonce', 'nonce');
        
        $user = wp_get_current_user();
        
        if (!in_array('solar_vendor', $user->roles)) {
            wp_send_json_error(['message' => 'Not authorized']);
        }
        
        $email_verified = get_user_meta($user->ID, 'email_verified', true);
        if ($email_verified === 'yes') {
            wp_send_json_error(['message' => 'Your email is already verified']);
        }
        
        // Generate new token
        $token = wp_generate_password(32, false);
        update_user_meta($user->ID, 'email_verification_token', $token);
        update_user_meta($user->ID, 'email_verification_sent_date', current_time('mysql'));
        
        // Create verification URL
        $verify_url = add_query_arg([
            'action' => 'verify_vendor_email',
            'token' => $token,
            'user' => $user->ID
        ], home_url('/'));
        
        // Send email
        $subject = 'Verify Your Email - Solar Vendor Registration';
        $message = sprintf(
            "Please verify your email address by clicking the link below:\n\n" .
            "%s\n\n" .
            "Once verified, your account will be automatically approved.\n\n" .
            "Best regards,\n" .
            "Solar Dashboard Team",
            $verify_url
        );
        
        $sent = wp_mail($user->user_email, $subject, $message);
        
        if ($sent) {
            wp_send_json_success(['message' => 'Verification email sent! Please check your inbox.']);
        } else {
            wp_send_json_error(['message' => 'Failed to send email. Please try again later.']);
        }
    }
}
