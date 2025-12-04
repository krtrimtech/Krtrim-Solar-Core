<?php
/**
 * Handles registration of Custom Post Types and Taxonomies.
 */
class SP_Post_Types_Taxonomies {

    /**
     * Constructor. Hooks into WordPress.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_post_types' ] );
        add_action( 'init', [ $this, 'register_solar_lead_cpt' ] );
        add_action( 'init', [ $this, 'register_taxonomies' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_bids_meta_box' ] );

        // Admin Columns & Filters
        add_filter( 'manage_solar_project_posts_columns', [ $this, 'add_custom_columns' ] );
        add_action( 'manage_solar_project_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );
        add_action( 'restrict_manage_posts', [ $this, 'add_status_filter' ] );
        add_filter( 'parse_query', [ $this, 'filter_projects_by_status' ] );
    }

    /**
     * Register Custom Post Types.
     */
    public function register_post_types() {
        $labels = [
            'name'                  => _x( 'Solar Projects', 'Post Type General Name', 'unified-solar-dashboard' ),
            'singular_name'         => _x( 'Solar Project', 'Post Type Singular Name', 'unified-solar-dashboard' ),
            'menu_name'             => __( 'Solar Projects', 'unified-solar-dashboard' ),
            'all_items'             => __( 'All Projects', 'unified-solar-dashboard' ),
            'add_new_item'          => __( 'Add New Project', 'unified-solar-dashboard' ),
            'add_new'               => __( 'Add New', 'unified-solar-dashboard' ),
            'new_item'              => __( 'New Project', 'unified-solar-dashboard' ),
            'edit_item'             => __( 'Edit Project', 'unified-solar-dashboard' ),
            'view_item'             => __( 'View Project', 'unified-solar-dashboard' ),
            'search_items'          => __( 'Search Project', 'unified-solar-dashboard' ),
            'not_found'             => __( 'Not found', 'unified-solar-dashboard' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'unified-solar-dashboard' ),
        ];
        $args = [
            'label'                 => __( 'Solar Project', 'unified-solar-dashboard' ),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'thumbnail', 'custom-fields', 'author'],
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-admin-generic',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => 'projects',
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
        ];
        register_post_type( 'solar_project', $args );
    }

    /**
     * Register Solar Lead Custom Post Type.
     */
    public function register_solar_lead_cpt() {
        $labels = [
            'name'                  => _x( 'Solar Leads', 'Post Type General Name', 'unified-solar-dashboard' ),
            'singular_name'         => _x( 'Solar Lead', 'Post Type Singular Name', 'unified-solar-dashboard' ),
            'menu_name'             => __( 'Solar Leads', 'unified-solar-dashboard' ),
            'all_items'             => __( 'All Leads', 'unified-solar-dashboard' ),
            'add_new_item'          => __( 'Add New Lead', 'unified-solar-dashboard' ),
            'add_new'               => __( 'Add New', 'unified-solar-dashboard' ),
            'new_item'              => __( 'New Lead', 'unified-solar-dashboard' ),
            'edit_item'             => __( 'Edit Lead', 'unified-solar-dashboard' ),
            'view_item'             => __( 'View Lead', 'unified-solar-dashboard' ),
            'search_items'          => __( 'Search Leads', 'unified-solar-dashboard' ),
            'not_found'             => __( 'No leads found', 'unified-solar-dashboard' ),
            'not_found_in_trash'    => __( 'No leads found in Trash', 'unified-solar-dashboard' ),
        ];
        $args = [
            'label'                 => __( 'Solar Lead', 'unified-solar-dashboard' ),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'custom-fields', 'author'], // Title = Name, Editor = Notes
            'hierarchical'          => false,
            'public'                => false, // Not public facing
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 6,
            'menu_icon'             => 'dashicons-groups',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
        ];
        register_post_type( 'solar_lead', $args );
    }

    /**
     * Register Custom Taxonomies.
     */
    public function register_taxonomies() {
        // Project City Taxonomy
        $city_labels = [
            'name'              => _x( 'Project Cities', 'taxonomy general name', 'unified-solar-dashboard' ),
            'singular_name'     => _x( 'Project City', 'taxonomy singular name', 'unified-solar-dashboard' ),
            'menu_name'         => __( 'Project Cities', 'unified-solar-dashboard' ),
        ];
        $city_args = [
            'hierarchical'      => true,
            'labels'            => $city_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'project-city'],
            'show_in_rest'      => true,
        ];
        register_taxonomy( 'project_city', ['solar_project'], $city_args );

        // Vendor State Taxonomy
        $state_labels = [
            'name'              => _x( 'Vendor States', 'taxonomy general name', 'unified-solar-dashboard' ),
            'singular_name'     => _x( 'Vendor State', 'taxonomy singular name', 'unified-solar-dashboard' ),
            'menu_name'         => __( 'Coverage Areas', 'unified-solar-dashboard' ),
        ];
        $state_args = [
            'hierarchical'      => false,
            'labels'            => $state_labels,
            'show_ui'           => true,
            'show_admin_column' => false,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'vendor-state'],
            'show_in_rest'      => true,
        ];
        register_taxonomy( 'vendor_state', null, $state_args );

        // Vendor City Taxonomy
        $vendor_city_labels = [
            'name'              => _x( 'Vendor Cities', 'taxonomy general name', 'unified-solar-dashboard' ),
            'singular_name'     => _x( 'Vendor City', 'taxonomy singular name', 'unified-solar-dashboard' ),
        ];
        $vendor_city_args = [
            'hierarchical'      => true,
            'labels'            => $vendor_city_labels,
            'show_ui'           => true,
            'show_admin_column' => false,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'vendor-city'],
            'show_in_rest'      => true,
        ];
        register_taxonomy( 'vendor_city', null, $vendor_city_args );
    }

    /**
     * Add Meta Box for Bids.
     */
    public function add_bids_meta_box() {
        add_meta_box(
            'sp_project_bids',
            'Project Bids',
            [ $this, 'display_bids_meta_box' ],
            'solar_project',
            'normal',
            'high'
        );
    }

    /**
     * Display the content of the Bids Meta Box.
     */
    public function display_bids_meta_box( $post ) {
        global $wpdb;
        $bids_table = $wpdb->prefix . 'project_bids';
        $bids = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, u.display_name FROM {$bids_table} b JOIN {$wpdb->users} u ON b.vendor_id = u.ID WHERE b.project_id = %d ORDER BY b.bid_amount ASC",
            $post->ID
        ));

        echo '<table class="widefat fixed" cellspacing="0" id="bids-meta-box-table">';
        echo '<thead><tr><th>Vendor</th><th>Bid Amount</th><th>Type</th><th>Details</th><th>Date</th><th>Action</th></tr></thead>';
        echo '<tbody>';

        if ($bids) {
            foreach ($bids as $bid) {
                $awarded_vendor_id = get_post_meta($post->ID, '_vendor_user_id', true);
                $is_awarded = ($awarded_vendor_id == $bid->vendor_id);
                $row_class = $is_awarded ? 'awarded-bid' : '';
                
                // Determine bid type styling
                $bid_type_class = ($bid->bid_type === 'open') ? 'bid-type-open' : 'bid-type-hidden';
                $bid_type_label = ($bid->bid_type === 'open') ? '👁️ Open' : '🔒 Hidden';
                
                echo '<tr class="' . $row_class . '">';
                echo '<td>' . esc_html($bid->display_name) . '</td>';
                echo '<td>₹' . number_format($bid->bid_amount) . '</td>';
                echo '<td><span class="' . $bid_type_class . '">' . $bid_type_label . '</span></td>';
                echo '<td>' . esc_html($bid->bid_details) . '</td>';
                echo '<td>' . esc_html(date('M j, Y', strtotime($bid->created_at))) . '</td>';
                echo '<td>';
                if ($is_awarded) {
                    echo '<span class="awarded-badge">✓ Awarded</span>';
                } else {
                    echo '<button type="button" class="button button-primary award-bid-btn" data-project-id="' . $post->ID . '" data-vendor-id="' . $bid->vendor_id . '" data-bid-amount="' . $bid->bid_amount . '">Award Project</button>';
                }
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6">No bids have been placed on this project yet.</td></tr>';
        }

        echo '</tbody></table>';
        echo '<style>
            .awarded-bid { background-color: #d4edda; }
            .bid-type-open { 
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                background-color: #d4edda;
                color: #155724;
                font-size: 12px;
                font-weight: 600;
            }
            .bid-type-hidden { 
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                background-color: #fff3cd;
                color: #856404;
                font-size: 12px;
                font-weight: 600;
            }
            .awarded-badge {
                color: #155724;
                font-weight: 600;
            }
        </style>';
        wp_nonce_field('award_bid_nonce', 'award_bid_nonce_field');
    }

    /**
     * Add custom columns to Solar Project list.
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['project_status'] = 'Status';
                $new_columns['assigned_vendor'] = 'Assigned Vendor';
                $new_columns['client_name'] = 'Client';
                $new_columns['project_cost'] = 'Total Cost';
            }
        }
        return $new_columns;
    }

    /**
     * Populate custom columns for Solar Project list.
     */
    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'project_status':
                $status = get_post_meta($post_id, 'project_status', true);
                if ($status) {
                    $colors = [
                        'pending' => '#ffc107', // Yellow
                        'assigned' => '#17a2b8', // Cyan
                        'in_progress' => '#007bff', // Blue
                        'completed' => '#28a745', // Green
                        'on_hold' => '#6c757d', // Grey
                        'cancelled' => '#dc3545' // Red
                    ];
                    $color = isset($colors[$status]) ? $colors[$status] : '#6c757d';
                    echo sprintf('<span style="background: %s; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">%s</span>', $color, ucfirst(str_replace('_', ' ', $status)));
                } else {
                    echo '<span style="color: #999;">-</span>';
                }
                break;

            case 'assigned_vendor':
                $vendor_id = get_post_meta($post_id, '_assigned_vendor_id', true);
                if ($vendor_id) {
                    $vendor = get_userdata($vendor_id);
                    echo $vendor ? esc_html($vendor->display_name) : '<span style="color: #999;">Unknown</span>';
                } else {
                    echo '<span style="color: #999;">Unassigned</span>';
                }
                break;

            case 'client_name':
                $client_id = get_post_meta($post_id, '_client_user_id', true);
                if ($client_id) {
                    $client = get_userdata($client_id);
                    echo $client ? esc_html($client->display_name) : '<span style="color: #999;">Unknown</span>';
                } else {
                    echo '<span style="color: #999;">-</span>';
                }
                break;

            case 'project_cost':
                $cost = get_post_meta($post_id, '_total_project_cost', true);
                echo $cost ? '₹' . number_format($cost) : '<span style="color: #999;">-</span>';
                break;
        }
    }

    /**
     * Add "Filter by Status" dropdown to Solar Project list.
     */
    public function add_status_filter($post_type) {
        if ($post_type !== 'solar_project') {
            return;
        }

        $current_status = isset($_GET['project_status_filter']) ? $_GET['project_status_filter'] : '';
        $statuses = [
            'pending' => 'Pending',
            'assigned' => 'Assigned',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'on_hold' => 'On Hold',
            'cancelled' => 'Cancelled'
        ];

        echo '<select name="project_status_filter">';
        echo '<option value="">All Statuses</option>';
        foreach ($statuses as $key => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                $key,
                selected($current_status, $key, false),
                $label
            );
        }
        echo '</select>';
    }

    /**
     * Handle the status filter query.
     */
    public function filter_projects_by_status($query) {
        global $pagenow;
        if ($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'solar_project' && isset($_GET['project_status_filter']) && $_GET['project_status_filter'] !== '') {
            $query->set('meta_key', 'project_status');
            $query->set('meta_value', $_GET['project_status_filter']);
        }
    }
}
