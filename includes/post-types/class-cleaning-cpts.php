<?php
/**
 * Cleaning Service Custom Post Types
 * 
 * Registers CPTs for cleaning_service (subscriptions), cleaning_visit (individual visits), 
 * and service_review (reviews/complaints).
 * 
 * @package Krtrim_Solar_Core
 * @since 1.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Cleaning_CPTs {

    /**
     * Initialize hooks
     */
    public function __construct() {
        add_action('init', [$this, 'register_cleaning_service_cpt']);
        add_action('init', [$this, 'register_cleaning_visit_cpt']);
        add_action('init', [$this, 'register_service_review_cpt']);
        
        // Admin Columns
        add_filter('manage_cleaning_service_posts_columns', [$this, 'add_cleaning_service_columns']);
        add_action('manage_cleaning_service_posts_custom_column', [$this, 'render_cleaning_service_columns'], 10, 2);
    }

    /**
     * Register Cleaning Service CPT (Subscriptions/Bookings)
     */
    public function register_cleaning_service_cpt() {
        $labels = [
            'name'               => 'Cleaning Orders',
            'singular_name'      => 'Cleaning Order',
            'menu_name'          => 'Cleaning Orders',
            'add_new'            => 'Manual Order',
            'add_new_item'       => 'Add New Cleaning Order',
            'edit_item'          => 'Edit Order',
            'new_item'           => 'New Order',
            'view_item'          => 'View Order',
            'search_items'       => 'Search Orders',
            'not_found'          => 'No orders found',
            'not_found_in_trash' => 'No orders in trash',
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-cart',
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => ['title'],
            'has_archive'         => false,
            'rewrite'             => false,
        ];

        register_post_type('cleaning_service', $args);

        // Register custom meta fields
        register_post_meta('cleaning_service', '_customer_type', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'existing_client or external',
        ]);
        register_post_meta('cleaning_service', '_client_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'WordPress user ID if existing client',
        ]);
        register_post_meta('cleaning_service', '_customer_name', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_service', '_customer_phone', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_service', '_customer_address', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_service', '_system_size_kw', [
            'type'         => 'number',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_service', '_plan_type', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'one_time, monthly, 6_month, yearly',
        ]);
        register_post_meta('cleaning_service', '_visits_total', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_service', '_visits_used', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_service', '_payment_status', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'pending, paid, failed',
        ]);
        register_post_meta('cleaning_service', '_razorpay_payment_id', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_service', '_total_amount', [
            'type'         => 'number',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_service', '_created_by', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'User ID who created this (AM/SM or 0 for online booking)',
        ]);
        register_post_meta('cleaning_service', '_assigned_area_manager', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ]);
    }

    /**
     * Register Cleaning Visit CPT (Individual scheduled visits)
     */
    public function register_cleaning_visit_cpt() {
        $labels = [
            'name'               => 'Cleaning Visits',
            'singular_name'      => 'Cleaning Visit',
            'menu_name'          => 'Cleaning Visits',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Visit',
            'edit_item'          => 'Edit Visit',
            'new_item'           => 'New Visit',
            'view_item'          => 'View Visit',
            'search_items'       => 'Search Visits',
            'not_found'          => 'No visits found',
            'not_found_in_trash' => 'No visits in trash',
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'ksc-settings',
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => ['title'],
            'has_archive'         => false,
            'rewrite'             => false,
        ];

        register_post_type('cleaning_visit', $args);

        // Register custom meta fields
        register_post_meta('cleaning_visit', '_service_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'Parent cleaning_service post ID',
        ]);
        register_post_meta('cleaning_visit', '_cleaner_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'Assigned cleaner user ID',
        ]);
        register_post_meta('cleaning_visit', '_scheduled_date', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_visit', '_scheduled_time', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_visit', '_status', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'scheduled, in_progress, completed, cancelled',
        ]);
        register_post_meta('cleaning_visit', '_completion_photo', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_visit', '_completion_notes', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_visit', '_completed_at', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('cleaning_visit', '_notification_sent', [
            'type'         => 'boolean',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'Whether pre-service notification was sent',
        ]);
    }

    /**
     * Register Service Review CPT (Reviews and Complaints)
     */
    public function register_service_review_cpt() {
        $labels = [
            'name'               => 'Service Reviews',
            'singular_name'      => 'Service Review',
            'menu_name'          => 'Service Reviews',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Review',
            'edit_item'          => 'Edit Review',
            'new_item'           => 'New Review',
            'view_item'          => 'View Review',
            'search_items'       => 'Search Reviews',
            'not_found'          => 'No reviews found',
            'not_found_in_trash' => 'No reviews in trash',
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'ksc-settings',
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => ['title', 'editor'],
            'has_archive'         => false,
            'rewrite'             => false,
        ];

        register_post_type('service_review', $args);

        // Register custom meta fields
        register_post_meta('service_review', '_service_type', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'project or cleaning',
        ]);
        register_post_meta('service_review', '_service_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'Project ID or Cleaning Visit ID',
        ]);
        register_post_meta('service_review', '_reviewer_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'Client user ID',
        ]);
        register_post_meta('service_review', '_rating', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => '1-5 star rating',
        ]);
        register_post_meta('service_review', '_is_complaint', [
            'type'         => 'boolean',
            'single'       => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('service_review', '_complaint_status', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'open, in_progress, resolved',
        ]);
        register_post_meta('service_review', '_cleaner_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'description'  => 'Cleaner user ID if review is for cleaning',
        ]);
    }

    /**
     * Add custom columns to Cleaning Service list
     */
    public function add_cleaning_service_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['customer'] = 'Customer';
                $new_columns['contact'] = 'Contact';
                $new_columns['plan'] = 'Plan Details';
                $new_columns['amount'] = 'Amount';
                $new_columns['payment'] = 'Payment';
                $new_columns['created'] = 'Date';
            }
        }
        // Remove date from end and put our created column if we want, or just stick with standard date
        unset($new_columns['date']); 
        return $new_columns;
    }

    /**
     * Render custom columns for Cleaning Service list
     */
    public function render_cleaning_service_columns($column, $post_id) {
        switch ($column) {
            case 'customer':
                $name = get_post_meta($post_id, '_customer_name', true);
                if ($name) {
                    echo '<strong>' . esc_html($name) . '</strong>';
                } else {
                    echo '<span style="color: #999;">-</span>';
                }
                break;

            case 'contact':
                $phone = get_post_meta($post_id, '_customer_phone', true);
                $address = get_post_meta($post_id, '_customer_address', true);
                
                if ($phone) {
                    echo '<div>📞 ' . esc_html($phone) . '</div>';
                }
                if ($address) {
                    echo '<div style="font-size: 11px; color: #666; margin-top: 2px;" title="' . esc_attr($address) . '">📍 ' . mb_strimwidth(esc_html($address), 0, 20, '...') . '</div>';
                }
                break;

            case 'plan':
                $plan = get_post_meta($post_id, '_plan_type', true);
                $kw = get_post_meta($post_id, '_system_size_kw', true);
                $visits = get_post_meta($post_id, '_visits_total', true);
                
                if ($plan) {
                    echo '<div>' . ucfirst(str_replace('_', ' ', $plan)) . '</div>';
                }
                if ($kw) {
                    echo '<div style="font-size: 11px; color: #666;">⚡ ' . esc_html($kw) . ' kW</div>';
                }
                if ($visits) {
                    echo '<div style="font-size: 11px; color: #666;">Total Visits: ' . esc_html($visits) . '</div>';
                }
                break;

            case 'amount':
                $amount = get_post_meta($post_id, '_total_amount', true);
                if ($amount) {
                    echo '<strong>₹' . number_format($amount) . '</strong>';
                } else {
                    echo '-';
                }
                break;

            case 'payment':
                $status = get_post_meta($post_id, '_payment_status', true) ?: 'pending';
                $option = get_post_meta($post_id, '_payment_option', true);
                
                $colors = [
                    'pending' => '#ffc107',
                    'paid' => '#28a745',
                    'failed' => '#dc3545',
                ];
                $color = isset($colors[$status]) ? $colors[$status] : '#6c757d';
                
                echo sprintf(
                    '<span style="background: %s; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">%s</span>', 
                    $color, 
                    ucfirst($status)
                );
                
                if ($option === 'pay_after') {
                    echo '<div style="font-size: 10px; color: #888; margin-top: 2px;">(Pay After)</div>';
                }
                break;

            case 'created':
                echo get_the_date('Y-m-d H:i', $post_id);
                break;
        }
    }
}

// Initialize the class
new KSC_Cleaning_CPTs();
