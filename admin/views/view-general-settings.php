<?php
/**
 * Admin settings page for Krtrim Solar Core.
 * Consolidates Vendor Registration and Notification settings.
 */

// Render the settings page content
function sp_render_general_settings_page() {
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'vendor_registration';
    ?>
    <div class="wrap">
        <h1>Krtrim Solar Core Settings</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=ksc-settings&tab=vendor_registration" class="nav-tab <?php echo $active_tab == 'vendor_registration' ? 'nav-tab-active' : ''; ?>">Vendor Registration</a>
            <a href="?page=ksc-settings&tab=notifications" class="nav-tab <?php echo $active_tab == 'notifications' ? 'nav-tab-active' : ''; ?>">Notifications</a>
            <a href="?page=ksc-settings&tab=project_settings" class="nav-tab <?php echo $active_tab == 'project_settings' ? 'nav-tab-active' : ''; ?>">Project Settings</a>
            <a href="?page=ksc-settings&tab=cleaning_service" class="nav-tab <?php echo $active_tab == 'cleaning_service' ? 'nav-tab-active' : ''; ?>">Cleaning Service</a>
            <a href="?page=ksc-settings&tab=about" class="nav-tab <?php echo $active_tab == 'about' ? 'nav-tab-active' : ''; ?>">About & Support</a>
        </h2>
        <form method="post" action="options.php">
            <?php
            if ($active_tab == 'vendor_registration') {
                settings_fields('sp_vendor_settings_group');
                do_settings_sections('vendor-registration-settings');
                submit_button();
            } elseif ($active_tab == 'project_settings') {
                settings_fields('sp_project_settings_group');
                do_settings_sections('project-settings');
                submit_button();
            } elseif ($active_tab == 'notifications') {
                settings_fields('sp_notification_settings_group');
                do_settings_sections('notification-settings');
                submit_button();
            } elseif ($active_tab == 'cleaning_service') {
                settings_fields('ksc_cleaning_settings_group');
                do_settings_sections('cleaning-service-settings');
                submit_button();
            } elseif ($active_tab == 'about') {
                ?>
                <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
                    <h2>About Krtrim Solar Core</h2>
                    <p><strong>Krtrim Solar Core</strong> is a comprehensive project management and bidding platform for solar companies. It creates a unified dashboard for "Solar Clients", "Solar Vendors", and "Area Managers".</p>
                    
                    <hr>
                    
                    <h3>🔗 Important Links</h3>
                    <ul style="list-style: disc; margin-left: 20px; line-height: 2;">
                        <li><strong>Website:</strong> <a href="https://www.krtrim.tech/" target="_blank">https://www.krtrim.tech/</a></li>
                        <li><strong>GitHub Repository:</strong> <a href="https://github.com/krtrimtech/Krtrim-Solar-Core" target="_blank">https://github.com/krtrimtech/Krtrim-Solar-Core</a></li>
                        <li><strong>Documentation:</strong> <a href="https://github.com/krtrimtech/Krtrim-Solar-Core/wiki" target="_blank">View Documentation</a></li>
                        <li><strong>Report Issues:</strong> <a href="https://github.com/krtrimtech/Krtrim-Solar-Core/issues" target="_blank">GitHub Issues</a></li>
                    </ul>
                    
                    <hr>
                    
                    <h3>💖 Support Development</h3>
                    <p>This plugin is free and open source. If you find it useful, please consider supporting its development:</p>
                    
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 15px;">
                        <a href="https://github.com/sponsors/shyanukant" target="_blank" class="button button-primary button-hero">
                            ❤️ Sponsor on GitHub
                        </a>
                    </div>
                    
                    <div style="margin-top: 20px; background: #f0f6fc; padding: 15px; border-radius: 6px; border: 1px solid #d0d7de;">
                        <p style="margin: 0;"><strong>UPI ID for Donations:</strong> <code>shyanukant@upi</code></p>
                    </div>
                    
                    <hr>
                    
                    <h3>📞 Contact</h3>
                    <p><strong>Email:</strong> <a href="mailto:contact@krtrim.tech">contact@krtrim.tech</a></p>
                    
                    <div style="margin-top: 30px; text-align: center; color: #666;">
                        <p>Made with ❤️ by <a href="https://www.krtrim.tech" target="_blank">Krtrim Tech</a></p>
                        <p><em>Version 1.0.0</em></p>
                    </div>
                </div>
                <?php
            }
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
        'WhatsApp Settings', // Changed title
        'sp_whatsapp_section_callback',
        'notification-settings'
    );

    add_settings_field(
        'whatsapp_provider',
        'WhatsApp Provider',
        'sp_whatsapp_provider_callback',
        'notification-settings',
        'sp_whatsapp_section'
    );

    // WAHA Fields (Default)
    add_settings_field(
        'waha_api_url',
        'WAHA API URL',
        'sp_waha_api_url_callback',
        'notification-settings',
        'sp_whatsapp_section',
        ['class' => 'waha-api-field']
    );

    add_settings_field(
        'waha_session_name',
        'Session Name',
        'sp_waha_session_name_callback',
        'notification-settings',
        'sp_whatsapp_section',
        ['class' => 'waha-api-field']
    );

    add_settings_field(
        'waha_api_key',
        'API Key',
        'sp_waha_api_key_callback',
        'notification-settings',
        'sp_whatsapp_section',
        ['class' => 'waha-api-field']
    );

    // Official API Fields
    add_settings_field(
        'whatsapp_phone_id',
        'Phone Number ID (Official)',
        'sp_whatsapp_phone_id_callback',
        'notification-settings',
        'sp_whatsapp_section',
        ['class' => 'official-api-field']
    );

    add_settings_field(
        'whatsapp_business_id',
        'Business Account ID (Official)',
        'sp_whatsapp_business_id_callback',
        'notification-settings',
        'sp_whatsapp_section',
        ['class' => 'official-api-field']
    );

    add_settings_field(
        'whatsapp_access_token',
        'Access Token (Official)',
        'sp_whatsapp_access_token_callback',
        'notification-settings',
        'sp_whatsapp_section',
        ['class' => 'official-api-field']
    );

    add_settings_field(
        'whatsapp_enable',
        'Enable Click-to-Chat Buttons', 
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

    // Cleaning Service Notifications Section
    add_settings_section(
        'sp_cleaning_notifications_section',
        '🧹 Cleaning Service Notifications',
        'sp_cleaning_notifications_section_callback',
        'notification-settings'
    );

    add_settings_field(
        'cleaning_preservice_reminder',
        'Pre-Service Reminder (1 Day Before)',
        'sp_cleaning_preservice_reminder_callback',
        'notification-settings',
        'sp_cleaning_notifications_section'
    );

    add_settings_field(
        'cleaning_assignment_notification',
        'Cleaner Assignment Notification',
        'sp_cleaning_assignment_notification_callback',
        'notification-settings',
        'sp_cleaning_notifications_section'
    );

    add_settings_field(
        'cleaning_postservice_review',
        'Post-Service Review Request',
        'sp_cleaning_postservice_review_callback',
        'notification-settings',
        'sp_cleaning_notifications_section'
    );

    add_settings_field(
        'cleaning_whatsapp_enabled',
        'Enable WhatsApp for Cleaning',
        'sp_cleaning_whatsapp_enabled_callback',
        'notification-settings',
        'sp_cleaning_notifications_section'
    );

    // --- Project Settings ---
    register_setting('sp_project_settings_group', 'ksc_default_project_image');

    add_settings_section(
        'sp_project_section',
        'Project Display Settings',
        'sp_project_section_callback',
        'project-settings'
    );

    add_settings_field(
        'default_project_image',
        'Default Project Featured Image',
        'sp_default_project_image_callback',
        'project-settings',
        'sp_project_section'
    );

    // --- Cleaning Service Settings ---
    register_setting('ksc_cleaning_settings_group', 'ksc_cleaning_options');

    add_settings_section(
        'ksc_cleaning_pricing_section',
        'Cleaning Service Pricing',
        'ksc_cleaning_pricing_section_callback',
        'cleaning-service-settings'
    );

    add_settings_field(
        'cleaning_price_per_kw',
        'Price per kW (₹)',
        'ksc_cleaning_price_per_kw_callback',
        'cleaning-service-settings',
        'ksc_cleaning_pricing_section'
    );

    add_settings_field(
        'cleaning_6month_discount',
        '6-Month Plan Discount (%)',
        'ksc_cleaning_6month_discount_callback',
        'cleaning-service-settings',
        'ksc_cleaning_pricing_section'
    );

    add_settings_field(
        'cleaning_yearly_discount',
        'Yearly Plan Discount (%)',
        'ksc_cleaning_yearly_discount_callback',
        'cleaning-service-settings',
        'ksc_cleaning_pricing_section'
    );
}

