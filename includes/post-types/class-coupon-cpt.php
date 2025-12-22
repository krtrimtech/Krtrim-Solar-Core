<?php
/**
 * Coupon Custom Post Type
 * 
 * Registers CPT for managing cleaning service coupons.
 * 
 * @package Krtrim_Solar_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class KSC_Coupon_CPT {

    public function __construct() {
        add_action('init', [$this, 'register_coupon_cpt']);
        add_action('save_post', [$this, 'save_coupon_meta']);
        add_action('add_meta_boxes', [$this, 'add_coupon_meta_boxes']);
        
        // Admin Columns
        add_filter('manage_solar_coupon_posts_columns', [$this, 'add_coupon_columns']);
        add_action('manage_solar_coupon_posts_custom_column', [$this, 'render_coupon_columns'], 10, 2);
    }

    public function register_coupon_cpt() {
        $labels = [
            'name'               => 'Coupons',
            'singular_name'      => 'Coupon',
            'menu_name'          => 'Coupons',
            'add_new'            => 'Add Coupon',
            'add_new_item'       => 'Add New Coupon',
            'edit_item'          => 'Edit Coupon',
            'new_item'           => 'New Coupon',
            'view_item'          => 'View Coupon',
            'search_items'       => 'Search Coupons',
            'not_found'          => 'No coupons found',
            'not_found_in_trash' => 'No coupons in trash',
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'ksc-settings', // Or top level if preferred, keeping under settings for now or maybe 'cleaning_service' parent if accessible? Let's use ksc-settings or top level. Plan didn't specify. Let's make it top level for visibility as requested for orders.
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-tickets-alt',
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => ['title'],
            'rewrite'             => false,
        ];

        register_post_type('solar_coupon', $args);
    }

    public function add_coupon_meta_boxes() {
        add_meta_box(
            'solar_coupon_details',
            'Coupon Details',
            [$this, 'render_coupon_meta_box'],
            'solar_coupon',
            'normal',
            'high'
        );
    }

    public function render_coupon_meta_box($post) {
        wp_nonce_field('save_solar_coupon', 'solar_coupon_nonce');
        
        $code = get_post_meta($post->ID, '_coupon_code', true);
        $type = get_post_meta($post->ID, '_discount_type', true) ?: 'percent';
        $amount = get_post_meta($post->ID, '_discount_amount', true);
        $expiry = get_post_meta($post->ID, '_expiry_date', true);
        
        ?>
        <div class="ksc-meta-box">
            <p>
                <label><strong>Coupon Code:</strong></label><br>
                <input type="text" name="coupon_code" value="<?php echo esc_attr($code); ?>" class="widefat" style="text-transform: uppercase;">
                <span class="description">The code users will enter (e.g. SUMMER20).</span>
            </p>
            <p>
                <label><strong>Discount Type:</strong></label><br>
                <select name="discount_type" class="widefat">
                    <option value="percent" <?php selected($type, 'percent'); ?>>Percentage (%)</option>
                    <option value="fixed" <?php selected($type, 'fixed'); ?>>Fixed Amount (₹)</option>
                </select>
            </p>
            <p>
                <label><strong>Discount Amount:</strong></label><br>
                <input type="number" name="discount_amount" value="<?php echo esc_attr($amount); ?>" class="widefat" step="0.01">
            </p>
            <p>
                <label><strong>Expiry Date:</strong></label><br>
                <input type="date" name="expiry_date" value="<?php echo esc_attr($expiry); ?>" class="widefat">
                <span class="description">Leave empty for no expiry.</span>
            </p>
        </div>
        <?php
    }

    public function save_coupon_meta($post_id) {
        if (!isset($_POST['solar_coupon_nonce']) || !wp_verify_nonce($_POST['solar_coupon_nonce'], 'save_solar_coupon')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['coupon_code'])) {
            // Force uppercase
            update_post_meta($post_id, '_coupon_code', strtoupper(sanitize_text_field($_POST['coupon_code'])));
            // Also update Title to match Code for consistency if desired, or just let Title be descriptive
        }
        if (isset($_POST['discount_type'])) {
            update_post_meta($post_id, '_discount_type', sanitize_text_field($_POST['discount_type']));
        }
        if (isset($_POST['discount_amount'])) {
            update_post_meta($post_id, '_discount_amount', floatval($_POST['discount_amount']));
        }
        if (isset($_POST['expiry_date'])) {
            update_post_meta($post_id, '_expiry_date', sanitize_text_field($_POST['expiry_date']));
        }
    }

    public function add_coupon_columns($columns) {
        $new = [];
        foreach($columns as $key => $title) {
            if ($key == 'date') {
                $new['code'] = 'Code';
                $new['discount'] = 'Discount';
                $new['expiry'] = 'Expiry';
            }
            $new[$key] = $title;
        }
        return $new;
    }

    public function render_coupon_columns($column, $post_id) {
        switch ($column) {
            case 'code':
                echo '<strong>' . esc_html(get_post_meta($post_id, '_coupon_code', true)) . '</strong>';
                break;
            case 'discount':
                $type = get_post_meta($post_id, '_discount_type', true);
                $amount = get_post_meta($post_id, '_discount_amount', true);
                if ($type == 'percent') {
                    echo esc_html($amount) . '%';
                } else {
                    echo '₹' . esc_html($amount);
                }
                break;
            case 'expiry':
                $date = get_post_meta($post_id, '_expiry_date', true);
                if ($date) {
                    $is_expired = (strtotime($date) < time());
                    echo $date;
                    if ($is_expired) {
                        echo ' <span style="color:red; font-weight:bold;">(Expired)</span>';
                    }
                } else {
                    echo '<span style="color:green;">Never</span>';
                }
                break;
        }
    }
}

new KSC_Coupon_CPT();
