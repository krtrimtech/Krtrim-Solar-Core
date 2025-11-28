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
    </div>
    <?php
}