// Enqueue media uploader
function sp_enqueue_admin_media_scripts($hook) {
    if ($hook !== 'settings_page_ksc-settings') {
        return;
    }
    wp_enqueue_media();
}

// Only register hooks when in admin area to prevent output during activation
if (is_admin()) {
    add_action('admin_init', 'sp_register_general_settings');
    add_action('admin_enqueue_scripts', 'sp_enqueue_admin_media_scripts');
}

// --- Callbacks ---

// Section callbacks
function sp_razorpay_section_callback() { echo 'Configure your Razorpay API credentials for test and live environments.'; }
function sp_fee_section_callback() { echo 'Set the fees for state and city coverage.'; }
function sp_email_section_callback() { echo 'Configure which email notifications are sent automatically.'; }
function sp_whatsapp_section_callback() { echo 'Enable "Click-to-Chat" buttons, which open a pre-filled WhatsApp chat in a new tab.'; }
function sp_project_section_callback() { echo 'Configure default settings for solar projects displayed on the marketplace.'; }

// Project Settings Fields
function sp_default_project_image_callback() {
    $image_url = get_option('ksc_default_project_image', '');
    ?>
    <div class="default-image-uploader">
        <input type="hidden" id="ksc_default_project_image" name="ksc_default_project_image" value="<?php echo esc_attr($image_url); ?>" />
        <div class="image-preview" style="margin-bottom: 10px;">
            <?php if ($image_url): ?>
                <img src="<?php echo esc_url($image_url); ?>" style="max-width: 300px; height: auto; display: block; margin-bottom: 10px; border: 1px solid #ddd; padding: 5px;" />
            <?php else: ?>
                <img src="" style="max-width: 300px; height: auto; display: none; margin-bottom: 10px; border: 1px solid #ddd; padding: 5px;" />
            <?php endif; ?>
        </div>
        <button type="button" class="button upload-default-image">Upload/Select Image</button>
        <?php if ($image_url): ?>
            <button type="button" class="button remove-default-image" style="margin-left: 10px;">Remove Image</button>
        <?php endif; ?>
        <p class="description">This image will be used as the featured image for projects that don't have one set. Recommended size: 400x250px</p>
    </div>
    <script>
    jQuery(document).ready(function($) {
        var mediaUploader;
        
        $('.upload-default-image').on('click', function(e) {
            e.preventDefault();
            
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            mediaUploader = wp.media({
                title: 'Select Default Project Image',
                button: { text: 'Use this image' },
                multiple: false
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#ksc_default_project_image').val(attachment.url);
                $('.image-preview img').attr('src', attachment.url).show();
                $('.remove-default-image').show();
            });
            
            mediaUploader.open();
        });
        
        $('.remove-default-image').on('click', function(e) {
            e.preventDefault();
            $('#ksc_default_project_image').val('');
            $('.image-preview img').attr('src', '').hide();
            $(this).hide();
        });
    });
    </script>
    <?php
}

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
// WhatsApp Callbacks
function sp_whatsapp_provider_callback() {
    $options = get_option('sp_notification_options');
    $provider = isset($options['whatsapp_provider']) ? $options['whatsapp_provider'] : 'waha';
    ?>
    <select name="sp_notification_options[whatsapp_provider]" id="whatsapp_provider_select">
        <option value="waha" <?php selected($provider, 'waha'); ?>>WAHA (Local/Custom API)</option>
        <option value="official" <?php selected($provider, 'official'); ?>>Official WhatsApp Cloud API</option>
    </select>
    <p class="description">Select which service to use for sending automated messages.</p>
    <script>
        jQuery(document).ready(function($) {
            function toggleFields() {
                if ($('#whatsapp_provider_select').val() === 'official') {
                    $('.official-api-field').closest('tr').show();
                    $('.waha-api-field').closest('tr').hide();
                } else {
                    $('.official-api-field').closest('tr').hide();
                    $('.waha-api-field').closest('tr').show();
                }
            }
            $('#whatsapp_provider_select').change(toggleFields);
            toggleFields(); // Init
        });
    </script>
    <?php
}

