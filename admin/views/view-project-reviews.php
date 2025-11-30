<?php
/**
 * Creates a custom admin page for reviewing vendor submissions on projects.
 * Based on user-provided code, refactored to use AJAX.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 2. Render the page content
function sp_render_project_reviews_page() {
    global $wpdb;
    
    // Enqueue admin scripts
    wp_enqueue_script('sp-admin-scripts');

    $current_user = wp_get_current_user();
    $is_area_manager = in_array('area_manager', $current_user->roles);
    
    $project_id = isset($_GET['project']) ? intval($_GET['project']) : 0;
    $steps_table = $wpdb->prefix . 'solar_process_steps';
    
    ?>
    <div class="wrap">
        <style>
            /* Container & Header */
            .review-container { max-width: 1200px; }
            .review-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
            .review-header h1 { margin: 0; font-size: 24px; }
            
            /* Enhanced Project Cards (List View) */
            .project-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; transition: transform 0.2s; }
            .project-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
            .project-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
            .project-card-header h3 { margin: 0; font-size: 18px; color: #333; }
            .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
            .status-badge.status-pending { background: #ffc107; color: #000; }
            .status-badge.status-in_progress { background: #2196F3; color: white; }
            .status-badge.status-completed { background: #4CAF50; color: white; }
            
            .project-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 15px; }
            .info-item { display: flex; gap: 10px; align-items: flex-start; }
           .info-item .icon { font-size: 20px; }
            .info-item .label { font-size: 11px; color: #888; text-transform: uppercase; font-weight: 600; }
            .info-item .value { font-size: 14px; font-weight: 600; color: #333; margin-top: 2px; }
            
            .project-card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #f0f0f0; }
            .pending-indicator { color: #ff9800; font-weight: 600; font-size: 13px; }
            .all-clear { color: #4CAF50; font-weight: 600; font-size: 13px; }
            .btn-review { background: #667eea; color: white; padding: 8px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; transition: background 0.2s; }
            .btn-review:hover { background: #5568d3; }
            
            /* Information Cards (Detail View) */
            .info-card { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
            .info-card h3 { margin: 0 0 15px 0; font-size: 16px; color: #333; font-weight: 600; }
            .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
            .card-item { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid #e0e0e0; }
            .card-item:last-child { border-bottom: none; }
            .card-item.full-width { grid-column: 1 / -1; }
            .card-label { font-weight: 600; min-width: 120px; color: #666; }
            .card-value { flex: 1; color: #333; }
            .card-value a { color: #667eea; text-decoration: none; }
            .card-value a:hover { text-decoration: underline; }
            
            /* Financial Card */
            .financial-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
            .financial-item { text-align: center; background: white; padding: 20px; border-radius: 8px; border: 2px solid #e0e0e0; }
            .financial-label { font-size: 12px; color: #888; text-transform: uppercase; font-weight: 600; margin-bottom: 8px; }
            .financial-value { font-size: 24px; font-weight: 700; color: #667eea; }
            
            /* Progress Bar */
            .progress-bar-container { margin-bottom: 20px; }
            .progress-bar { width: 100%; height: 12px; background: #e0e0e0; border-radius: 6px; overflow: hidden; margin-bottom: 8px; }
            .progress-fill { height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); transition: width 0.3s; }
            .progress-text { margin: 0; font-size: 13px; color: #666; text-align: center; }
            
            /* Steps Overview List */
            .steps-overview-list { margin-top: 15px; }
            .step-overview-item { display: flex; align-items: center; gap: 15px; padding: 12px; background: white; border-radius: 6px; margin-bottom: 8px; border-left: 4px solid #e0e0e0; }
            .step-overview-item.status-approved { border-left-color: #4CAF50; background: #f1f8f4; }
            .step-overview-item.status-pending { border-left-color: #ff9800; background: #fff8e1; }
            .step-overview-item.status-rejected { border-left-color: #f44336; background: #ffebee; }
            .step-number { width: 30px; height: 30px; background: #667eea; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; }
            .step-info-col { flex: 1; }
            .step-name { font-weight: 600; font-size: 14px; color: #333; }
            .step-meta { font-size: 12px; color: #888; margin-top: 2px; }
            .step-status-badge { padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
            .step-status-badge.approved { background: #4CAF50; color: white; }
            .step-status-badge.pending { background: #ff9800; color: white; }
            .step-status-badge.rejected { background: #f44336; color: white; }
            
            /* Legacy Styles (for submissions section) */
            .project-detail { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .back-btn { background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; margin-bottom: 20px; }
            .submission-toggle { border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 12px; }
            .toggle-header { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
            .toggle-left { display: flex; align-items: center; gap: 15px; }
            .toggle-icon { transition: transform 0.3s; }
            .toggle-title { font-size: 15px; font-weight: 600; }
            .toggle-content { display: none; padding: 20px; border-top: 1px solid #e0e0e0; }
            .submission-image { max-width: 400px; border-radius: 6px; margin: 15px 0; }
            .submission-comment { background: #f0f7ff; padding: 12px; border-left: 4px solid #007bff; border-radius: 4px; margin: 10px 0; }
            .review-form { margin-top: 15px; }
            .review-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 10px; }
            .btn-approve, .btn-reject { padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
            .btn-approve { background: #28a745; color: white; }
            .btn-reject { background: #dc3545; color: white; }
        </style>
        
        <div class="review-container">
                <?php if (!$project_id) : // PROJECT LIST VIEW ?>
                <div class="review-header"><h1>Project Reviews</h1></div>
                <div class="project-list">
                    <?php
                    $query_args = ['post_type' => 'solar_project', 'posts_per_page' => -1, 'post_status' => 'publish'];
                    if ($is_area_manager) {
                        $query_args['author'] = $current_user->ID;
                    }
                    $projects = get_posts($query_args);
                    
                    if (empty($projects)) {
                        echo '<p>No projects found.</p>';
                    } else {
                        foreach ($projects as $proj) {
                            $pid = $proj->ID;
                            
                            // Get client
                            $client_id = get_post_meta($pid, '_client_user_id', true);
                            $client = get_userdata($client_id);
                            
                            // Get vendor
                            $vendor_id = get_post_meta($pid, '_assigned_vendor_id', true);
                            $vendor = get_userdata($vendor_id);
                            
                            // Get project details
                            $total_cost = get_post_meta($pid, '_total_project_cost', true);
                            $status = get_post_meta($pid, 'project_status', true) ?: 'pending';
                            $state = get_post_meta($pid, '_project_state', true);
                            $city = get_post_meta($pid, '_project_city', true);
                            $system_size = get_post_meta($pid, '_solar_system_size_kw', true);
                            
                            // Calculate progress
                            $total_steps = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$steps_table} WHERE project_id = %d", $pid));
                            $approved_steps = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$steps_table} WHERE project_id = %d AND admin_status = 'approved'", $pid));
                            $progress = $total_steps > 0 ? round(($approved_steps / $total_steps) * 100) : 0;
                            
                            $pending_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$steps_table} WHERE project_id = %d AND admin_status = 'pending' AND image_url IS NOT NULL", $pid));
                            ?>
                            <div class="project-card">
                                <div class="project-card-header">
                                    <h3><?php echo esc_html($proj->post_title); ?></h3>
                                    <span class="status-badge status-<?php echo esc_attr($status); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                    </span>
                                </div>
                                
                                <div class="project-info-grid">
                                    <div class="info-item">
                                        <span class="icon">👤</span>
                                        <div>
                                            <div class="label">Client</div>
                                            <div class="value"><?php echo $client ? esc_html($client->display_name) : 'N/A'; ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="icon">🏢</span>
                                        <div>
                                            <div class="label">Vendor</div>
                                            <div class="value"><?php echo $vendor ? esc_html($vendor->display_name) : 'Not assigned'; ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="icon">📍</span>
                                        <div>
                                            <div class="label">Location</div>
                                            <div class="value"><?php echo esc_html($city . ($city && $state ? ', ' : '') . $state); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="icon">⚡</span>
                                        <div>
                                            <div class="label">System Size</div>
                                            <div class="value"><?php echo esc_html($system_size); ?> kW</div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="icon">💰</span>
                                        <div>
                                            <div class="label">Total Cost</div>
                                            <div class="value">₹<?php echo number_format($total_cost); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="icon">📊</span>
                                        <div>
                                            <div class="label">Progress</div>
                                            <div class="value"><?php echo $progress; ?>%</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="project-card-footer">
                                    <?php if ($pending_count > 0): ?>
                                        <span class="pending-indicator">🟡 <?php echo $pending_count; ?> pending review(s)</span>
                                    <?php else: ?>
                                        <span class="all-clear">✅ No pending reviews</span>
                                    <?php endif; ?>
                                    <a href="<?php echo admin_url('admin.php?page=project-reviews&project=' . $pid); ?>" class="btn-review">
                                        Review Project →
                                    </a>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
            <?php else : // PROJECT DETAIL VIEW ?>
                <?php
                $project = get_post($project_id);
                if ($is_area_manager && $project->post_author != $current_user->ID) {
                    wp_die('You do not have permission to view this project.');
                }
                
                // Get all necessary data
                $client_id = get_post_meta($project_id, '_client_user_id', true);
                $client = get_userdata($client_id);
                
                $vendor_id = get_post_meta($project_id, '_assigned_vendor_id', true);
                $vendor = get_userdata($vendor_id);
                
                $total_cost = get_post_meta($project_id, '_total_project_cost', true);
                $paid_amount = get_post_meta($project_id, '_paid_amount', true);
                
                ?>
                <button onclick="window.location.href='<?php echo admin_url('admin.php?page=project-reviews'); ?>'" class="back-btn">&larr; Back to Projects</button>
                <div class="project-detail">
                    <h2><?php echo esc_html($project->post_title); ?></h2>
                    
                    <!-- Client Information Card -->
                    <div class="info-card client-info-card">
                        <h3>👤 Client Information</h3>
                        <div class="card-grid">
                            <?php if ($client): ?>
                                <div class="card-item">
                                    <span class="card-label">Name:</span>
                                    <span class="card-value"><?php echo esc_html($client->display_name); ?></span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">Email:</span>
                                    <span class="card-value"><a href="mailto:<?php echo esc_attr($client->user_email); ?>"><?php echo esc_html($client->user_email); ?></a></span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">Phone:</span>
                                    <span class="card-value">
                                        <?php 
                                        $phone = get_user_meta($client_id, 'phone', true);
                                        echo $phone ? '<a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a>' : 'N/A';
                                        ?>
                                    </span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">Address:</span>
                                    <span class="card-value"><?php echo esc_html(get_post_meta($project_id, '_client_address', true)); ?></span>
                                </div>
                            <?php else: ?>
                                <p>No client assigned</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Vendor Information Card -->
                    <?php if ($vendor): ?>
                        <div class="info-card vendor-info-card">
                            <h3>🏢 Assigned Vendor</h3>
                            <div class="card-grid">
                                <div class="card-item">
                                    <span class="card-label">Name:</span>
                                    <span class="card-value"><?php echo esc_html($vendor->display_name); ?></span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">Email:</span>
                                    <span class="card-value"><a href="mailto:<?php echo esc_attr($vendor->user_email); ?>"><?php echo esc_html($vendor->user_email); ?></a></span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">Phone:</span>
                                    <span class="card-value">
                                        <?php 
                                        $vendor_phone = get_user_meta($vendor_id, 'phone', true);
                                        echo $vendor_phone ? '<a href="tel:' . esc_attr($vendor_phone) . '">' . esc_html($vendor_phone) . '</a>' : 'N/A';
                                        ?>
                                    </span>
                                </div>
                                <div class="card-item">
                                    <span class="card-label">Company:</span>
                                    <span class="card-value"><?php echo esc_html(get_user_meta($vendor_id, 'company_name', true)); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Project Overview Card -->
                    <div class="info-card project-overview-card">
                        <h3>📋 Project Overview</h3>
                        <div class="card-grid">
                            <div class="card-item">
                                <span class="card-label">Location:</span>
                                <span class="card-value"><?php echo esc_html(get_post_meta($project_id, '_project_city', true) . ', ' . get_post_meta($project_id, '_project_state', true)); ?></span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">System Size:</span>
                                <span class="card-value"><?php echo esc_html(get_post_meta($project_id, '_solar_system_size_kw', true)); ?> kW</span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">Status:</span>
                                <span class="card-value"><?php echo esc_html(get_post_meta($project_id, 'project_status', true)); ?></span>
                            </div>
                            <div class="card-item">
                                <span class="card-label">Start Date:</span>
                                <span class="card-value"><?php echo esc_html(get_post_meta($project_id, '_project_start_date', true)); ?></span>
                            </div>
                            <?php if ($project->post_content): ?>
                                <div class="card-item full-width">
                                    <span class="card-label">Description:</span>
                                    <span class="card-value"><?php echo wp_kses_post($project->post_content); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Financial Summary Card -->
                    <div class="info-card financial-card">
                        <h3>💰 Financial Summary</h3>
                        <div class="financial-grid">
                            <div class="financial-item">
                                <div class="financial-label">Total Cost</div>
                                <div class="financial-value">₹<?php echo number_format($total_cost); ?></div>
                            </div>
                            <div class="financial-item">
                                <div class="financial-label">Paid by Client</div>
                                <div class="financial-value">₹<?php echo number_format($paid_amount); ?></div>
                            </div>
                            <div class="financial-item">
                                <div class="financial-label">Balance Due</div>
                                <div class="financial-value">₹<?php echo number_format($total_cost - $paid_amount); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Complete Process Steps Overview -->
                    <div class="info-card steps-overview-card">
                        <h3>📋 Process Steps Overview</h3>
                        <?php
                        $all_steps = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM {$steps_table} WHERE project_id = %d ORDER BY step_number ASC",
                            $project_id
                        ));
                        
                        if ($all_steps):
                            $total = count($all_steps);
                            $approved = count(array_filter($all_steps, fn($s) => $s->admin_status === 'approved'));
                            $progress_pct = round(($approved / $total) * 100);
                        ?>
                            <div class="progress-bar-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress_pct; ?>%"></div>
                                </div>
                                <p class="progress-text"><?php echo $approved; ?> of <?php echo $total; ?> steps approved (<?php echo $progress_pct; ?>%)</p>
                            </div>
                            
                            <div class="steps-overview-list">
                                <?php foreach ($all_steps as $step): ?>
                                    <div class="step-overview-item status-<?php echo esc_attr($step->admin_status); ?>">
                                        <div class="step-number"><?php echo $step->step_number; ?></div>
                                        <div class="step-info-col">
                                            <div class="step-name"><?php echo esc_html($step->step_name); ?></div>
                                            <div class="step-meta">
                                                <?php if ($step->image_url): ?>
                                                    📸 Submitted
                                                <?php else: ?>
                                                    ⏳ Not submitted yet
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="step-status-badge <?php echo esc_attr($step->admin_status); ?>">
                                            <?php echo ucfirst($step->admin_status); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="submissions-section">
                        <h3>Vendor Submissions</h3>
                        <?php
                        $submissions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$steps_table} WHERE project_id = %d AND image_url IS NOT NULL ORDER BY step_number ASC", $project_id));
                        if (empty($submissions)) {
                            echo '<p>No vendor submissions yet.</p>';
                        } else {
                            foreach ($submissions as $submission) {
                                ?>
                                <div class="submission-toggle <?php echo esc_attr($submission->admin_status); ?>">
                                    <div class="toggle-header" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block';">
                                        <div class="toggle-left">
                                            <span class="toggle-icon">&rtrif;</span>
                                            <div class="toggle-title">Step <?php echo $submission->step_number; ?>: <?php echo esc_html($submission->step_name); ?></div>
                                        </div>
                                        <span class="status-badge <?php echo esc_attr($submission->admin_status); ?>"><?php echo $submission->admin_status === 'pending' ? 'Under Review' : ucfirst($submission->admin_status); ?></span>
                                    </div>
                                    <div class="toggle-content">
                                        <img src="<?php echo esc_url($submission->image_url); ?>" class="submission-image" alt="Submission Image">
                                        <div class="submission-comment"><strong>Vendor Comment:</strong> <?php echo esc_html($submission->vendor_comment); ?></div>
                                        
                                        <?php if ($submission->admin_status === 'pending') : ?>
                                            <div class="review-form">
                                                <input type="text" class="review-input" placeholder="Add approval/rejection reason...">
                                                <button type="button" class="btn-approve review-btn" data-decision="approved" data-step-id="<?php echo $submission->id; ?>">Approve</button>
                                                <button type="button" class="btn-reject review-btn" data-decision="rejected" data-step-id="<?php echo $submission->id; ?>">Reject</button>
                                            </div>
                                        <?php else: ?>
                                            <div class="submission-comment"><strong>Admin Comment:</strong> <?php echo esc_html($submission->admin_comment); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>
                <?php wp_nonce_field('sp_review_nonce', 'sp_review_nonce_field'); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
