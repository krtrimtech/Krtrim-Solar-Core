<?php

function render_solar_vendor_dashboard() {
    $current_user = wp_get_current_user();
    $vendor_id = get_current_user_id();
    $view_project_id = isset($_GET['view_project']) ? intval($_GET['view_project']) : 0;
    
    // Get vendor coverage data (needed for both display and JavaScript)
    $purchased_states = get_user_meta($vendor_id, 'purchased_states', true) ?: [];
    $purchased_cities = get_user_meta($vendor_id, 'purchased_cities', true) ?: [];
    
    $args = array(
        'post_type' => 'solar_project',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'assigned_vendor_id',
                'value' => $vendor_id,
            ),
            array(
                'key' => 'project_status',
                'value' => 'cancelled',
                'compare' => '!='
            )
        ),
    );
    
    $vendor_projects = new WP_Query($args);
    
    $total_received = 0;
    $total_working = 0;
    $total_completed = 0;
    
    if ($vendor_projects->have_posts()) {
        while ($vendor_projects->have_posts()) {
            $vendor_projects->the_post();
            $paid_to_vendor = floatval(get_post_meta(get_the_ID(), '_paid_to_vendor', true));
            $total_received += $paid_to_vendor;
            $project_status = get_post_meta(get_the_ID(), '_project_status', true);
            if ($project_status === 'in_progress') {
                $total_working++;
            } elseif ($project_status === 'completed') {
                $total_completed++;
            }
        }
        wp_reset_postdata();
    }
    
    // ✅ DETERMINE WHICH SECTION TO SHOW
    $show_projects = $view_project_id ? true : false;
    $dashboard_display = $show_projects ? 'none' : 'block';
    $projects_display = $show_projects ? 'block' : 'none';
    
    ?>

    <div class="modern-vendor-dashboard" id="vendorDashboard">
        <!-- Left Sidebar -->
        <aside class="dashboard-sidebar">
            <div class="sidebar-brand">
                <?php
                if ( has_custom_logo() ) {
                    echo get_custom_logo();
                } else {
                    echo '<div class="logo">👷</div>';
                    echo '<span>' . get_bloginfo('name') . '</span>';
                }
                ?>
                <button class="sidebar-toggle-mobile" onclick="toggleSidebar()" title="Toggle Menu">☰</button>
            </div>
            
            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" data-section="dashboard" onclick="switchVendorSection(event, 'dashboard')">
                    <span class="icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="nav-item" data-section="projects" onclick="switchVendorSection(event, 'projects')">
                    <span class="icon">📂</span>
                    <span>Projects</span>
                </a>
                <a href="#" class="nav-item" data-section="timeline" onclick="switchVendorSection(event, 'timeline')">
                    <span class="icon">🔄</span>
                    <span>Work Timeline</span>
                </a>
                <a href="#" class="nav-item" data-section="profile" onclick="switchVendorSection(event, 'profile')">
                    <span class="icon">👤</span>
                    <span>Profile & Coverage</span>
                </a>
            </nav>
            
            <!-- User Profile -->
            <div class="sidebar-profile">
                <img src="<?php echo esc_url(get_avatar_url($current_user->ID)); ?>" alt="<?php echo esc_html($current_user->display_name); ?>">
                <div class="profile-info">
                    <h4><?php echo esc_html($current_user->display_name); ?></h4>
                    <p>Vendor</p>
                </div>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="logout-btn" title="Logout">🚪</a>
            </div>
        </aside>
        
        <!-- Mobile Floating Toggle Button -->
        <button class="mobile-sidebar-toggle-floating" onclick="toggleSidebar()" title="Toggle Menu">☰</button>
        
        <!-- Main Content -->
        <main class="dashboard-main">
            
            <!-- Header -->
            <div class="dashboard-header-top">
                <div class="header-left">
                    <h1 id="section-title">Dashboard</h1>
                </div>
                <div class="header-right">
                    <button class="notification-badge" onclick="toggleNotificationPanel()">
                        <span class="icon">🔔</span>
                        <span class="badge-count" id="notif-count">0</span>
                    </button>
                </div>
            </div>
            
            <!-- Notification Panel -->
            <div class="notification-panel" id="notificationPanel">
                <div class="notification-header">
                    <h3>Updates</h3>
                    <button onclick="toggleNotificationPanel()" class="close-btn">✕</button>
                </div>
                <div class="notification-list" id="notif-list">
                    <p style="text-align: center; color: #999; padding: 20px;">⏳ Loading...</p>
                </div>
            </div>
            
            <!-- Dashboard Content -->
            <div class="dashboard-content">
                
                <!-- DASHBOARD SECTION -->
                <div class="section-content" id="dashboard-section" style="display: <?php echo $dashboard_display; ?>;">
                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <span class="stat-label">Total Received</span>
                                <span class="stat-icon">💰</span>
                            </div>
                            <div class="stat-value">₹<?php echo number_format($total_received, 0); ?></div>
                            <div class="stat-subtitle">Earnings</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <span class="stat-label">In Progress</span>
                                <span class="stat-icon">⚙️</span>
                            </div>
                            <div class="stat-value"><?php echo $total_working; ?></div>
                            <div class="stat-subtitle">Active Projects</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <span class="stat-label">Completed</span>
                                <span class="stat-icon">✅</span>
                            </div>
                            <div class="stat-value"><?php echo $total_completed; ?></div>
                            <div class="stat-subtitle">Projects Done</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <span class="stat-label">Total Projects</span>
                                <span class="stat-icon">📂</span>
                            </div>
                            <div class="stat-value"><?php echo $vendor_projects->found_posts; ?></div>
                            <div class="stat-subtitle">All Time</div>
                        </div>
                    </div>
                    
                    <!-- Main Content Grid -->
                    <div class="content-grid">
                        <!-- Left Column -->
                        <div class="content-left">
                            <!-- Earnings Chart -->
                            <div class="card">
                                <div class="card-header">
                                    <h3>💰 Earnings Overview</h3>
                                </div>
                                <div class="earnings-chart">
                                    <canvas id="earnings-chart"></canvas>
                                </div>
                            </div>
                            <!-- Summary Card -->
                            <div class="card">
                                <div class="card-header">
                                    <h3>📊 Overview</h3>
                                </div>
                                <div class="overview-items">
                                    <div class="overview-item">
                                        <div class="overview-label">Total Earnings</div>
                                        <div class="overview-value">₹<?php echo number_format($total_received, 2); ?></div>
                                    </div>
                                    <div class="overview-item">
                                        <div class="overview-label">Active Work</div>
                                        <div class="overview-value"><?php echo $total_working; ?> projects</div>
                                    </div>
                                    <div class="overview-item">
                                        <div class="overview-label">Completed</div>
                                        <div class="overview-value"><?php echo $total_completed; ?> projects</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="content-right">
                            <!-- Quick Actions Card -->
                            <div class="card upgrade-card">
                                <div class="upgrade-content">
                                    <h3>Quick Access</h3>
                                    <p>View your assigned projects and work updates.</p>
                                    <button class="btn btn-primary" onclick="switchVendorSection(null, 'projects')">View Projects →</button>
                                </div>
                            </div>
                            
                            <!-- Support Card -->
                            <div class="card support-card">
                                <div class="support-icon">📞</div>
                                <h3>Need Help?</h3>
                                <p>Contact our support team</p>
                                <a href="mailto:<?php echo get_option('admin_email'); ?>" class="btn btn-secondary">Contact Support</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- PROJECTS SECTION -->
                <div class="section-content" id="projects-section" style="display: <?php echo $projects_display; ?>;">
                    <?php if ($view_project_id) : ?>
                        <!-- BACK BUTTON -->
                        <button class="back-btn" onclick="backToProjectsList()" style="margin-bottom: 20px;">← Back to Projects</button>
                        
                        <?php
                        $project = get_post($view_project_id);
                        if ($project && $project->post_type === 'solar_project') :
                            $assigned_vendor = get_post_meta($view_project_id, 'assigned_vendor_id', true);
                            
                            $has_access = false;
                            if (is_array($assigned_vendor)) {
                                foreach ($assigned_vendor as $v) {
                                    $vid = is_object($v) ? $v->ID : intval($v);
                                    if ($vid == $vendor_id) {
                                        $has_access = true;
                                        break;
                                    }
                                }
                            } else {
                                $vid = is_object($assigned_vendor) ? $assigned_vendor->ID : intval($assigned_vendor);
                                $has_access = ($vid == $vendor_id);
                            }
                            
                            if (!$has_access) {
                                echo '<div class="card" style="color: #dc3545; text-align: center; padding: 40px;">
                                    <p>Access denied.</p>
                                </div>';
                            } else {
                                $client_id = get_post_meta($view_project_id, '_client_user_id', true);
                                $paid_to_vendor = floatval(get_post_meta($view_project_id, '_paid_to_vendor', true));
                                $system_size = get_post_meta($view_project_id, '_solar_system_size_kw', true);
                                $status = get_post_meta($view_project_id, '_project_status', true);
                                $total_cost = get_post_meta($view_project_id, '_total_project_cost', true);
                                $client_address = get_post_meta($view_project_id, '_client_address', true);
                                $client_phone = get_post_meta($view_project_id, '_client_phone_number', true);
                                
                                if (is_object($client_id)) $client_id = $client_id->ID;
                                $client = get_userdata($client_id);
                        ?>
                        
                        <!-- Project Header -->
                        <div class="card">
                            <div class="card-header">
                                <h3><?php echo get_the_title($view_project_id); ?></h3>
                                <span class="badge" style="background: #667eea; color: white; padding: 6px 12px; border-radius: 6px;"><?php echo ucfirst(str_replace('_', ' ', $status)); ?></span>
                            </div>
                            
                            <!-- Project Stats -->
                            <div class="project-stats-grid">
                                <div class="project-stat">
                                    <span class="stat-label">Your Payment</span>
                                    <span class="stat-amount">₹<?php echo number_format($paid_to_vendor, 2); ?></span>
                                </div>
                                <div class="project-stat">
                                    <span class="stat-label">System Size</span>
                                    <span class="stat-amount"><?php echo $system_size ?: 'N/A'; ?> kW</span>
                                </div>
                                <div class="project-stat">
                                    <span class="stat-label">Total Cost</span>
                                    <span class="stat-amount">₹<?php echo number_format($total_cost, 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Client Information -->
                        <div class="card">
                            <div class="card-header">
                                <h3>👨‍💼 Client Information</h3>
                            </div>
                            <div class="client-info-grid">
                                <div class="client-info-item">
                                    <span class="info-label">Name</span>
                                    <span class="info-value"><?php echo $client ? esc_html($client->display_name) : 'N/A'; ?></span>
                                </div>
                                <div class="client-info-item">
                                    <span class="info-label">Email</span>
                                    <span class="info-value"><?php echo $client ? esc_html($client->user_email) : 'N/A'; ?></span>
                                </div>
                                <div class="client-info-item">
                                    <span class="info-label">Address</span>
                                    <span class="info-value"><?php echo esc_html($client_address ?: 'N/A'); ?></span>
                                </div>
                                <div class="client-info-item">
                                    <span class="info-label">Phone</span>
                                    <span class="info-value"><?php echo esc_html($client_phone ?: 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Process Steps -->
                        <div class="card">
                            <div class="card-header">
                                <h3>📂 Project Process Steps</h3>
                            </div>
                            
                            <?php
                            global $wpdb;
                            $steps_table = $wpdb->prefix . 'solar_process_steps';

                            $steps = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$steps_table} WHERE project_id = %d ORDER BY step_number ASC",
                                $view_project_id
                            ));

                            if ($steps && is_array($steps) && count($steps) > 0) :
                                $total_steps = count($steps);
                                $approved_steps = 0;

                                foreach ($steps as $step) {
                                    if ($step->admin_status === 'approved') {
                                        $approved_steps++;
                                    }
                                }

                                $progress_percent = ($total_steps > 0) ? ($approved_steps / $total_steps) * 100 : 0;
                            ?>

                            <div class="progress-text">📊 Progress: <?php echo $approved_steps; ?> of <?php echo $total_steps; ?> steps completed (<?php echo round($progress_percent); ?>%)</div>
                            <div class="progress-bar-wrap">
                                <div class="progress-bar" style="width: <?php echo $progress_percent; ?>%"></div>
                            </div>

                            <div class="steps-container">
                                <?php
                                $previous_approved = true;

                                foreach ($steps as $step_index => $step) :
                                    $is_locked = !$previous_approved;
                                    $step_class = $is_locked ? 'locked' : $step->admin_status;
                                    $submitted_date = $step->created_at ? wp_date('M d, Y H:i', strtotime($step->created_at)) : 'N/A';
                                ?>
                                    <div class="step-card <?php echo $step_class; ?>">
                                        <!-- Step Header -->
                                        <div class="step-card-header" onclick="toggleStepDetail(<?php echo $step->id; ?>)" style="cursor: pointer;">
                                            <div class="step-info">
                                                <div class="step-number-badge">
                                                    <?php if ($is_locked) echo '🔒'; elseif ($step->admin_status === 'approved') echo '✓'; else echo $step->step_number; ?>
                                                </div>
                                                <div>
                                                    <div class="step-title"><?php echo esc_html($step->step_name); ?></div>
                                                    <div class="step-meta">
                                                        <?php 
                                                        if ($is_locked) echo 'Locked until previous step approved';
                                                        elseif ($step->admin_status === 'approved') echo '✅ Approved';
                                                        elseif ($step->admin_status === 'rejected') echo '❌ Rejected';
                                                        else echo '⏳ Pending review';
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="step-status-badge" style="background: 
                                                <?php 
                                                if ($is_locked) echo '#ccc';
                                                elseif ($step->admin_status === 'approved') echo '#28a745';
                                                elseif ($step->admin_status === 'rejected') echo '#dc3545';
                                                else echo '#ffc107';
                                                ?>
                                            ">
                                                <?php if ($is_locked) echo 'Locked'; else echo ucfirst($step->admin_status); ?>
                                            </span>
                                        </div>

                                        <!-- Step Content -->
                                        <div class="step-card-content" id="step-content-<?php echo $step->id; ?>" style="display: none;">
                                            <?php if (!$is_locked) : ?>
                                                
                                                <!-- SHOW SUBMITTED WORK -->
                                                <?php if ($step->image_url && ($step->admin_status === 'pending' || $step->admin_status === 'rejected')) : ?>
                                                    <div style="background: #f0f7ff; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff; margin-bottom: 15px;">
                                                        <strong>📂 Your Submission</strong>
                                                        <div style="margin-top: 10px;">
                                                            <?php if ($step->image_url) : ?>
                                                                <img src="<?php echo esc_url($step->image_url); ?>" alt="Your submission" style="max-width: 300px; border-radius: 6px; margin-bottom: 10px; cursor: pointer;" onclick="openImageModal(this.src)">
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($step->vendor_comment) : ?>
                                                                <div style="background: white; padding: 10px; border-radius: 6px; margin-bottom: 10px;">
                                                                    <strong>Your Comment:</strong><br>
                                                                    <?php echo esc_html($step->vendor_comment); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($step->admin_status === 'pending') : ?>
                                                                <div style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 6px; margin-top: 10px; border-left: 4px solid #ffc107;">
                                                                    ⏳ <strong>Waiting for Admin Review</strong><br>
                                                                    <small>Your submission is under review. Please wait for admin approval or rejection.</small>
                                                                </div>
                                                            <?php elseif ($step->admin_status === 'rejected') : ?>
                                                                <div style="background: #fff5f5; color: #721c24; padding: 12px; border-radius: 6px; margin-top: 10px; border-left: 4px solid #dc3545;">
                                                                    ❌ <strong>Submission Rejected</strong><br>
                                                                    <strong>Reason:</strong> <?php echo esc_html($step->admin_comment); ?><br>
                                                                    <small style="margin-top: 8px; display: block;">You can re-submit below</small>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- UPLOAD FORM -->
                                                <?php if ($step->admin_status === 'rejected' || ($step->admin_status === 'pending' && !$step->image_url)) : ?>
                                                    <form class="ajax-upload-form" data-step-id="<?php echo $step->id; ?>" data-project-id="<?php echo $view_project_id; ?>" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #f0f0f0;">
                                                        <?php wp_nonce_field('solar_upload_' . $step->id, 'solar_nonce'); ?>

                                                        <div class="form-group">
                                                            <label>Upload Image *</label>
                                                            <input type="file" name="step_image" accept="image/*" required>
                                                            <small>Max 5MB</small>
                                                        </div>

                                                        <div class="form-group">
                                                            <label>Comment *</label>
                                                            <textarea name="vendor_comment" rows="3" placeholder="Describe the work..." required></textarea>
                                                        </div>

                                                        <button type="submit" class="btn btn-upload">📂 <?php echo $step->admin_status === 'rejected' ? 'Re-Submit' : 'Submit'; ?></button>
                                                        <div class="upload-status"></div>
                                                    </form>
                                                <?php elseif ($step->admin_status === 'pending' && $step->image_url) : ?>
                                                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px dashed #ddd; text-align: center; color: #666;">
                                                        ✅ <strong>Already Submitted</strong><br>
                                                        <small>You have already submitted this step. Please wait for admin review before re-submitting.</small>
                                                    </div>
                                                <?php endif; ?>

                                            <?php else : ?>
                                                <div class="lock-message">🔒 This step is locked. Please complete Step <?php echo $step->step_number - 1; ?> first and get it approved.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php
                                    $previous_approved = ($step->admin_status === 'approved');
                                endforeach;
                                ?>
                            </div>

                            <?php else : ?>
                                <p style="color: #666; text-align: center;">No steps configured yet.</p>
                            <?php endif; ?>
                        </div>

                        <?php 
                        }
                        endif; 
                        ?>
                    
                    <?php elseif ($vendor_projects->have_posts()) : ?>
                        <!-- Projects Grid -->
                        <div class="projects-grid">
                            <?php
                            while ($vendor_projects->have_posts()) : $vendor_projects->the_post();
                                $project_id = get_the_ID();
                                $paid_to_vendor = floatval(get_post_meta($project_id, '_paid_to_vendor', true));
                                $status = get_post_meta($project_id, '_project_status', true);
                                $system_size = get_post_meta($project_id, '_solar_system_size_kw', true);
                                $client_id = get_post_meta($project_id, '_client_user_id', true);
                                if (is_object($client_id)) $client_id = $client_id->ID;
                                $client = get_userdata($client_id);
                                
                                $is_pending = ($status === 'pending');
                                $card_class = $is_pending ? 'pending-project' : 'accepted-project';
                            ?>
                                <div class="project-card <?php echo $card_class; ?>" data-project-id="<?php echo $project_id; ?>" data-status="<?php echo $status; ?>">
                                    <div class="project-card-content" <?php if (!$is_pending) echo 'onclick="goToProject(' . $project_id . ')"'; ?>>
                                        <?php if ($is_pending) : ?>
                                            <div class="pending-notice">⏳ PENDING ACCEPTANCE</div>
                                        <?php endif; ?>
                                        
                                        <h3><?php the_title(); ?></h3>
                                        <div class="project-info"><strong>Client:</strong> <?php echo $client ? esc_html($client->display_name) : 'N/A'; ?></div>
                                        <div class="project-info"><strong>Status:</strong> <span class="status-badge" style="background: <?php 
                                            if ($status === 'in_progress') echo '#007bff';
                                            elseif ($status === 'completed') echo '#28a745';
                                            else echo '#ffc107'; ?>; color: white;">
                                            <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                        </span></div>
                                        <div class="project-info"><strong>System:</strong> <?php echo $system_size ?: 'N/A'; ?> kW</div>
                                        <div class="project-info" style="margin-top: 10px; color: #28a745; font-weight: 700;">💰 ₹<?php echo number_format($paid_to_vendor, 2); ?></div>
                                    </div>
                                    
                                    <div class="project-card-footer">
                                        <?php if ($is_pending) : ?>
                                            <div class="card-buttons" data-project-id="<?php echo $project_id; ?>">
                                                <button type="button" class="btn-accept-card btn-vendor-action" data-action="accept" data-nonce="<?php echo wp_create_nonce('vendor_accept_project_' . $project_id); ?>">✅ Accept</button>
                                                <button type="button" class="btn-decline-card btn-vendor-action" data-action="decline" data-nonce="<?php echo wp_create_nonce('vendor_accept_project_' . $project_id); ?>">❌ Decline</button>
                                            </div>
                                        <?php else : ?>
                                            <div class="card-buttons">
                                                <button type="button" class="btn-view-card" onclick="goToProject(<?php echo $project_id; ?>)">👁️‍🗨️ View Details</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <div class="no-projects">
                            <div class="empty-icon">📦</div>
                            <h2>No Projects Yet</h2>
                            <p>You don't have any assigned projects.</p>
                        </div>
                    <?php endif; ?>
                    <?php wp_reset_postdata(); ?>
                </div>
                
                <!-- TIMELINE SECTION -->
                <div class="section-content" id="timeline-section" style="display: none;">
                    <?php if ($vendor_projects->have_posts()) : ?>
                        <div class="timeline-container">
                            <?php
                            while ($vendor_projects->have_posts()) : $vendor_projects->the_post();
                                $project_id = get_the_ID();
                                
                                global $wpdb;
                                $table = $wpdb->prefix . 'solar_process_steps';
                                $steps = $wpdb->get_results($wpdb->prepare(
                                    "SELECT * FROM $table WHERE project_id = %d ORDER BY step_number ASC",
                                    $project_id
                                ));
                            ?>
                                <div class="timeline-project-section">
                                    <h3 style="margin-bottom: 20px; color: #333; font-size: 18px;">📂 <?php the_title(); ?></h3>
                                    
                                    <div class="timeline-items">
                                        <?php foreach ($steps as $step_index => $step) : ?>
                                            <div class="timeline-item <?php echo $step->admin_status; ?>">
                                                <div class="timeline-marker">
                                                    <?php if ($step->admin_status === 'approved') echo '✓';
                                                    elseif ($step->admin_status === 'rejected') echo '✕';
                                                    else echo $step->step_number; ?>
                                                </div>
                                                <div class="timeline-content">
                                                    <h4><?php echo esc_html($step->step_name); ?></h4>
                                                    <p style="font-size: 12px; color: #666; margin: 5px 0;">
                                                        Status: <strong><?php echo ucfirst($step->admin_status); ?></strong>
                                                    </p>
                                                    <?php if ($step->vendor_comment) : ?>
                                                        <p style="font-size: 12px; margin: 5px 0;"><?php echo esc_html($step->vendor_comment); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else : ?>
                        <div class="no-projects">
                            <div class="empty-icon">📦</div>
                            <h2>No Timeline</h2>
                            <p>You don't have any projects to show timeline for.</p>
                        </div>
                    <?php endif; ?>
                    <?php wp_reset_postdata(); ?>
                </div>

                <!-- PROFILE & COVERAGE SECTION -->
                <div class="section-content" id="profile-section" style="display: none;">
                    <div class="profile-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        
                        <!-- Profile Settings -->
                        <div class="card" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                            <h3>Profile Settings</h3>
                            <form id="vendor-profile-form">
                                <p>
                                    <label>Company Name</label><br>
                                    <input type="text" id="profile-company" class="form-control" value="<?php echo esc_attr(get_user_meta($vendor_id, 'company_name', true)); ?>" required style="width:100%; padding:8px; margin-top:5px;">
                                </p>
                                <p>
                                    <label>Phone</label><br>
                                    <input type="text" id="profile-phone" class="form-control" value="<?php echo esc_attr(get_user_meta($vendor_id, 'phone', true)); ?>" required style="width:100%; padding:8px; margin-top:5px;">
                                </p>
                                <button type="submit" class="button button-primary" style="margin-top:10px;">Update Profile</button>
                            </form>
                        </div>

                        <!-- Current Coverage -->
                        <div class="card" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                            <h3>My Coverage Area</h3>
                            <div style="margin-bottom: 15px;">
                                <strong>States (<?php echo count($purchased_states); ?>):</strong>
                                <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px;">
                                    <?php foreach($purchased_states as $state): ?>
                                        <span style="background: #eef2f7; padding: 4px 8px; border-radius: 4px; font-size: 12px;"><?php echo esc_html($state); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div>
                                <strong>Cities (<?php echo count($purchased_cities); ?>):</strong>
                                <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px;">
                                    <?php foreach($purchased_cities as $city): ?>
                                        <span style="background: #eef2f7; padding: 4px 8px; border-radius: 4px; font-size: 12px;"><?php echo esc_html($city); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Add Coverage -->
                        <div class="card" style="grid-column: 1 / -1; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <h3 style="margin-bottom: 5px;">Expand Coverage Area</h3>
                            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Grow your business by adding more states or specific cities.</p>
                            
                            <div class="coverage-expansion-grid" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px;">
                                
                                <!-- Left: Selection -->
                                <div class="selection-panel">
                                    
                                    <!-- Step 1: Select State -->
                                    <div class="form-group" style="margin-bottom: 20px;">
                                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">1. Select State</label>
                                        <select id="coverage-state-select" class="form-control" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ddd;">
                                            <option value="">-- Choose a State --</option>
                                            <!-- Populated via JS -->
                                        </select>
                                    </div>

                                    <!-- Step 2: State Options (Hidden by default) -->
                                    <div id="state-options-container" style="display: none; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #eee;">
                                        <div id="buy-state-option" style="margin-bottom: 15px;">
                                            <label class="custom-checkbox" style="display: flex; align-items: center; cursor: pointer;">
                                                <input type="checkbox" id="buy-state-checkbox" style="width: 18px; height: 18px; margin-right: 10px;">
                                                <span>
                                                    <strong>Buy Entire State Coverage</strong>
                                                    <span style="display: block; font-size: 12px; color: #666;">Includes all current and future cities in this state.</span>
                                                </span>
                                                <span style="margin-left: auto; font-weight: 700; color: #2c3e50;">₹500</span>
                                            </label>
                                        </div>
                                        <div id="owned-state-msg" style="display: none; color: #28a745; font-weight: 600; margin-bottom: 15px;">
                                            ✅ You already own this state.
                                        </div>
                                    </div>

                                    <!-- Step 3: City Selection -->
                                    <div id="city-selection-container" style="display: none;">
                                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">2. Select Specific Cities (Optional)</label>
                                        <div id="city-checkboxes" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; max-height: 300px; overflow-y: auto; padding: 10px; border: 1px solid #eee; border-radius: 6px;">
                                            <!-- Populated via JS -->
                                        </div>
                                        <small style="display: block; margin-top: 5px; color: #888;">Cities are ₹100 each.</small>
                                    </div>

                                </div>

                                <!-- Right: Summary/Cart -->
                                <div class="summary-panel" style="background: #f8f9fa; padding: 20px; border-radius: 10px; border: 1px solid #eee; height: fit-content;">
                                    <h4 style="margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">Order Summary</h4>
                                    
                                    <div id="cart-items" style="min-height: 100px; margin-bottom: 20px;">
                                        <p style="color: #999; text-align: center; margin-top: 30px;">No items selected</p>
                                    </div>

                                    <div style="border-top: 1px solid #ddd; padding-top: 15px; margin-top: auto;">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 18px; font-weight: 700;">
                                            <span>Total</span>
                                            <span id="cart-total">₹0</span>
                                        </div>
                                        <button id="pay-add-coverage-btn" class="button button-primary" style="width: 100%; padding: 12px; font-size: 16px; background: linear-gradient(135deg, #007bff, #0056b3); border: none;" disabled>
                                            Pay & Add Coverage
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Razorpay Script -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    
    <script>
    // Pass PHP data to JS
    var vendorCoverage = {
        ownedStates: <?php echo json_encode($purchased_states); ?>,
        ownedCities: <?php echo json_encode($purchased_cities); ?>,
        userEmail: '<?php echo esc_js($current_user->user_email); ?>',
        userName: '<?php echo esc_js($current_user->display_name); ?>',
        userPhone: '<?php echo esc_js(get_user_meta($vendor_id, 'phone', true)); ?>'
    };
    </script>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal" onclick="closeImageModal()">
        <span class="modal-close">✕</span>
        <img class="modal-content" id="modalImage">
    </div>
    <?php
}