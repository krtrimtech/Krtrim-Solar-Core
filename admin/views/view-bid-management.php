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

    // Pagination
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $offset = ($paged - 1) * $per_page;

    global $wpdb;
    $bids_table = $wpdb->prefix . 'project_bids';
    $projects_table = $wpdb->posts;
    $users_table = $wpdb->users;

    // Get Total Count
    $total_bids = $wpdb->get_var("SELECT COUNT(*) FROM {$bids_table}");
    $total_pages = ceil($total_bids / $per_page);

    // Get Bids
    $bids = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, p.post_title, p.ID as project_id, u.display_name as vendor_name, u.user_email 
        FROM {$bids_table} b 
        JOIN {$projects_table} p ON b.project_id = p.ID 
        JOIN {$users_table} u ON b.vendor_id = u.ID 
        ORDER BY b.created_at DESC 
        LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline">Bid Management</h1>
        <hr class="wp-header-end">

        <div class="tablenav top">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo $total_bids; ?> items</span>
                <?php if ($total_pages > 1): ?>
                    <span class="pagination-links">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $paged
                        ]);
                        ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped table-view-list">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-primary">Project</th>
                    <th scope="col" class="manage-column">Vendor</th>
                    <th scope="col" class="manage-column">Bid Amount</th>
                    <th scope="col" class="manage-column">Type</th>
                    <th scope="col" class="manage-column">Date</th>
                    <th scope="col" class="manage-column">Status</th>
                    <th scope="col" class="manage-column">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($bids)) : ?>
                    <?php foreach ($bids as $bid) : ?>
                        <?php 
                        $project_status = get_post_meta($bid->project_id, 'project_status', true);
                        $assigned_vendor = get_post_meta($bid->project_id, '_assigned_vendor_id', true);
                        $is_winner = ($assigned_vendor == $bid->vendor_id);
                        ?>
                        <tr>
                            <td class="column-primary">
                                <strong><a href="<?php echo get_edit_post_link($bid->project_id); ?>"><?php echo esc_html($bid->post_title); ?></a></strong>
                            </td>
                            <td>
                                <?php echo esc_html($bid->vendor_name); ?><br>
                                <small><?php echo esc_html($bid->user_email); ?></small>
                            </td>
                            <td>
                                <strong>₹<?php echo number_format($bid->bid_amount); ?></strong>
                            </td>
                            <td>
                                <?php if ($bid->bid_type === 'open'): ?>
                                    <span class="badge badge-success" style="background:#d4edda; color:#155724; padding:2px 6px; border-radius:4px; font-size:11px;">Open</span>
                                <?php else: ?>
                                    <span class="badge badge-warning" style="background:#fff3cd; color:#856404; padding:2px 6px; border-radius:4px; font-size:11px;">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($bid->created_at)); ?></td>
                            <td>
                                <?php if ($is_winner): ?>
                                    <span class="dashicons dashicons-awards" style="color: #28a745;"></span> <strong style="color: #28a745;">Winner</strong>
                                <?php elseif ($assigned_vendor): ?>
                                    <span style="color: #999;">Lost</span>
                                <?php else: ?>
                                    <span style="color: #ffc107;">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$assigned_vendor): ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to award this project to <?php echo esc_js($bid->vendor_name); ?>?');">
                                        <input type="hidden" name="action" value="award_bid">
                                        <input type="hidden" name="project_id" value="<?php echo $bid->project_id; ?>">
                                        <input type="hidden" name="vendor_id" value="<?php echo $bid->vendor_id; ?>">
                                        <input type="hidden" name="bid_amount" value="<?php echo $bid->bid_amount; ?>">
                                        <?php wp_nonce_field('award_bid_action', 'bid_nonce'); ?>
                                        <button type="submit" class="button button-primary button-small">Award</button>
                                    </form>
                                <?php elseif ($is_winner): ?>
                                    <button class="button button-disabled" disabled>Awarded</button>
                                <?php else: ?>
                                    <button class="button button-disabled" disabled>Closed</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7">No bids found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}


