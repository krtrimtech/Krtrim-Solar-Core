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
        'post_type' => 'solar_project',
        'posts_per_page' => -1, // Fetch all projects
        'post_status' => 'publish',
    );
    
    if ($client_id > 0) {
        $args['meta_query'] = array(
            array(
                'key' => '_client_user_id',
                'value' => $client_id,
            )
        );
    }
    
    $project_query = new WP_Query($args);
    
    // Extract chart data immediately (before any display loops)
    $chart_total_cost = 0;
    $chart_paid = 0;
    $chart_balance = 0;
    
    if ($project_query->have_posts()) {
        while ($project_query->have_posts()) {
            $project_query->the_post();
            $temp_project_id = get_the_ID();
            
            if ($temp_project_id > 0) {
                $chart_total_cost = floatval(get_post_meta($temp_project_id, '_total_project_cost', true));
                $chart_paid = floatval(get_post_meta($temp_project_id, '_paid_amount', true));
                $chart_balance = $chart_total_cost - $chart_paid;
            }
            break; // Only need first project
        }
        wp_reset_postdata(); // Reset once, cleanly
    }
    
    // Rewind query for display loops
    $project_query->rewind_posts();
    ?>

<!-- Global JavaScript Functions (must be before HTML) -->
<script>
// Section Switching
function switchSection(event, sectionName) {
    if (event) event.preventDefault();
    
    document.querySelectorAll('.section-content').forEach(section => {
        section.style.display = 'none';
    });
    
    const targetSection = document.getElementById(sectionName + '-section');
    if (targetSection) {
        targetSection.style.display = 'block';
    }
    
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    const activeNav = document.querySelector('.nav-item[data-section="' + sectionName + '"]');
    if (activeNav) {
        activeNav.classList.add('active');
    }
    
    const titles = {
        'dashboard': 'Dashboard',
        'projects': 'Projects',
        'timeline': 'Timeline'
    };
    const titleElement = document.getElementById('section-title');
    if (titleElement) {
        titleElement.textContent = titles[sectionName] || 'Dashboard';
    }
}

function toggleSidebar() {
    const sidebar = document.querySelector('.dashboard-sidebar');
    if (sidebar) {
        sidebar.classList.toggle('active');
    }
}

function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    if (panel) {
        panel.classList.toggle('active');
    }
}

function toggleTimelineDetail(stepId) {
    const content = document.getElementById('content-' + stepId);
    const arrow = document.getElementById('arrow-' + stepId);
    
    if (content) {
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            if (arrow) arrow.style.transform = 'rotate(180deg)';
        } else {
            content.style.display = 'none';
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        }
    }
}

function toggleCommentForm(stepId) {
    const form = document.getElementById('comment-form-' + stepId);
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
}

function submitComment(stepId, projectId) {
    const textarea = document.getElementById('comment-text-' + stepId);
    if (!textarea || !textarea.value.trim()) {
        alert('Please enter a comment.');
        return;
    }
    
    alert('Comment functionality coming soon!');
    toggleCommentForm(stepId);
}

