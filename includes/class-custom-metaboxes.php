<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SP_Custom_Metaboxes {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
        add_action( 'save_post', array( $this, 'save_metabox_data' ) );
    }

    public function add_metaboxes() {
        add_meta_box(
            'sp_project_details',
            'Project Details',
            array( $this, 'render_project_details_metabox' ),
            'solar_project',
            'normal',
            'high'
        );

        // Lead Details Meta Box
        add_meta_box(
            'sp_lead_details',
            'Lead Details',
            array( $this, 'render_lead_details_metabox' ),
            'solar_lead',
            'normal',
            'high'
        );

        // Cleaning Order Details Meta Box
        add_meta_box(
            'sp_cleaning_details',
            'Order Details',
            array( $this, 'render_cleaning_details_metabox' ),
            'cleaning_service',
            'normal',
            'high'
        );
    }

    private function get_location_data() {
        $locations = [];
        $file_path = plugin_dir_path( __DIR__ ) . 'assets/data/indian-states-cities.json';

        if (file_exists($file_path)) {
            $json_data = file_get_contents($file_path);
            $data = json_decode($json_data, true);

            if (isset($data['states'])) {
                foreach ($data['states'] as $state) {
                    $locations[$state['state']] = $state['districts'];
                }
            }
        }

        return $locations;
    }

    public function render_project_details_metabox( $post ) {
        wp_nonce_field( 'sp_save_metabox_data', 'sp_metabox_nonce' );

        $project_status = get_post_meta( $post->ID, 'project_status', true );
        $client_user_id = get_post_meta( $post->ID, '_client_user_id', true );
        $solar_system_size_kw = get_post_meta( $post->ID, '_solar_system_size_kw', true );
        $client_address = get_post_meta( $post->ID, '_client_address', true );
        $client_phone_number = get_post_meta( $post->ID, '_client_phone_number', true );
        $project_start_date = get_post_meta( $post->ID, '_project_start_date', true );
        $total_project_cost = get_post_meta( $post->ID, '_total_project_cost', true );
        $paid_amount = get_post_meta( $post->ID, '_paid_amount', true );
        $paid_to_vendor = get_post_meta( $post->ID, '_paid_to_vendor', true );
        $assigned_vendor_id = get_post_meta( $post->ID, '_assigned_vendor_id', true );
        $vendor_assignment_method = get_post_meta( $post->ID, '_vendor_assignment_method', true );
        $winning_vendor_id = get_post_meta( $post->ID, '_winning_vendor_id', true );
        $winning_bid_amount = get_post_meta( $post->ID, '_winning_bid_amount', true );

        $project_state = get_post_meta( $post->ID, '_project_state', true );
        $project_city = get_post_meta( $post->ID, '_project_city', true );

        $current_user = wp_get_current_user();
        $is_area_manager = in_array('area_manager', (array)$current_user->roles);
        $is_editable = current_user_can('manage_options') || in_array('manager', (array)$current_user->roles);

        if ($is_area_manager) {
            $project_state = get_user_meta($current_user->ID, 'state', true);
            $project_city = get_user_meta($current_user->ID, 'city', true);
        }
        
        $locations = $this->get_location_data();

        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="project_state">State</label></th>
                    <td>
                        <?php if ($is_editable): ?>
                            <select name="project_state" id="project_state">
                                <option value="">Select State</option>
                                <?php foreach ($locations as $state => $cities): ?>
                                    <option value="<?php echo esc_attr($state); ?>" <?php selected($project_state, $state); ?>><?php echo esc_html($state); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" readonly value="<?php echo esc_attr($project_state); ?>" />
                            <input type="hidden" name="project_state" value="<?php echo esc_attr($project_state); ?>" />
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="project_city">City</label></th>
                    <td>
                        <?php if ($is_editable): ?>
                            <select name="project_city" id="project_city">
                                <option value="">Select City</option>
                            </select>
                        <?php else: ?>
                            <input type="text" readonly value="<?php echo esc_attr($project_city); ?>" />
                            <input type="hidden" name="project_city" value="<?php echo esc_attr($project_city); ?>" />
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="total_project_cost">Total Project Cost (₹)</label></th>
                    <td>
                        <input type="number" id="total_project_cost" name="total_project_cost" value="<?php echo esc_attr($total_project_cost); ?>" step="0.01" placeholder="e.g., 500000">
                        <p class="description">Total price quoted to the client for this project</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="paid_amount">Paid Amount (₹)</label></th>
                    <td>
                        <input type="number" id="paid_amount" name="paid_amount" value="<?php echo esc_attr($paid_amount); ?>" step="0.01" placeholder="e.g., 300000">
                        <p class="description">Total amount received from client so far (for token, installments, etc.)</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="project_status">Project Status</label></th>
                    <td>
                        <select name="project_status" id="project_status">
                            <option value="pending" <?php selected( $project_status, 'pending' ); ?>>Pending</option>
                            <option value="assigned" <?php selected( $project_status, 'assigned' ); ?>>Assigned</option>
                            <option value="in_progress" <?php selected( $project_status, 'in_progress' ); ?>>In Progress</option>
                            <option value="completed" <?php selected( $project_status, 'completed' ); ?>>Completed</option>
                            <option value="cancelled" <?php selected( $project_status, 'cancelled' ); ?>>Cancelled</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="client_user_id">Client</label></th>
                    <td>
                        <?php
                        wp_dropdown_users( array(
                            'role' => 'solar_client',
                            'name' => 'client_user_id',
                            'selected' => $client_user_id,
                            'show_option_none' => 'Select Client',
                        ) );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="solar_system_size_kw">Solar System Size (kW)</label></th>
                    <td>
                        <input type="number" id="solar_system_size_kw" name="solar_system_size_kw" value="<?php echo esc_attr( $solar_system_size_kw ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="client_address">Client Address</label></th>
                    <td>
                        <textarea id="client_address" name="client_address" rows="4" cols="50"><?php echo esc_textarea( $client_address ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label for="client_phone_number">Client Phone Number</label></th>
                    <td>
                        <input type="text" id="client_phone_number" name="client_phone_number" value="<?php echo esc_attr( $client_phone_number ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="project_start_date">Project Start Date</label></th>
                    <td>
                        <input type="date" id="project_start_date" name="project_start_date" value="<?php echo esc_attr( $project_start_date ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label>Vendor Assignment</label></th>
                    <td>
                        <label><input type="radio" name="vendor_assignment_method" value="manual" <?php checked($vendor_assignment_method, 'manual'); ?>> Manual</label>
                        <label style="margin-left: 15px;"><input type="radio" name="vendor_assignment_method" value="bidding" <?php checked($vendor_assignment_method, 'bidding'); ?>> Bidding</label>
                    </td>
                </tr>
                <tr class="vendor-manual-fields">
                    <th><label for="assigned_vendor_id">Assign Vendor</label></th>
                    <td>
                        <?php
                        wp_dropdown_users( array(
                            'role' => 'solar_vendor',
                            'name' => 'assigned_vendor_id',
                            'selected' => $assigned_vendor_id,
                            'show_option_none' => 'Select Vendor',
                        ) );
                        ?>
                    </td>
                </tr>
                <tr class="vendor-manual-fields">
                    <th><label for="paid_to_vendor">Amount to be Paid to Vendor</label></th>
                    <td>
                        <input type="number" id="paid_to_vendor" name="paid_to_vendor" value="<?php echo esc_attr( $paid_to_vendor ); ?>" />
                    </td>
                </tr>
                <tr class="vendor-bidding-fields">
                    <th><label>Winning Vendor</label></th>
                    <td>
                        <?php
                        if ($winning_vendor_id) {
                            $vendor = get_userdata($winning_vendor_id);
                            echo '<strong>' . esc_html($vendor->display_name) . '</strong>';
                        } else {
                            echo '<em>Award a bid to select a vendor.</em>';
                        }
                        ?>
                    </td>
                </tr>
                <tr class="vendor-bidding-fields">
                    <th><label>Winning Bid Amount</label></th>
                    <td>
                        <input type="text" readonly value="<?php echo esc_attr( $winning_bid_amount ); ?>" />
                    </td>
                </tr>
            </tbody>
        </table>
        <script>
            jQuery(document).ready(function($) {
                var locations = <?php echo json_encode($locations); ?>;
                var selectedCity = '<?php echo esc_js($project_city); ?>';

                function updateCities() {
                    var state = $('#project_state').val();
                    var cityDropdown = $('#project_city');
                    cityDropdown.empty().append('<option value="">Select City</option>');

                    if (locations[state]) {
                        $.each(locations[state], function(index, city) {
                            var option = $('<option></option>').attr('value', city).text(city);
                            if (city === selectedCity) {
                                option.attr('selected', 'selected');
                            }
                            cityDropdown.append(option);
                        });
                    }
                }

                if ($('#project_state').length) {
                    updateCities();
                    $('#project_state').on('change', function() {
                        selectedCity = ''; // Reset city on state change
                        updateCities();
                    });
                }

                function toggleVendorFields() {
                    var method = $('input[name="vendor_assignment_method"]:checked').val();
                    if (method === 'manual') {
                        $('.vendor-manual-fields').show();
                        $('.vendor-bidding-fields').hide();
                    } else {
                        $('.vendor-manual-fields').hide();
                        $('.vendor-bidding-fields').show();
                    }
                }
                toggleVendorFields();
                $('input[name="vendor_assignment_method"]').on('change', toggleVendorFields);
            });
        </script>
        <?php
    }

    public function save_metabox_data( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['sp_metabox_nonce'] ) || ! wp_verify_nonce( $_POST['sp_metabox_nonce'], 'sp_save_metabox_data' ) ) {
            return;
        }
        
        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // Check post type
        if ( isset( $_POST['post_type'] )) {
            $post_type = $_POST['post_type'];
            
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            // Handle Lead meta
            if ($post_type === 'solar_lead') {
                $this->save_lead_meta($post_id);
                return;
            }

            // Handle Cleaning Order meta
            if ($post_type === 'cleaning_service') {
                $this->save_cleaning_meta($post_id);
                return;
            }

            // Handle Solar Project meta
            if ($post_type !== 'solar_project') {
                return;
            }
        } else {
            return;
        }

        $fields = array(
            'project_state',
            'project_city',
            'total_project_cost',
            'paid_amount',
            'project_status',
            'client_user_id',
            'solar_system_size_kw',
            'client_address',
            'client_phone_number',
            'project_start_date',
            'vendor_assignment_method',
            'assigned_vendor_id',
            'paid_to_vendor',
        );

        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                // Special handling: project_status WITHOUT underscore, others WITH underscore
                $meta_key = ($field === 'project_status') ? 'project_status' : ('_' . $field);
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $field ] ) );
            }
        }
        
        // Auto-update project_status to 'assigned' when vendor is manually assigned
        if (isset($_POST['vendor_assignment_method']) && $_POST['vendor_assignment_method'] === 'manual') {
            if (isset($_POST['assigned_vendor_id']) && !empty($_POST['assigned_vendor_id']) && $_POST['assigned_vendor_id'] !== '-1') {
                // Get current status
                $current_status = get_post_meta($post_id, 'project_status', true);
                
                // Only auto-update if status is 'pending' (don't override manual status changes)
                if ($current_status === 'pending' || empty($current_status)) {
                    update_post_meta($post_id, 'project_status', 'assigned');
                    
                    // Create process steps using the same function as bidding flow
                    $admin_api = new KSC_Admin_Manager_API();
                    $admin_api->create_default_process_steps($post_id);
                }
            }
        }
    }

    /**
     * Render Lead Details Meta Box
     */
    public function render_lead_details_metabox($post) {
        wp_nonce_field('sp_save_lead_data', 'sp_lead_nonce');
        
        $lead_phone = get_post_meta($post->ID, '_lead_phone', true);
        $lead_email = get_post_meta($post->ID, '_lead_email', true);
        $lead_address = get_post_meta($post->ID, '_lead_address', true);
        $lead_source = get_post_meta($post->ID, '_lead_source', true);
        $lead_status = get_post_meta($post->ID, '_lead_status', true) ?: 'new';
        $system_size = get_post_meta($post->ID, '_system_size_kw', true);
        $assigned_sm = get_post_meta($post->ID, '_assigned_sales_manager', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="lead_phone">Phone Number *</label></th>
                <td><input type="text" id="lead_phone" name="lead_phone" value="<?php echo esc_attr($lead_phone); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="lead_email">Email</label></th>
                <td><input type="email" id="lead_email" name="lead_email" value="<?php echo esc_attr($lead_email); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="lead_address">Address</label></th>
                <td><textarea id="lead_address" name="lead_address" rows="3" class="large-text"><?php echo esc_textarea($lead_address); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="system_size">System Size (kW)</label></th>
                <td><input type="number" id="system_size" name="system_size" value="<?php echo esc_attr($system_size); ?>" step="0.1"></td>
            </tr>
            <tr>
                <th><label for="lead_source">Lead Source</label></th>
                <td>
                    <select id="lead_source" name="lead_source">
                        <option value="">Select Source</option>
                        <option value="website" <?php selected($lead_source, 'website'); ?>>Website</option>
                        <option value="referral" <?php selected($lead_source, 'referral'); ?>>Referral</option>
                        <option value="social_media" <?php selected($lead_source, 'social_media'); ?>>Social Media</option>
                        <option value="google_ads" <?php selected($lead_source, 'google_ads'); ?>>Google Ads</option>
                        <option value="cold_call" <?php selected($lead_source, 'cold_call'); ?>>Cold Call</option>
                        <option value="walk_in" <?php selected($lead_source, 'walk_in'); ?>>Walk-in</option>
                        <option value="other" <?php selected($lead_source, 'other'); ?>>Other</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="lead_status">Lead Status</label></th>
                <td>
                    <select id="lead_status" name="lead_status">
                        <option value="new" <?php selected($lead_status, 'new'); ?>>New</option>
                        <option value="contacted" <?php selected($lead_status, 'contacted'); ?>>Contacted</option>
                        <option value="qualified" <?php selected($lead_status, 'qualified'); ?>>Qualified</option>
                        <option value="proposal_sent" <?php selected($lead_status, 'proposal_sent'); ?>>Proposal Sent</option>
                        <option value="negotiation" <?php selected($lead_status, 'negotiation'); ?>>Negotiation</option>
                        <option value="converted" <?php selected($lead_status, 'converted'); ?>>Converted</option>
                        <option value="lost" <?php selected($lead_status, 'lost'); ?>>Lost</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="assigned_sm">Assigned Sales Manager</label></th>
                <td>
                    <?php
                    wp_dropdown_users(array(
                        'role' => 'sales_manager',
                        'name' => 'assigned_sm',
                        'selected' => $assigned_sm,
                        'show_option_none' => 'Not Assigned',
                        'option_none_value' => '',
                    ));
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Cleaning Order Details Meta Box
     */
    public function render_cleaning_details_metabox($post) {
        wp_nonce_field('sp_save_cleaning_data', 'sp_cleaning_nonce');
        
        $customer_name = get_post_meta($post->ID, '_customer_name', true);
        $customer_phone = get_post_meta($post->ID, '_customer_phone', true);
        $customer_address = get_post_meta($post->ID, '_customer_address', true);
        $system_size_kw = get_post_meta($post->ID, '_system_size_kw', true);
        $plan_type = get_post_meta($post->ID, '_plan_type', true) ?: 'one_time';
        $visits_total = get_post_meta($post->ID, '_visits_total', true);
        $visits_used = get_post_meta($post->ID, '_visits_used', true);
        $total_amount = get_post_meta($post->ID, '_total_amount', true);
        $payment_status = get_post_meta($post->ID, '_payment_status', true) ?: 'pending';
        $payment_option = get_post_meta($post->ID, '_payment_option', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="customer_name">Customer Name *</label></th>
                <td><input type="text" id="customer_name" name="customer_name" value="<?php echo esc_attr($customer_name); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="customer_phone">Phone Number *</label></th>
                <td><input type="text" id="customer_phone" name="customer_phone" value="<?php echo esc_attr($customer_phone); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="customer_address">Address</label></th>
                <td><textarea id="customer_address" name="customer_address" rows="3" class="large-text"><?php echo esc_textarea($customer_address); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="system_size_kw">System Size (kW) *</label></th>
                <td><input type="number" id="system_size_kw" name="system_size_kw" value="<?php echo esc_attr($system_size_kw); ?>" step="0.1" required></td>
            </tr>
            <tr>
                <th><label for="plan_type">Plan Type</label></th>
                <td>
                    <select id="plan_type" name="plan_type">
                        <option value="one_time" <?php selected($plan_type, 'one_time'); ?>>One Time</option>
                        <option value="monthly" <?php selected($plan_type, 'monthly'); ?>>Monthly (12 visits)</option>
                        <option value="6_month" <?php selected($plan_type, '6_month'); ?>>6 Month (6 visits)</option>
                        <option value="yearly" <?php selected($plan_type, 'yearly'); ?>>Yearly (12 visits)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="visits_total">Total Visits</label></th>
                <td><input type="number" id="visits_total" name="visits_total" value="<?php echo esc_attr($visits_total); ?>"></td>
            </tr>
            <tr>
                <th><label for="visits_used">Visits Used</label></th>
                <td><input type="number" id="visits_used" name="visits_used" value="<?php echo esc_attr($visits_used); ?>"></td>
            </tr>
            <tr>
                <th><label for="total_amount">Total Amount (₹)</label></th>
                <td><input type="number" id="total_amount" name="total_amount" value="<?php echo esc_attr($total_amount); ?>" step="0.01"></td>
            </tr>
            <tr>
                <th><label for="payment_status">Payment Status</label></th>
                <td>
                    <select id="payment_status" name="payment_status">
                        <option value="pending" <?php selected($payment_status, 'pending'); ?>>Pending</option>
                        <option value="paid" <?php selected($payment_status, 'paid'); ?>>Paid</option>
                        <option value="failed" <?php selected($payment_status, 'failed'); ?>>Failed</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="payment_option">Payment Option</label></th>
                <td>
                    <select id="payment_option" name="payment_option">
                        <option value="online" <?php selected($payment_option, 'online'); ?>>Online</option>
                        <option value="pay_after" <?php selected($payment_option, 'pay_after'); ?>>Pay After Service</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save Lead Meta Data
     */
    private function save_lead_meta($post_id) {
        if (!isset($_POST['sp_lead_nonce']) || !wp_verify_nonce($_POST['sp_lead_nonce'], 'sp_save_lead_data')) {
            return;
        }
        
        $fields = ['lead_phone', 'lead_email', 'lead_address', 'lead_source', 'lead_status', 'system_size', 'assigned_sm'];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $meta_key = '_' . ($field === 'system_size' ? 'system_size_kw' : ($field === 'assigned_sm' ? 'assigned_sales_manager' : $field));
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
            }
        }
    }

    /**
     * Save Cleaning Order Meta Data
     */
    private function save_cleaning_meta($post_id) {
        if (!isset($_POST['sp_cleaning_nonce']) || !wp_verify_nonce($_POST['sp_cleaning_nonce'], 'sp_save_cleaning_data')) {
            return;
        }
        
        $fields = [
            'customer_name', 'customer_phone', 'customer_address', 'system_size_kw',
            'plan_type', 'visits_total', 'visits_used', 'total_amount',
            'payment_status', 'payment_option'
        ];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
}

new SP_Custom_Metaboxes();
