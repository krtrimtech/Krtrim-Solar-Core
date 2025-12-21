<?php
/**
 * Sales Manager Dashboard
 * 
 * Frontend dashboard for Sales Managers to manage leads and track follow-ups.
 * Supervised by Area Managers.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_sales_manager_dashboard_shortcode() {
    // Security check
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( get_permalink() ) );
        exit;
    }

    $current_user = wp_get_current_user();
    $user_roles   = $current_user->roles;

    // Only allow sales_manager or higher roles
    if ( ! in_array( 'sales_manager', $user_roles, true ) && 
         ! in_array( 'administrator', $user_roles, true ) && 
         ! in_array( 'manager', $user_roles, true ) ) {
        // Redirect other roles to their appropriate dashboards
        if ( in_array( 'area_manager', $user_roles, true ) ) {
            wp_safe_redirect( home_url( '/area-manager-dashboard/' ) );
            exit;
        }
        if ( in_array( 'solar_client', $user_roles, true ) || in_array( 'solar_vendor', $user_roles, true ) ) {
            wp_safe_redirect( home_url( '/solar-dashboard/' ) );
            exit;
        }
        wp_safe_redirect( admin_url() );
        exit;
    }

    $user = wp_get_current_user();
    $supervisor_id = get_user_meta( $user->ID, '_supervised_by_area_manager', true );
    
    ob_start();
    ?>
    <div id="salesManagerDashboard" class="modern-solar-dashboard sales-manager-dashboard">
        <!-- Sidebar -->
        <div class="dashboard-sidebar">
            <div class="sidebar-brand">
                <?php
                if ( has_custom_logo() ) {
                    echo get_custom_logo();
                } else {
                    echo '<span class="logo">☀️</span>';
                }
                ?>
                <span><?php echo get_bloginfo('name'); ?></span>
            </div>
            <nav class="sidebar-nav">
                <a href="javascript:void(0)" class="nav-item active" data-section="dashboard"><span>🏠</span> Dashboard</a>
                <a href="javascript:void(0)" class="nav-item" data-section="my-leads"><span>👥</span> Leads</a>
                <a href="javascript:void(0)" class="nav-item" data-section="conversions"><span>✅</span> My Conversions</a>
            </nav>
            <div class="sidebar-profile">
                <div class="profile-info">
                    <h4><?php echo esc_html($user->display_name); ?></h4>
                    <p>Sales Manager</p>
                </div>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="logout-btn" title="Logout">🚪</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="dashboard-main">
            <header class="dashboard-header-top">
                <h1 id="section-title">Dashboard</h1>
                <div class="header-right">
                    <button class="notification-badge" id="notification-toggle" title="Notifications">
                        🔔
                        <span class="badge-count" id="notif-count" style="display:none;">0</span>
                    </button>
                </div>
            </header>
            <main class="dashboard-content">
                <!-- Dashboard Section -->
                <section id="dashboard-section" class="section-content">
                    <!-- Stats Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">👥</div>
                            <div class="stat-details">
                                <h3 id="total-leads-stat">0</h3>
                                <span>Total Leads</span>
                            </div>
                        </div>
                        <div class="stat-card stat-warning">
                            <div class="stat-icon">📞</div>
                            <div class="stat-details">
                                <h3 id="pending-followups-stat">0</h3>
                                <span>Pending Follow-ups</span>
                            </div>
                        </div>
                        <div class="stat-card stat-success">
                            <div class="stat-icon">✅</div>
                            <div class="stat-details">
                                <h3 id="converted-leads-stat">0</h3>
                                <span>Conversions</span>
                            </div>
                        </div>
                        <div class="stat-card stat-highlight">
                            <div class="stat-icon">📈</div>
                            <div class="stat-details">
                                <h3 id="conversion-rate-stat">0%</h3>
                                <span>Conversion Rate</span>
                            </div>
                        </div>
                    </div>

                    <!-- Lead Status Chart -->
                    <div class="charts-grid">
                        <div class="chart-card">
                            <h3>📊 Lead Status Distribution</h3>
                            <div class="chart-container">
                                <canvas id="lead-status-chart"></canvas>
                            </div>
                        </div>
                        <div class="chart-card">
                            <h3>📈 Monthly Performance</h3>
                            <div class="chart-container">
                                <canvas id="performance-chart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Follow-ups -->
                    <div class="card" style="margin-top: 20px;">
                        <h3>📅 Today's Follow-ups</h3>
                        <div id="today-followups-container">
                            <p>Loading...</p>
                        </div>
                    </div>
                </section>

                <!-- Leads Section - Using Shared Component -->
                <section id="my-leads-section" class="section-content" style="display:none;">
                    <?php 
                    // Include and render shared lead component
                    require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/components/class-lead-component.php';
                    KSC_Lead_Component::render_lead_section($user_roles, 'sales_manager');
                    ?>
                </section>

                <!-- Conversions Section -->
                <section id="conversions-section" class="section-content" style="display:none;">
                    <h2>✅ My Conversions</h2>
                    <p style="color: #666; margin-bottom: 20px;">Leads you successfully converted to clients.</p>
                    <div class="card">
                        <div id="conversions-container">
                            <p>Loading conversions...</p>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-bottom-nav">
        <div class="mobile-bottom-nav-items">
            <a href="javascript:void(0)" class="mobile-nav-item active" data-section="dashboard">
                <span class="nav-icon">🏠</span>
                <span>Home</span>
            </a>
            <a href="javascript:void(0)" class="mobile-nav-item" data-section="my-leads">
                <span class="nav-icon">👥</span>
                <span>Leads</span>
            </a>
            <a href="javascript:void(0)" class="mobile-nav-item" data-section="conversions">
                <span class="nav-icon">✅</span>
                <span>Conversions</span>
            </a>
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="mobile-nav-item">
                <span class="nav-icon">🚪</span>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- Notification Panel -->
    <div class="notification-panel" id="notification-panel">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button class="close-btn" id="close-notification-panel">×</button>
        </div>
        <div class="notification-list" id="notif-list">
            <p style="text-align: center; color: #999; padding: 20px;">Loading notifications...</p>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <?php
    return ob_get_clean();
}
