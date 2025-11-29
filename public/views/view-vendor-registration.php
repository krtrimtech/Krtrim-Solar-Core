<?php
/**
 * Shortcode for displaying the vendor registration form.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_vendor_registration_form_shortcode() {
    // Script enqueuing is now handled in unified-solar-dashboard.php
    // based on shortcode detection

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
                <div class="password-wrapper">
                    <input type="password" id="vreg-password" required>
                    <button type="button" class="toggle-password" id="toggle-vreg-password" aria-label="Toggle password visibility">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
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
