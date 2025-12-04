<?php
/**
 * Creates a custom admin page for vendor approval.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Render the content of the admin page
function sp_render_vendor_approval_page() {
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Vendor Approval</h1>
        <p class="description">Manage vendor registrations. Vendors are auto-approved when they complete payment and verify their email.</p>
        <hr class="wp-header-end">
        
        <table class="wp-list-table widefat fixed striped users">
            <thead>
                <tr>
                    <th scope="col" class="manage-column">Company Name</th>
                    <th scope="col" class="manage-column">Contact</th>
                    <th scope="col" class="manage-column">Coverage</th>
                    <th scope="col" class="manage-column">Payment</th>
                    <th scope="col" class="manage-column">Email Verified</th>
                    <th scope="col" class="manage-column">Approval Status</th>
                    <th scope="col" class="manage-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $vendors = get_users(['role' => 'solar_vendor']);
                if (!empty($vendors)) {
                    foreach ($vendors as $vendor) {
                        $user_id = $vendor->ID;
                        $company_name = get_user_meta($user_id, 'company_name', true) ?: 'N/A';
                        $phone = get_user_meta($user_id, 'phone', true);
                        $email_verified = get_user_meta($user_id, 'email_verified', true);
                        $account_approved = get_user_meta($user_id, 'account_approved', true);
                        $payment_status = get_user_meta($user_id, 'vendor_payment_status', true);
                        $approval_method = get_user_meta($user_id, 'approval_method', true);
                        $approved_by = get_user_meta($user_id, 'account_approved_by', true);
                        $approved_date = get_user_meta($user_id, 'account_approved_date', true);
                        
                        $purchased_states = get_user_meta($user_id, 'purchased_states', true) ?: [];
                        $purchased_cities = get_user_meta($user_id, 'purchased_cities', true) ?: [];

                        ?>
                        <tr>
                            <td><strong><a href="<?php echo get_edit_user_link($user_id); ?>"><?php echo esc_html($company_name); ?></a></strong></td>
                            <td><?php echo esc_html($vendor->user_email); ?><br><?php echo esc_html($phone); ?></td>
                            <td><?php echo count($purchased_states); ?> States, <?php echo count($purchased_cities); ?> Cities</td>
                            <td>
                                <?php if ($payment_status === 'completed'): ?>
                                    <span style="color:green;" title="Payment completed">✅ Paid</span>
                                <?php else: ?>
                                    <span style="color:orange;" title="Payment pending">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($email_verified === 'yes'): ?>
                                    <span style="color:green;" title="Email verified">✅ Yes</span>
                                <?php else: ?>
                                    <span style="color:orange;" title="Email not verified">⏳ No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if ($account_approved === 'yes') {
                                    echo '<strong style="color:green;">✅ Approved</strong>';
                                    if ($approval_method === 'auto') {
                                        echo '<br><small style="color:#666;">Auto-approved</small>';
                                    } elseif ($approval_method === 'manual') {
                                        $approver = '';
                                        if ($approved_by && $approved_by !== 'auto') {
                                            $admin = get_userdata($approved_by);
                                            $approver = $admin ? ' by ' . $admin->display_name : '';
                                        }
                                        echo '<br><small style="color:#666;">Manual' . esc_html($approver) . '</small>';
                                    }
                                    if ($approved_date) {
                                        echo '<br><small style="color:#999;">' . date('M j, Y', strtotime($approved_date)) . '</small>';
                                    }
                                } elseif ($account_approved === 'denied') {
                                    echo '<strong style="color:red;">❌ Denied</strong>';
                                } else {
                                    echo '<strong style="color:orange;">⏳ Pending</strong>';
                                    // Show what's missing
                                    $missing = [];
                                    if ($payment_status !== 'completed') $missing[] = 'Payment';
                                    if ($email_verified !== 'yes') $missing[] = 'Email';
                                    if (!empty($missing)) {
                                        echo '<br><small style="color:#999;">Needs: ' . implode(', ', $missing) . '</small>';
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($account_approved !== 'yes'): ?>
                                    <button class="button button-primary vendor-action-btn" data-action="approve" data-user-id="<?php echo $user_id; ?>" title="Manually approve (bypasses email/payment checks)">
                                        Manual Approve
                                    </button>
                                <?php endif; ?>
                                <button class="button vendor-edit-btn" 
                                    data-user-id="<?php echo $user_id; ?>"
                                    data-company="<?php echo esc_attr($company_name); ?>"
                                    data-phone="<?php echo esc_attr($phone); ?>"
                                    data-states='<?php echo json_encode($purchased_states); ?>'
                                    data-cities='<?php echo json_encode($purchased_cities); ?>'
                                >Edit</button>
                                <?php if ($account_approved !== 'denied'): ?>
                                    <button class="button button-secondary vendor-action-btn" data-action="deny" data-user-id="<?php echo $user_id; ?>">Deny</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                    }
                } else {
                    echo '<tr><td colspan="7">No vendors found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
        <?php wp_nonce_field('sp_vendor_approval_nonce', 'sp_vendor_approval_nonce_field'); ?>

        <!-- Edit Vendor Modal -->
        <div id="edit-vendor-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; overflow-y: auto;">
            <div style="background:#fff; width:600px; max-width:90%; margin:30px auto; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.3); max-height: calc(100vh - 60px); display: flex; flex-direction: column;">
                <h2 style="padding: 20px 20px 10px 20px; margin: 0; border-bottom: 1px solid #ddd;">Edit Vendor Details</h2>
                <form id="edit-vendor-form" style="display: flex; flex-direction: column; flex: 1; min-height: 0;">
                    <div style="padding: 20px; overflow-y: auto; flex: 1;">
                        <input type="hidden" id="edit-vendor-id" name="user_id">
                        
                        <p>
                            <label for="edit-company-name">Company Name:</label><br>
                            <input type="text" id="edit-company-name" name="company_name" class="widefat" required>
                        </p>
                        
                        <p>
                            <label for="edit-phone">Phone:</label><br>
                            <input type="text" id="edit-phone" name="phone" class="widefat" required>
                        </p>
                        
                        <p>
                            <label><strong>Coverage States:</strong></label><br>
                            <div id="edit-states-checkboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #f9f9f9;">
                                <?php
                                $json_file = plugin_dir_path(dirname(dirname(__FILE__))) . 'assets/data/indian-states-cities.json';
                                if (file_exists($json_file)) {
                                    $json_data = file_get_contents($json_file);
                                    $data = json_decode($json_data, true);
                                    if ($data && isset($data['states'])) {
                                        foreach ($data['states'] as $state) {
                                            echo '<label style="display: block; padding: 4px 0; cursor: pointer;">';
                                            echo '<input type="checkbox" class="state-checkbox" value="' . esc_attr($state['state']) . '" style="margin-right: 8px;">';
                                            echo '<span>' . esc_html($state['state']) . '</span>';
                                            echo '</label>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                            <div style="margin-top: 8px;">
                                <button type="button" class="button button-small" onclick="selectAllStates()">Select All</button>
                                <button type="button" class="button button-small" onclick="deselectAllStates()">Deselect All</button>
                            </div>
                        </p>
                        
                        <p>
                            <label><strong>Coverage Cities:</strong></label><br>
                            <div id="edit-cities-checkboxes" style="max-height: 250px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #f9f9f9;">
                                <p style="text-align: center; color: #999; margin: 20px 0;">Select states above to see available cities</p>
                            </div>
                            <div style="margin-top: 8px;">
                                <button type="button" class="button button-small" onclick="selectAllCities()">Select All</button>
                                <button type="button" class="button button-small" onclick="deselectAllCities()">Deselect All</button>
                            </div>
                        </p>
                    </div>
                    
                    <div style="padding: 15px 20px; border-top: 1px solid #ddd; background: #f9f9f9; text-align: right; flex-shrink: 0;">
                        <button type="button" class="button" onclick="document.getElementById('edit-vendor-modal').style.display='none'">Cancel</button>
                        <button type="submit" class="button button-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Pass PHP data to JS
        var indianStatesCities = <?php echo isset($json_data) ? $json_data : '{}'; ?>;
        </script>
    </div>
    <?php
}
