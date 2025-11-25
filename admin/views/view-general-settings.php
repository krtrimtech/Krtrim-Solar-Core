<?php
/**
 * Admin settings page for Kritim Solar Core.
 * Consolidates Vendor Registration and Notification settings.
 */

// Render the settings page content
function sp_render_general_settings_page() {
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'vendor_registration';
    ?>
    <div class="wrap">
        <h1>Kritim Solar Core Settings</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=ksc-settings&tab=vendor_registration" class="nav-tab <?php echo $active_tab == 'vendor_registration' ? 'nav-tab-active' : ''; ?>">Vendor Registration</a>
            <a href="?page=ksc-settings&tab=notifications" class="nav-tab <?php echo $active_tab == 'notifications' ? 'nav-tab-active' : ''; ?>">Notifications</a>
        </h2>
        <form method="post" action="options.php">
            <?php
            if ($active_tab == 'vendor_registration') {
                settings_fields('sp_vendor_settings_group');
                do_settings_sections('vendor-registration-settings');
            } else {
                settings_fields('sp_notification_settings_group');
                do_settings_sections('notification-settings');
            }
            submit_button();
            ?>
        </form>
    </div>
    <script>
    jQuery(document).ready(function($) {
        function toggleRazorpayFields() {
            var mode = $('input[name="sp_vendor_options[razorpay_mode]"]:checked').val();
            if (mode === 'live') {
                $('.razorpay-test-field').closest('tr').hide();
                $('.razorpay-live-field').closest('tr').show();
            } else {
                $('.razorpay-test-field').closest('tr').show();
                $('.razorpay-live-field').closest('tr').hide();
            }
        }

        // Initial check
        toggleRazorpayFields();

        // On change
        $('input[name="sp_vendor_options[razorpay_mode]"]').on('change', function() {
            toggleRazorpayFields();
        });
    });
    </script>
    <?php
}

// Register settings, sections, and fields
function sp_register_general_settings() {
    // --- Vendor Registration Settings ---
    register_setting('sp_vendor_settings_group', 'sp_vendor_options');

    // Razorpay Section
    add_settings_section(
        'sp_razorpay_section',
        'Razorpay API Settings',
        'sp_razorpay_section_callback',
        'vendor-registration-settings'
    );

    add_settings_field(
        'razorpay_mode',
        'Razorpay Mode',
        'sp_razorpay_mode_callback',
        'vendor-registration-settings',
        'sp_razorpay_section'
    );

    add_settings_field(
        'razorpay_test_key_id',
        'Test Key ID',
        'sp_razorpay_test_key_id_callback',
        'vendor-registration-settings',
        'sp_razorpay_section',
        ['class' => 'razorpay-test-field']
    );

    add_settings_field(
        'razorpay_test_key_secret',
        'Test Key Secret',
        'sp_razorpay_test_key_secret_callback',
        'vendor-registration-settings',
        'sp_razorpay_section',
        ['class' => 'razorpay-test-field']
    );

    add_settings_field(
        'razorpay_live_key_id',
        'Live Key ID',
        'sp_razorpay_live_key_id_callback',
        'vendor-registration-settings',
        'sp_razorpay_section',
        ['class' => 'razorpay-live-field']
    );

    add_settings_field(
        'razorpay_live_key_secret',
        'Live Key Secret',
        'sp_razorpay_live_key_secret_callback',
        'vendor-registration-settings',
        'sp_razorpay_section',
        ['class' => 'razorpay-live-field']
    );

    // Fee Section
    add_settings_section(
        'sp_fee_section',
        'Coverage Fee Settings',
        'sp_fee_section_callback',
        'vendor-registration-settings'
    );

    add_settings_field(
        'per_state_fee',
        'Per-State Fee (₹)',
        'sp_per_state_fee_callback',
        'vendor-registration-settings',
        'sp_fee_section'
    );

    add_settings_field(
        'per_city_fee',
        'Per-City Fee (₹)',
        'sp_per_city_fee_callback',
        'vendor-registration-settings',
        'sp_fee_section'
    );


    // --- Notification Settings ---
    register_setting('sp_notification_settings_group', 'sp_notification_options');

    // Email Settings Section
    add_settings_section(
        'sp_email_section',
        'Email Notifications',
        'sp_email_section_callback',
        'notification-settings'
    );

    add_settings_field(
        'email_vendor_approved',
        'Vendor Approved Email',
        'sp_email_vendor_approved_callback',
        'notification-settings',
        'sp_email_section'
    );
    
    add_settings_field(
        'email_vendor_rejected',
        'Vendor Rejected Email',
        'sp_email_vendor_rejected_callback',
        'notification-settings',
        'sp_email_section'
    );

    add_settings_field(
        'email_submission_approved',
        'Submission Approved Email',
        'sp_email_submission_approved_callback',
        'notification-settings',
        'sp_email_section'
    );

    add_settings_field(
        'email_submission_rejected',
        'Submission Rejected Email',
        'sp_email_submission_rejected_callback',
        'notification-settings',
        'sp_email_section'
    );

    // WhatsApp Settings Section
    add_settings_section(
        'sp_whatsapp_section',
        'WhatsApp Click-to-Chat',
        'sp_whatsapp_section_callback',
        'notification-settings'
    );

    add_settings_field(
        'whatsapp_enable',
        'Enable WhatsApp Buttons',
        'sp_whatsapp_enable_callback',
        'notification-settings',
        'sp_whatsapp_section'
    );

    add_settings_field(
        'whatsapp_vendor_approved',
        'On Vendor Approved',
        'sp_whatsapp_vendor_approved_callback',
        'notification-settings',
        'sp_whatsapp_section'
    );
    
    add_settings_field(
        'whatsapp_vendor_rejected',
        'On Vendor Rejected',
        'sp_whatsapp_vendor_rejected_callback',
        'notification-settings',
        'sp_whatsapp_section'
    );

    add_settings_field(
        'whatsapp_submission_approved',
        'On Submission Approved',
        'sp_whatsapp_submission_approved_callback',
        'notification-settings',
        'sp_whatsapp_section'
    );

    add_settings_field(
        'whatsapp_submission_rejected',
        'On Submission Rejected',
        'sp_whatsapp_submission_rejected_callback',
        'notification-settings',
        'sp_whatsapp_section'
    );
}
add_action('admin_init', 'sp_register_general_settings');

