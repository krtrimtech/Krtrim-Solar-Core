<?php
/**
 * Shortcode for displaying the vendor registration form.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_vendor_registration_form_shortcode() {
    // Enqueue scripts and styles
    wp_enqueue_script('razorpay-checkout', 'https://checkout.razorpay.com/v1/checkout.js', [], null, true);
    wp_enqueue_script('unified-dashboard-scripts');
    wp_enqueue_style('unified-dashboard-styles');

    // Localize script to pass data
    $options = get_option('sp_vendor_options');
    wp_localize_script('unified-dashboard-scripts', 'vendor_reg_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'razorpay_key_id' => $options['razorpay_key_id'] ?? '',
        'per_state_fee' => $options['per_state_fee'] ?? 500,
        'per_city_fee' => $options['per_city_fee'] ?? 100,
        'nonce' => wp_create_nonce('vendor_registration_nonce'),
    ]);

    ob_start();
    ?>
    <div id="vendor-registration-app" class="vendor-registration-wrapper">
        <div id="vreg-feedback" class="form-feedback" style="display:none;"></div>

        <!-- Step 1: Basic Info -->
        <div id="vreg-step-1" class="vreg-step active">
            <h2>Step 1: Basic Information</h2>
            <div class="form-group">
                <label for="vreg-name">Full Name *</label>
                <input type="text" id="vreg-name" required>
            </div>
            <div class="form-group">
                <label for="vreg-company">Company Name *</label>
                <input type="text" id="vreg-company" required>
            </div>
            <div class="form-group">
                <label for="vreg-email">Email Address *</label>
                <input type="email" id="vreg-email" required>
            </div>
            <div class="form-group">
                <label for="vreg-phone">Phone Number *</label>
                <input type="tel" id="vreg-phone" required>
            </div>
            <div class="form-group">
                <label for="vreg-password">Password *</label>
                <input type="password" id="vreg-password" required>
            </div>
            <button id="vreg-step1-next" class="btn btn-primary">Next</button>
        </div>

        <!-- Step 2: Coverage Selection -->
        <div id="vreg-step-2" class="vreg-step" style="display:none;">
            <h2>Step 2: Select Coverage Area</h2>
            <div id="coverage-selection-loader">Loading areas...</div>
            <div id="coverage-selection-ui" style="display:none;">
                <!-- States and Cities will be populated by JS -->
            </div>
            <div class="coverage-summary">
                <h3>Total Amount: ₹<span id="vreg-total-amount">0</span></h3>
            </div>
            <button id="vreg-step2-prev" class="btn btn-secondary">Previous</button>
            <button id="vreg-step2-next" class="btn btn-primary">Next</button>
        </div>

        <!-- Step 3: Summary & Payment -->
        <div id="vreg-step-3" class="vreg-step" style="display:none;">
            <h2>Step 3: Summary and Payment</h2>
            <div id="vreg-summary">
                <!-- Summary will be populated by JS -->
            </div>
            <button id="vreg-step3-prev" class="btn btn-secondary">Previous</button>
            <button id="vreg-pay-btn" class="btn btn-primary">Proceed to Payment</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('vendor_registration_form', 'sp_vendor_registration_form_shortcode');