function openImageModal(src) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    if (modal && modalImg) {
        modal.style.display = 'block';
        modalImg.src = src;
    }
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function toggleProjectDetails(projectId) {
    const details = document.getElementById('details-' + projectId);
    const icon = document.getElementById('icon-' + projectId);
    
    if (details && icon) {
        if (details.style.display === 'none' || details.style.display === '') {
            details.style.display = 'block';
            icon.style.transform = 'rotate(180deg)';
        } else {
            details.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
        }
    }
}
</script>

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
                        $total_project_cost = floatval(get_post_meta($project_id, '_total_project_cost', true));
                        $paid_amount = floatval(get_post_meta($project_id, '_paid_amount', true));
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
                            
                            <?php
                            // Get vendor information
                            $vendor_user_id = get_post_meta($project_id, '_vendor_user_id', true);
                            $vendor_info = null;
                            if ($vendor_user_id) {
                                $vendor_info = get_userdata($vendor_user_id);
                            }
                            
                            // Get area manager (project author)
                            $area_manager_id = get_post_field('post_author', $project_id);
                            $area_manager = get_userdata($area_manager_id);
                            ?>
                            
                            <!-- Assigned Vendor Card -->
                            <?php if ($vendor_info): ?>
                            <div class="card vendor-card">
                                <div class="card-header">
                                    <h3>👷 Assigned Vendor</h3>
                                </div>
                                <div class="vendor-info-content">
                                    <div class="vendor-avatar">
                                        <img src="<?php echo esc_url(get_avatar_url($vendor_info->ID, ['size' => 80])); ?>" alt="<?php echo esc_attr($vendor_info->display_name); ?>">
                                    </div>
                                    <div class="vendor-details">
                                        <h4><?php echo esc_html($vendor_info->display_name); ?></h4>
                                        <p class="vendor-email">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                <polyline points="22,6 12,13 2,6"></polyline>
                                            </svg>
                                            <?php echo esc_html($vendor_info->user_email); ?>
                                        </p>
                                        <?php 
                                        $vendor_phone = get_user_meta($vendor_info->ID, 'phone_number', true);
                                        if ($vendor_phone): 
                                        ?>
                                        <p class="vendor-phone">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                            </svg>
                                            <?php echo esc_html($vendor_phone); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="card vendor-card vendor-card-empty">
                                <div class="card-header">
                                    <h3>👷 Vendor Assignment</h3>
                                </div>
                                <div class="vendor-empty-state">
                                    <div class="empty-icon">⏳</div>
                                    <p>No vendor assigned yet</p>
                                    <small>Your area manager will assign a vendor soon</small>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Area Manager Contact Card -->
                            <?php if ($area_manager): ?>
                            <div class="card area-manager-card">
                                <div class="card-header">
                                    <h3>👨‍💼 Your Area Manager</h3>
                                </div>
                                <div class="manager-info-content">
                                    <div class="manager-avatar">
                                        <img src="<?php echo esc_url(get_avatar_url($area_manager->ID, ['size' => 80])); ?>" alt="<?php echo esc_attr($area_manager->display_name); ?>">
                                    </div>
                                    <div class="manager-details">
                                        <h4><?php echo esc_html($area_manager->display_name); ?></h4>
                                        <p class="manager-role">Area Manager</p>
                                    </div>
                                </div>
                                
                                <div class="contact-buttons">
                                    <a href="mailto:<?php echo esc_attr($area_manager->user_email); ?>?subject=Query about Project: <?php echo urlencode(get_the_title($project_id)); ?>&body=Hi <?php echo urlencode($area_manager->display_name); ?>,%0A%0AI have a query regarding my solar project.%0A%0AProject: <?php echo urlencode(get_the_title($project_id)); ?>%0A%0A" 
                                       class="contact-btn email-btn">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                            <polyline points="22,6 12,13 2,6"></polyline>
                                        </svg>
                                        Send Email
                                    </a>
                                    
                                    <?php 
                                    $manager_phone = get_user_meta($area_manager->ID, 'phone_number', true);
                                    if ($manager_phone): 
                                        $clean_phone = preg_replace('/[^0-9]/', '', $manager_phone);
                                        $whatsapp_msg = "Hi, I need help with my solar project: " . get_the_title($project_id);
                                    ?>
                                    <a href="https://wa.me/<?php echo esc_attr($clean_phone); ?>?text=<?php echo urlencode($whatsapp_msg); ?>" 
                                       target="_blank" 
                                       class="contact-btn whatsapp-btn">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.304-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        WhatsApp
                                    </a>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="manager-help-text">
                                    <p>💡 Your area manager is here to help with any questions about your project.</p>
                                </div>
                            </div>
                            <?php endif; ?>
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
            </div>
            
            <!-- PROJECTS SECTION -->
            <div class="section-content" id="projects-section" style="display: none;">
                <?php if ($project_query->have_posts()) : ?>
                    <div class="projects-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; padding: 20px 0;">
                        <?php 
                        $project_query->rewind_posts(); // Start fresh for projects list
                        while ($project_query->have_posts()) : $project_query->the_post(); 
                            $proj_id = get_the_ID();
                            $proj_status = get_post_meta($proj_id, '_project_status', true);
                            $proj_size = get_post_meta($proj_id, '_solar_system_size_kw', true);
                            $proj_cost = floatval(get_post_meta($proj_id, '_total_project_cost', true));
                            $proj_paid = floatval(get_post_meta($proj_id, '_paid_amount', true));
                            $proj_balance = $proj_cost - $proj_paid;
                            
                            // Status badge colors
                            $status_colors = array(
                                'pending' => '#ffc107',
                                'in_progress' => '#007bff',
                                'completed' => '#28a745',
                                'on_hold' => '#dc3545',
                            );
                            $status_color = isset($status_colors[$proj_status]) ? $status_colors[$proj_status] : '#6c757d';
                        ?>
                            <div class="project-card" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.3s ease; border-left: 4px solid <?php echo $status_color; ?>;" onclick="toggleProjectDetails(<?php echo $proj_id; ?>)">
                                <!-- Card Header -->
                                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <h3 style="margin: 0; font-size: 18px; color: #333;"><?php the_title(); ?></h3>
                                    <span class="expand-icon" id="icon-<?php echo $proj_id; ?>" style="font-size: 24px; transition: transform 0.3s;">▼</span>
                                </div>
                                
                                <!-- Card Summary (always visible) -->
                                <div class="card-summary">
                                    <div style="display: flex; gap: 10px; margin-bottom: 12px;">
                                        <span class="status-badge" style="background: <?php echo $status_color; ?>; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                            <?php echo ucfirst(str_replace('_', ' ', $proj_status)); ?>
                                        </span>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px; color: #666;">
                                        <div>
                                            <strong style="color: #333;">System Size:</strong><br>
                                            <?php echo $proj_size ? $proj_size . ' kW' : 'N/A'; ?>
                                        </div>
                                        <div>
                                            <strong style="color: #333;">Total Cost:</strong><br>
                                            ₹<?php echo number_format($proj_cost, 0); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Card Details (hidden, expands on click) -->
                                <div class="card-details" id="details-<?php echo $proj_id; ?>" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                                    <?php
                                    // Get additional project details
                                    $client_address = get_post_meta($proj_id, '_client_address', true);
                                    $client_phone = get_post_meta($proj_id, '_client_phone_number', true);
                                    $start_date = get_post_meta($proj_id, '_project_start_date', true);
                                    
                                    // Get process steps
                                    global $wpdb;
                                    $steps_table = $wpdb->prefix . 'solar_process_steps';
                                    $steps = $wpdb->get_results($wpdb->prepare(
                                        "SELECT * FROM {$steps_table} WHERE project_id = %d ORDER BY step_number ASC",
                                        $proj_id
                                    ));
                                    
                                    $total_steps = count($steps);
                                    $completed_steps = 0;
                                    if ($steps) {
                                        foreach ($steps as $step) {
                                            if ($step->admin_status == 'approved') {
                                                $completed_steps++;
                                            }
                                        }
                                    }
                                    $progress = ($total_steps > 0) ? round(($completed_steps / $total_steps) * 100) : 0;
                                    ?>
                                    
                                    <!-- Financial Details -->
                                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                        <h4 style="margin: 0 0 12px 0; font-size: 16px; color: #333;">💰 Financial Summary</h4>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px;">
                                            <div>
                                                <strong>Total Cost:</strong><br>
                                                ₹<?php echo number_format($proj_cost, 0); ?>
                                            </div>
                                            <div>
                                                <strong>Paid Amount:</strong><br>
                                                <span style="color: #28a745;">₹<?php echo number_format($proj_paid, 0); ?></span>
                                            </div>
                                            <div style="grid-column: span 2;">
                                                <strong>Balance:</strong><br>
                                                <span style="color: #dc3545; font-size: 16px; font-weight: 700;">₹<?php echo number_format($proj_balance, 0); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress Details -->
                                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                        <h4 style="margin: 0 0 12px 0; font-size: 16px; color: #333;">📊 Project Progress</h4>
                                        <div style="margin-bottom: 10px;">
                                            <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 5px;">
                                                <span><?php echo $completed_steps; ?> of <?php echo $total_steps; ?> steps completed</span>
                                                <span><?php echo $progress; ?>%</span>
                                            </div>
                                            <div style="background: #e9ecef; height: 10px; border-radius: 10px; overflow: hidden;">
                                                <div style="background: linear-gradient(90deg, #667eea, #764ba2); height: 100%; width: <?php echo $progress; ?>%; transition: width 0.5s;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Location Details -->
                                    <?php if ($client_address || $client_phone || $start_date): ?>
                                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                        <h4 style="margin: 0 0 12px 0; font-size: 16px; color: #333;">📍 Project Details</h4>
                                        <div style="font-size: 14px; line-height: 1.8;">
                                            <?php if ($client_address): ?>
                                                <div><strong>Address:</strong> <?php echo esc_html($client_address); ?></div>
                                            <?php endif; ?>
                                            <?php if ($client_phone): ?>
                                                <div><strong>Phone:</strong> <?php echo esc_html($client_phone); ?></div>
                                            <?php endif; ?>
                                            <?php if ($start_date): ?>
                                                <div><strong>Start Date:</strong> <?php echo esc_html($start_date); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else : ?>
                    <div style="text-align: center; padding: 60px 20px; color: #666;">
                        <div style="font-size: 64px; margin-bottom: 20px;">📦</div>
                        <h2 style="color: #333; margin-bottom: 10px;">No Projects Yet</h2>
                        <p>You don't have any solar projects assigned.</p>
                    </div>
                <?php endif; ?>
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

