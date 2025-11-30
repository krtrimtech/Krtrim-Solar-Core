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
        if ( isset( $_POST['post_type'] ) && 'solar_project' == $_POST['post_type'] ) {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
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
}

new SP_Custom_Metaboxes();
