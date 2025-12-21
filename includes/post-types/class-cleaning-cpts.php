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
    }

    /**
     * Register Cleaning Service CPT (Subscriptions/Bookings)
     */
    public function register_cleaning_service_cpt() {
        $labels = [
            'name'               => 'Cleaning Services',
            'singular_name'      => 'Cleaning Service',
            'menu_name'          => 'Cleaning Services',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Cleaning Service',
            'edit_item'          => 'Edit Cleaning Service',
            'new_item'           => 'New Cleaning Service',
            'view_item'          => 'View Cleaning Service',
            'search_items'       => 'Search Cleaning Services',
            'not_found'          => 'No cleaning services found',
            'not_found_in_trash' => 'No cleaning services in trash',
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
}

// Initialize the class
new KSC_Cleaning_CPTs();