function sp_waha_api_url_callback() {
    $options = get_option('sp_notification_options');
    $val = isset($options['waha_api_url']) ? esc_attr($options['waha_api_url']) : '';
    echo "<input type='url' name='sp_notification_options[waha_api_url]' value='$val' style='width: 400px;' class='waha-api-field' />";
}

function sp_waha_session_name_callback() {
    $options = get_option('sp_notification_options');
    $val = isset($options['waha_session_name']) ? esc_attr($options['waha_session_name']) : 'default';
    echo "<input type='text' name='sp_notification_options[waha_session_name]' value='$val' style='width: 200px;' class='waha-api-field' />";
}

function sp_waha_api_key_callback() {
    $options = get_option('sp_notification_options');
    $val = isset($options['waha_api_key']) ? esc_attr($options['waha_api_key']) : '';
    echo "<input type='password' name='sp_notification_options[waha_api_key]' value='$val' style='width: 300px;' class='waha-api-field' />";
}

function sp_whatsapp_phone_id_callback() {
    $options = get_option('sp_notification_options');
    $val = isset($options['whatsapp_phone_id']) ? esc_attr($options['whatsapp_phone_id']) : '';
    echo "<input type='text' name='sp_notification_options[whatsapp_phone_id]' value='$val' style='width: 350px;' class='official-api-field' />";
    echo "<p class='description'>Found in Meta for Developers App Dashboard &gt; WhatsApp &gt; API Setup</p>";
}