<!-- Chart.js Initialization -->
<script>

jQuery(document).ready(function($) {
    // Payment Summary Chart
    var paymentCanvas = document.getElementById('payment-summary-chart');
    if (paymentCanvas && <?php echo $chart_total_cost; ?> > 0) {
        var ctx = paymentCanvas.getContext('2d');
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Amount Paid', 'Balance Due'],
                datasets: [{
                    data: [<?php echo $chart_paid; ?>, <?php echo $chart_balance; ?>],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)'
                    ],
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12,
                                weight: '600'
                            },
                            color: '#4a5568',
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                var value = context.parsed || 0;
                                var percentage = ((value / <?php echo $chart_total_cost; ?>) * 100).toFixed(1);
                                return label + ': ₹' + value.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1500,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }
    
    // Animate numbers on load
    $('.stat-value').each(function() {
        var $this = $(this);
        var text = $this.text();
        var number = parseFloat(text.replace(/[^0-9.]/g, ''));
        
        if (!isNaN(number)) {
            $this.prop('Counter', 0).animate({
                Counter: number
            }, {
                duration: 1500,
                easing: 'swing',
                step: function(now) {
                    if (text.includes('%')) {
                        $this.text(Math.ceil(now) + '%');
                    } else if (text.includes('₹')) {
                        $this.text('₹' + Math.ceil(now).toLocaleString());
                    } else {
                        $this.text(Math.ceil(now).toLocaleString());
                    }
                }
            });
        }
    });
});
</script>
<?php
}
