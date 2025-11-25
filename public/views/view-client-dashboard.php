<?php

function render_solar_client_dashboard() {
    $current_user = wp_get_current_user();
    $is_admin = in_array('administrator', $current_user->roles);
    
    if ($is_admin) {
        $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
    } else {
        $client_id = get_current_user_id();
    }
    
    $args = array(
        'post_type' => 'solar-project',
        'posts_per_page' => 1,
        'post_status' => 'publish',
    );
    
    if ($client_id > 0) {
        $args['meta_query'] = array(
            array(
                'key' => 'client_user_id',
                'value' => $client_id,
            )
        );
    }
    
    $project_query = new WP_Query($args);
    
    ?>

<div class="modern-solar-dashboard" id="modernDashboard">
    
    <!-- Left Sidebar -->
    <aside class="dashboard-sidebar">
        <div class="sidebar-brand">
            <?php
            if ( has_custom_logo() ) {
                echo get_custom_logo();
            } else {
                echo '<div class="logo">☀️</div>';
                echo '<span>' . esc_html(get_bloginfo('name')) . '</span>';
            }
            ?>
            <button class="sidebar-toggle-mobile" onclick="toggleSidebar()" title="Toggle Menu">☰</button>
        </div>
        
        <nav class="sidebar-nav">
            <a href="#" class="nav-item active" data-section="dashboard" onclick="switchSection(event, 'dashboard')">
                <span class="icon">📊</span>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-item" data-section="projects" onclick="switchSection(event, 'projects')">
                <span class="icon">⚡️</span>
                <span>Projects</span>
            </a>
            <a href="#" class="nav-item" data-section="timeline" onclick="switchSection(event, 'timeline')">
                <span class="icon">🔄</span>
                <span>Timeline</span>
            </a>
        </nav>
        
        <!-- User Profile -->
        <div class="sidebar-profile">
            <img src="<?php echo esc_url(get_avatar_url($current_user->ID)); ?>" alt="<?php echo esc_html($current_user->display_name); ?>">
            <div class="profile-info">
                <h4><?php echo esc_html($current_user->display_name); ?></h4>
                <p><?php echo $is_admin ? 'Administrator' : 'Client'; ?></p>
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
                <?php if ($is_admin): ?>
                    <select id="admin-client-switcher" onchange="window.location.href='?client_id='+this.value" class="admin-switcher">
                        <option value="0">Select Client</option>
                        <?php
                        $clients = get_users(array('role' => 'solar_client'));
                        foreach ($clients as $client) {
                            $selected = ($client_id == $client->ID) ? 'selected' : '';
                            echo '<option value="' . $client->ID . '" ' . $selected . '>' . esc_html($client->display_name) . '</option>';
                        }
                        ?>
                    </select>
                <?php endif; ?>
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
            <div class="section-content" id="dashboard-section" style="display: block;">
                <?php if ($project_query->have_posts()) : ?>
                    <?php while ($project_query->have_posts()) : $project_query->the_post(); ?>
                        <?php
                        $project_id = get_the_ID();
                        $project_status = get_post_meta($project_id, '_project_status', true);
                        $solar_system_size = get_post_meta($project_id, '_solar_system_size_kw', true);
                        $total_project_cost = get_post_meta($project_id, '_total_project_cost', true);
                        $paid_amount = get_post_meta($project_id, '_paid_amount', true);
                        $balance = $total_project_cost - $paid_amount;
                        $client_address = get_post_meta($project_id, '_client_address', true);
                        $client_phone = get_post_meta($project_id, '_client_phone_number', true);
                        $project_start_date = get_post_meta($project_id, '_project_start_date', true);
                        
                        global $wpdb;
                        $table = $wpdb->prefix . 'solar_process_steps';
                        $steps = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM $table WHERE project_id = %d ORDER BY step_number ASC",
                            $project_id
                        ));
                        
                        $total_steps = count($steps);
                        $completed_steps = 0;
                        
                        foreach ($steps as $step) {
                            if ($step->admin_status == 'approved') {
                                $completed_steps++;
                            }
                        }
                        
                        $progress_percentage = ($total_steps > 0) ? round(($completed_steps / $total_steps) * 100) : 0;
                        ?>
                        
                        <!-- Stats Row -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-header">
                                    <span class="stat-label">Project Status</span>
                                    <span class="stat-icon">⚡️</span>
                                </div>
                                <div class="stat-value"><?php echo ucfirst(str_replace('_', ' ', $project_status)); ?></div>
                                <div class="stat-subtitle"><?php echo $project_status; ?></div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-header">
                                    <span class="stat-label">System Size</span>
                                    <span class="stat-icon">💡</span>
                                </div>
                                <div class="stat-value"><?php echo esc_html($solar_system_size ?: 'N/A'); ?> kW</div>
                                <div class="stat-subtitle">Capacity</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-header">
                                    <span class="stat-label">Total Cost</span>
                                    <span class="stat-icon">💰</span>
                                </div>
                                <div class="stat-value">₹<?php echo number_format($total_project_cost, 0); ?></div>
                                <div class="stat-subtitle">Project Budget</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-header">
                                    <span class="stat-label">Progress</span>
                                    <span class="stat-icon">✅</span>
                                </div>
                                <div class="stat-value"><?php echo $progress_percentage; ?>%</div>
                                <div class="stat-subtitle"><?php echo $completed_steps; ?>/<?php echo $total_steps; ?> steps</div>
                            </div>
                        </div>
                        
                        <!-- Main Content Grid -->
                        <div class="content-grid">
                            
                            <!-- Left Column -->
                            <div class="content-left">
                                
                                <!-- Progress Circle -->
                                <div class="card">
                                    <div class="card-header">
                                        <h3>Progress Overview</h3>
                                        <div class="date-range">01-<?php echo date('d'); ?> <?php echo date('M'); ?></div>
                                    </div>
                                    <div class="progress-container">
                                        <div class="progress-circle">
                                            <div class="circle-content">
                                                <div class="circle-value"><?php echo $progress_percentage; ?>%</div>
                                                <div class="circle-label">Complete</div>
                                            </div>
                                            <svg viewBox="0 0 100 100">
                                                <circle cx="50" cy="50" r="45" class="progress-bg"></circle>
                                                <circle cx="50" cy="50" r="45" class="progress-fill" style="--percentage: <?php echo $progress_percentage; ?>"></circle>
                                            </svg>
                                        </div>
                                        
                                        <div class="progress-details">
                                            <div class="detail-item">
                                                <span class="detail-label">Completed</span>
                                                <span class="detail-value"><?php echo $completed_steps; ?> steps</span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">Remaining</span>
                                                <span class="detail-value"><?php echo ($total_steps - $completed_steps); ?> steps</span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">Total Steps</span>
                                                <span class="detail-value"><?php echo $total_steps; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Payment Summary -->
                                <div class="card">
                                    <div class="card-header">
                                        <h3>Payment Summary</h3>
                                    </div>
                                    <div class="payment-summary-chart">
                                        <canvas id="payment-summary-chart"></canvas>
                                    </div>
                                    <div class="payment-grid">
                                        <div class="payment-item">
                                            <div class="payment-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">💸</div>
                                            <div class="payment-content">
                                                <div class="payment-label">Total Cost</div>
                                                <div class="payment-amount">₹<?php echo number_format($total_project_cost, 0); ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="payment-item">
                                            <div class="payment-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">✅</div>
                                            <div class="payment-content">
                                                <div class="payment-label">Amount Paid</div>
                                                <div class="payment-amount">₹<?php echo number_format($paid_amount, 0); ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="payment-item">
                                            <div class="payment-icon" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);">⏳</div>
                                            <div class="payment-content">
                                                <div class="payment-label">Balance Due</div>
                                                <div class="payment-amount">₹<?php echo number_format($balance, 0); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="content-right">
                                
                                <!-- Project Info Card -->
                                <div class="card upgrade-card">
                                    <div class="upgrade-content">
                                        <h3>Project Details</h3>
                                        <p>View all information about your solar project installation.</p>
                                        <button class="btn btn-primary" onclick="switchSection(null, 'projects')">View Details →</button>
                                    </div>
                                </div>
                                
                                <!-- Quick Info -->
                                <div class="card">
                                    <div class="card-header">
                                        <h3>Quick Info</h3>
                                    </div>
                                    <div class="info-list">
                                        <div class="info-item">
                                            <span class="info-label">📍 Location</span>
                                            <span class="info-value"><?php echo esc_html($client_address ?: 'N/A'); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">📅 Start Date</span>
                                            <span class="info-value"><?php echo esc_html($project_start_date ?: 'N/A'); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">📱 Phone</span>
                                            <span class="info-value"><?php echo esc_html($client_phone ?: 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Contact Support -->
                                <div class="card support-card">
                                    <div class="support-icon">📞</div>
                                    <h3>Need Help?</h3>
                                    <p>Contact our support team</p>
                                    <a href="mailto:contact@krtrim.tech" class="btn btn-secondary">Contact Support</a>
                                    <a href="https://github.com/sponsors/shyanukant" target="_blank" class="btn btn-secondary" style="margin-top: 10px;">Sponsor Shyanukant</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else : ?>
                    <div class="no-projects">
                        <div class="empty-icon">📦</div>
                        <h2>No Projects Yet</h2>
                        <p>You don't have any solar projects yet.</p>
                    </div>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>
            </div>
            
            <!-- PROJECTS SECTION -->
            <div class="section-content" id="projects-section" style="display: none;">
                <?php if ($project_query->have_posts()) : ?>
                    <?php while ($project_query->have_posts()) : $project_query->the_post(); ?>
                        <?php
                        $project_id = get_the_ID();
                        $project_status = get_post_meta($project_id, '_project_status', true);
                        $solar_system_size = get_post_meta($project_id, '_solar_system_size_kw', true);
                        $total_project_cost = get_post_meta($project_id, '_total_project_cost', true);
                        $paid_amount = get_post_meta($project_id, '_paid_amount', true);
                        $balance = $total_project_cost - $paid_amount;
                        $client_address = get_post_meta($project_id, '_client_address', true);
                        $client_phone = get_post_meta($project_id, '_client_phone_number', true);
                        $project_start_date = get_post_meta($project_id, '_project_start_date', true);
                        ?>
                        
                        <div class="card full-width">
                            <div class="card-header">
                                <h3><?php the_title(); ?></h3>
                                <span class="badge" style="background: #667eea; color: white; padding: 6px 12px; border-radius: 6px;"><?php echo ucfirst(str_replace('_', ' ', $project_status)); ?></span>
                            </div>
                            
                            <div class="project-details-grid">
                                <div class="detail-box">
                                    <h4>📊 System Details</h4>
                                    <p><strong>System Size:</strong> <?php echo esc_html($solar_system_size); ?> kW</p>
                                    <p><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $project_status)); ?></p>
                                </div>
                                
                                <div class="detail-box">
                                    <h4>💰 Financial Details</h4>
                                    <p><strong>Total Cost:</strong> ₹<?php echo number_format($total_project_cost, 0); ?></p>
                                    <p><strong>Paid Amount:</strong> ₹<?php echo number_format($paid_amount, 0); ?></p>
                                    <p><strong>Balance:</strong> ₹<?php echo number_format($balance, 0); ?></p>
                                </div>
                                
                                <div class="detail-box">
                                    <h4>📍 Location Details</h4>
                                    <p><strong>Address:</strong> <?php echo esc_html($client_address ?: 'N/A'); ?></p>
                                    <p><strong>Phone:</strong> <?php echo esc_html($client_phone ?: 'N/A'); ?></p>
                                    <p><strong>Start Date:</strong> <?php echo esc_html($project_start_date ?: 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>
            </div>
            
            <!-- TIMELINE SECTION -->
            <div class="section-content" id="timeline-section" style="display: none;">
                <?php if ($project_query->have_posts()) : ?>
                    <?php while ($project_query->have_posts()) : $project_query->the_post(); ?>
                        <?php
                        $project_id = get_the_ID();
                        global $wpdb;
                        $table = $wpdb->prefix . 'solar_process_steps';
                        $steps = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM $table WHERE project_id = %d ORDER BY step_number ASC",
                            $project_id
                        ));
                        ?>
                        
                        <div class="timeline-section-content">
                            <?php
                            $step_count = 0;
                            foreach ($steps as $step):
                                $step_count++;
                                $show_step = $is_admin || ($step->admin_status == 'approved');
                                if (!$show_step) continue;
                            ?>
                                <div class="timeline-card">
                                    <!-- Timeline Card Header - Clickable Toggle -->
                                    <div class="timeline-card-header" onclick="toggleTimelineDetail(<?php echo $step->id; ?>)" style="cursor: pointer; user-select: none;">
                                        <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                                            <div class="step-circle-large">
                                                <?php
                                                if ($step->admin_status == 'approved') {
                                                    echo '✓';
                                                } elseif ($step->admin_status == 'pending') {
                                                    echo '⏳';
                                                } else {
                                                    echo $step_count;
                                                }
                                                ?>
                                            </div>
                                            <div>
                                                <h3 style="margin: 0; font-size: 16px;"><?php echo esc_html($step->step_name); ?></h3>
                                                <span class="badge-step" style="background: <?php echo $step->admin_status == 'approved' ? '#28a745' : '#ffc107'; ?>; color: white; padding: 4px 10px; border-radius: 4px; font-size: 11px;">
                                                    <?php echo ucfirst($step->admin_status); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <span class="timeline-arrow" id="arrow-<?php echo $step->id; ?>" style="font-size: 20px; color: #667eea; transition: transform 0.3s; display: inline-block;">▼</span>
                                    </div>
                                    
                                    <!-- Timeline Card Content - Collapsible -->
                                    <div class="timeline-card-content" id="content-<?php echo $step->id; ?>" style="display: none; padding-top: 20px; border-top: 1px solid #f0f0f0; padding-left: 65px;">
                                        
                                        <!-- Step Image - Smaller -->
                                        <?php if ($step->image_url) : ?>
                                            <div class="step-image-container-small">
                                                <img src="<?php echo esc_url($step->image_url); ?>" alt="<?php echo esc_attr($step->step_name); ?>" onclick="openImageModal(this.src)" title="Click to view full image">
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Work Description -->
                                        <?php if ($step->vendor_comment) : ?>
                                            <div class="step-section">
                                                <h4>📝 Work Description</h4>
                                                <p><?php echo esc_html($step->vendor_comment); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Admin Notes -->
                                        <?php if ($step->admin_comment) : ?>
                                            <div class="step-section">
                                                <h4>👨‍💼 Admin Notes</h4>
                                                <p><?php echo esc_html($step->admin_comment); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Date Info -->
                                        <?php if ($step->approved_date) : ?>
                                            <div class="step-section">
                                                <h4>📅 Completed Date</h4>
                                                <p><?php echo date('d M Y, h:i A', strtotime($step->approved_date)); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Client Comment Section -->
                                        <?php if ($step->admin_status == 'approved' && !$is_admin) : ?>
                                            <div class="step-comment-section">
                                                <button class="btn btn-small" onclick="toggleCommentForm(<?php echo $step->id; ?>); event.stopPropagation();">💬 Add Comment</button>
                                                
                                                <div class="comment-form" id="comment-form-<?php echo $step->id; ?>" style="display: none; margin-top: 15px;">
                                                    <textarea placeholder="Share your feedback..." id="comment-text-<?php echo $step->id; ?>" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; margin: 10px 0; font-family: inherit; font-size: 13px;"></textarea>
                                                    <div style="display: flex; gap: 10px;">
                                                        <button class="btn btn-primary-small" onclick="submitComment(<?php echo $step->id; ?>, <?php echo $project_id; ?>); event.stopPropagation();">Submit</button>
                                                        <button class="btn btn-cancel-small" onclick="toggleCommentForm(<?php echo $step->id; ?>); event.stopPropagation();">Cancel</button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>
            </div>
        </div>
    </main>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal" onclick="closeImageModal()">
    <span class="modal-close">✕</span>
    <img class="modal-content" id="modalImage">
</div>
<?php
}