function sp_whatsapp_business_id_callback() {
    $options = get_option('sp_notification_options');
    $val = isset($options['whatsapp_business_id']) ? esc_attr($options['whatsapp_business_id']) : '';
    echo "<input type='text' name='sp_notification_options[whatsapp_business_id]' value='$val' style='width: 350px;' class='official-api-field' />";
}

function sp_whatsapp_access_token_callback() {
    $options = get_option('sp_notification_options');
    $val = isset($options['whatsapp_access_token']) ? esc_attr($options['whatsapp_access_token']) : '';
    echo "<input type='password' name='sp_notification_options[whatsapp_access_token]' value='$val' style='width: 350px;' class='official-api-field' />";
    echo "<p class='description'>Permanent System User Access Token recommended.</p>";
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

// --- Cleaning Notification Callbacks ---
function sp_cleaning_notifications_section_callback() { 
    echo 'Configure notifications for solar panel cleaning services. These notifications are sent to cleaners and customers.'; 
}

function sp_cleaning_preservice_reminder_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['cleaning_preservice_reminder']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[cleaning_preservice_reminder]' value='1' $checked />";
    echo "<p class='description'>Send reminder notifications 1 day before scheduled cleaning (to cleaner and customer)</p>";
}

function sp_cleaning_assignment_notification_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['cleaning_assignment_notification']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[cleaning_assignment_notification]' value='1' $checked />";
    echo "<p class='description'>Notify cleaner when a new cleaning visit is assigned to them</p>";
}

function sp_cleaning_postservice_review_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['cleaning_postservice_review']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[cleaning_postservice_review]' value='1' $checked />";
    echo "<p class='description'>Send review request to customer 2 hours after cleaning is completed</p>";
}

function sp_cleaning_whatsapp_enabled_callback() {
    $options = get_option('sp_notification_options');
    $checked = isset($options['cleaning_whatsapp_enabled']) ? 'checked' : '';
    echo "<input type='checkbox' name='sp_notification_options[cleaning_whatsapp_enabled]' value='1' $checked />";
    echo "<p class='description'>Enable WhatsApp notifications for cleaning service reminders and review requests</p>";
}

// --- Cleaning Service Callbacks ---
function ksc_cleaning_pricing_section_callback() {
    echo 'Set pricing for solar panel cleaning services. Discount applies to subscription plans.';
}

function ksc_cleaning_price_per_kw_callback() {
    $options = get_option('ksc_cleaning_options');
    $val = isset($options['cleaning_price_per_kw']) ? esc_attr($options['cleaning_price_per_kw']) : '50';
    echo "<input type='number' name='ksc_cleaning_options[cleaning_price_per_kw]' value='$val' min='0' step='1' />";
    echo "<p class='description'>Base price charged per kW of solar system for each cleaning visit.</p>";
}

function ksc_cleaning_6month_discount_callback() {
    $options = get_option('ksc_cleaning_options');
    $val = isset($options['cleaning_6month_discount']) ? esc_attr($options['cleaning_6month_discount']) : '10';
    echo "<input type='number' name='ksc_cleaning_options[cleaning_6month_discount]' value='$val' min='0' max='100' step='1' /> %";
    echo "<p class='description'>Discount for 6-month subscription (6 visits prepaid).</p>";
}

function ksc_cleaning_yearly_discount_callback() {
    $options = get_option('ksc_cleaning_options');
    $val = isset($options['cleaning_yearly_discount']) ? esc_attr($options['cleaning_yearly_discount']) : '10';
    echo "<input type='number' name='ksc_cleaning_options[cleaning_yearly_discount]' value='$val' min='0' max='100' step='1' /> %";
    echo "<p class='description'>Discount for yearly subscription (12 visits prepaid).</p>";
}