// --- Callbacks ---

// Section callbacks
function sp_razorpay_section_callback() { echo 'Configure your Razorpay API credentials for test and live environments.'; }
function sp_fee_section_callback() { echo 'Set the fees for state and city coverage.'; }
function sp_email_section_callback() { echo 'Configure which email notifications are sent automatically.'; }
function sp_whatsapp_section_callback() { echo 'Enable "Click-to-Chat" buttons, which open a pre-filled WhatsApp chat in a new tab.'; }

// Vendor Fields
function sp_razorpay_mode_callback() {
    $options = get_option('sp_vendor_options');
    $mode = isset($options['razorpay_mode']) ? $options['razorpay_mode'] : 'test';
    ?>
    <label><input type="radio" name="sp_vendor_options[razorpay_mode]" value="test" <?php checked($mode, 'test'); ?>> Test</label>
    <br>
    <label><input type="radio" name="sp_vendor_options[razorpay_mode]" value="live" <?php checked($mode, 'live'); ?>> Live</label>
    <?php
}
function sp_razorpay_test_key_id_callback() {
    $options = get_option('sp_vendor_options');
    $val = isset($options['razorpay_test_key_id']) ? esc_attr($options['razorpay_test_key_id']) : '';
    echo "<input type='text' name='sp_vendor_options[razorpay_test_key_id]' value='$val' size='50' class='razorpay-test-field' />";
}
function sp_razorpay_test_key_secret_callback() {
    $options = get_option('sp_vendor_options');
    $val = isset($options['razorpay_test_key_secret']) ? esc_attr($options['razorpay_test_key_secret']) : '';
    echo "<input type='password' name='sp_vendor_options[razorpay_test_key_secret]' value='$val' size='50' class='razorpay-test-field' />";
}
function sp_razorpay_live_key_id_callback() {
    $options = get_option('sp_vendor_options');
    $val = isset($options['razorpay_live_key_id']) ? esc_attr($options['razorpay_live_key_id']) : '';
    echo "<input type='text' name='sp_vendor_options[razorpay_live_key_id]' value='$val' size='50' class='razorpay-live-field' />";
}
function sp_razorpay_live_key_secret_callback() {
    $options = get_option('sp_vendor_options');
    $val = isset($options['razorpay_live_key_secret']) ? esc_attr($options['razorpay_live_key_secret']) : '';
    echo "<input type='password' name='sp_vendor_options[razorpay_live_key_secret]' value='$val' size='50' class='razorpay-live-field' />";
}
function sp_per_state_fee_callback() {
    $options = get_option('sp_vendor_options');
    $val = isset($options['per_state_fee']) ? esc_attr($options['per_state_fee']) : '500';
    echo "<input type='number' name='sp_vendor_options[per_state_fee]' value='$val' />";
}
function sp_per_city_fee_callback() {
    $options = get_option('sp_vendor_options');
    $val = isset($options['per_city_fee']) ? esc_attr($options['per_city_fee']) : '100';
    echo "<input type='number' name='sp_vendor_options[per_city_fee]' value='$val' />";
}

// Notification Fields
function sp_email_vendor_approved_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['email_vendor_approved']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[email_vendor_approved]' value='1' $checked />";
}
function sp_email_vendor_rejected_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['email_vendor_rejected']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[email_vendor_rejected]' value='1' $checked />";
}
function sp_email_submission_approved_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['email_submission_approved']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[email_submission_approved]' value='1' $checked />";
}
function sp_email_submission_rejected_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['email_submission_rejected']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[email_submission_rejected]' value='1' $checked />";
}
function sp_whatsapp_enable_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['whatsapp_enable']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[whatsapp_enable]' value='1' $checked /> Enable all WhatsApp buttons globally";
}
function sp_whatsapp_vendor_approved_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['whatsapp_vendor_approved']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[whatsapp_vendor_approved]' value='1' $checked />";
}
function sp_whatsapp_vendor_rejected_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['whatsapp_vendor_rejected']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[whatsapp_vendor_rejected]' value='1' $checked />";
}
function sp_whatsapp_submission_approved_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['whatsapp_submission_approved']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[whatsapp_submission_approved]' value='1' $checked />";
}
function sp_whatsapp_submission_rejected_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['whatsapp_submission_rejected']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[whatsapp_submission_rejected]' value='1' $checked />";
}
