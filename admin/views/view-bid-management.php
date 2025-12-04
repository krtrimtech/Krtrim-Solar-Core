<?php
/**
 * View: Bid Management Page
 * 
 * Lists all bids across all projects, allowing admins to view and award them.
 */

if (!defined('ABSPATH')) {
    exit;
}

// 2. Render the page content
function sp_render_bid_management_page() {
    // Handle Award Action
    if (isset($_POST['action']) && $_POST['action'] === 'award_bid' && isset($_POST['bid_nonce']) && wp_verify_nonce($_POST['bid_nonce'], 'award_bid_action')) {
        $project_id = intval($_POST['project_id']);
        $vendor_id = intval($_POST['vendor_id']);
        $bid_amount = floatval($_POST['bid_amount']);
        
        // Call the API handler logic directly or via internal request
        // For simplicity in this view, we'll use the API handler class if available, or replicate logic
        // Ideally, we should use the SP_API_Handlers class.
        
        if (class_exists('SP_API_Handlers')) {
            // We need to simulate the POST data for the handler or call a helper method
            // Since the handler expects AJAX, we'll replicate the core logic here for the admin page submission
            
            update_post_meta($project_id, 'winning_vendor_id', $vendor_id);
            update_post_meta($project_id, 'winning_bid_amount', $bid_amount);
            update_post_meta($project_id, '_assigned_vendor_id', $vendor_id);
            update_post_meta($project_id, '_total_project_cost', $bid_amount); // Standardized key
            update_post_meta($project_id, 'project_status', 'assigned');
            
            // Notify Vendor
            $winning_vendor = get_userdata($vendor_id);
            $project_title = get_the_title($project_id);
            if ($winning_vendor) {
                $subject = 'Congratulations! You Won the Bid for Project: ' . $project_title;
                $message = "Congratulations! Your bid of ₹" . number_format($bid_amount, 2) . " for project '" . $project_title . "' has been accepted.";
                wp_mail($winning_vendor->user_email, $subject, $message);
            }
            
            echo '<div class="notice notice-success is-dismissible"><p>Project awarded successfully!</p></div>';
        }
    }

    // Get Projects that have bids
    global $wpdb;
    $bids_table = $wpdb->prefix . 'project_bids';
    $projects_table = $wpdb->posts;
    $users_table = $wpdb->users;

    $projects_with_bids = $wpdb->get_results(
        "SELECT DISTINCT p.ID, p.post_title, p.post_status, p.post_date 
         FROM {$projects_table} p 
         JOIN {$bids_table} b ON p.ID = b.project_id 
         ORDER BY p.post_date DESC"
    );

    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline">Bid Management</h1>
        <hr class="wp-header-end">

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 40%;">Project</th>
                    <th style="width: 15%;">Total Bids</th>
                    <th style="width: 15%;">Status</th>
                    <th style="width: 15%;">Created</th>
                    <th style="width: 15%;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($projects_with_bids)) : ?>
                    <?php foreach ($projects_with_bids as $project) : ?>
                        <?php 
                        $project_id = $project->ID;
                        $project_status = get_post_meta($project_id, 'project_status', true);
                        $assigned_vendor_id = get_post_meta($project_id, '_assigned_vendor_id', true);
                        $assigned_vendor_name = '';
                        
                        if (!empty($assigned_vendor_id) && $assigned_vendor_id > 0) {
                            $vendor = get_userdata($assigned_vendor_id);
                            $assigned_vendor_name = $vendor ? $vendor->display_name : 'Unknown';
                        }

                        // Get bids for this project
                        $bids = $wpdb->get_results($wpdb->prepare(
                            "SELECT b.*, u.display_name as vendor_name, u.user_email 
                             FROM {$bids_table} b 
                             JOIN {$users_table} u ON b.vendor_id = u.ID 
                             WHERE b.project_id = %d 
                             ORDER BY b.created_at DESC",
                            $project_id
                        ));
                        
                        $bid_count = count($bids);
                        ?>
                        
                        <!-- Project Row -->
                        <tr class="project-row" id="project-<?php echo $project_id; ?>">
                            <td>
                                <strong><a href="<?php echo get_edit_post_link($project_id); ?>"><?php echo esc_html($project->post_title); ?></a></strong>
                                <?php if ($assigned_vendor_name): ?>
                                    <br><small style="color: #28a745;">Awarded to: <?php echo esc_html($assigned_vendor_name); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo $bid_count; ?> Bids</span>
                            </td>
                            <td>
                                <?php if (!empty($assigned_vendor_id) && $assigned_vendor_id > 0): ?>
                                    <span style="color: #28a745; font-weight: bold;">Awarded</span>
                                <?php else: ?>
                                    <span style="color: #ffc107; font-weight: bold;">Open</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($project->post_date)); ?></td>
                            <td>
                                <button type="button" class="button toggle-bids-btn" data-project-id="<?php echo $project_id; ?>">Show Bids</button>
                            </td>
                        </tr>

                        <!-- Bids Row (Hidden by default) -->
                        <tr class="bids-row" id="bids-row-<?php echo $project_id; ?>" style="display: none; background-color: #f9f9f9;">
                            <td colspan="5" style="padding: 0;">
                                <div style="padding: 10px 20px;">
                                    <table class="wp-list-table widefat fixed striped" style="box-shadow: none; border: 1px solid #ddd;">
                                        <thead>
                                            <tr>
                                                <th>Vendor</th>
                                                <th>Bid Amount</th>
                                                <th>Type</th>
                                                <th>Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bids as $bid): ?>
                                                <?php 
                                                $is_winner = ($assigned_vendor_id == $bid->vendor_id);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php echo esc_html($bid->vendor_name); ?><br>
                                                        <small><?php echo esc_html($bid->user_email); ?></small>
                                                    </td>
                                                    <td><strong>₹<?php echo number_format($bid->bid_amount); ?></strong></td>
                                                    <td>
                                                        <?php if ($bid->bid_type === 'open'): ?>
                                                            <span style="color: green;">Open</span>
                                                        <?php else: ?>
                                                            <span style="color: orange;">Hidden</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($bid->created_at)); ?></td>
                                                    <td>
                                                        <?php if (empty($assigned_vendor_id) || $assigned_vendor_id <= 0): ?>
                                                            <form class="award-bid-form" method="post" style="display:inline;">
                                                                <input type="hidden" name="action" value="award_project_to_vendor">
                                                                <input type="hidden" name="project_id" value="<?php echo $bid->project_id; ?>">
                                                                <input type="hidden" name="vendor_id" value="<?php echo $bid->vendor_id; ?>">
                                                                <input type="hidden" name="bid_amount" value="<?php echo $bid->bid_amount; ?>">
                                                                <?php wp_nonce_field('award_bid_nonce', 'nonce'); ?>
                                                                <button type="submit" class="button button-primary button-small">Award</button>
                                                            </form>
                                                        <?php elseif ($is_winner): ?>
                                                            <span class="dashicons dashicons-awards" style="color: #28a745;"></span> <strong style="color: #28a745;">Winner</strong>
                                                        <?php else: ?>
                                                            <span style="color: #999;">Lost</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">No projects with bids found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}


