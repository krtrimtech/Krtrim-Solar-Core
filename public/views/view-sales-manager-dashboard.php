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
                <a href="javascript:void(0)" class="nav-item" data-section="cleaning-services"><span>🧼</span> Cleaning Services</a>
                <a href="javascript:void(0)" class="nav-item" data-section="conversions"><span>✅</span> My Conversions</a>
            </nav>
            <div class="sidebar-profile">
                <div class="profile-info">
                    <h4><?php echo esc_html($user->display_name); ?></h4>
                    <p>Sales Manager</p>
                </div>
                <!-- Admin Contact -->
                <div class="supervisor-info" style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                    <p style="font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.6); margin-bottom: 5px;">Support / Admin</p>
                    <?php
                    $admins = get_users(['role' => 'administrator', 'number' => 1]);
                    if (!empty($admins)) {
                        $admin = $admins[0];
                        echo '<p style="font-size: 13px; margin-bottom: 2px;">' . esc_html($admin->display_name) . '</p>';
                        echo '<p style="font-size: 12px; opacity: 0.8;">' . esc_html($admin->user_email) . '</p>';
                        $admin_phone = get_user_meta($admin->ID, 'phone_number', true);
                        if ($admin_phone) {
                            echo '<p style="font-size: 12px; opacity: 0.8;">' . esc_html($admin_phone) . '</p>';
                        }
                    } else {
                        echo '<p>Contact Admin</p>';
                    }
                    ?>
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

                <!-- Cleaning Services Section -->
                <section id="cleaning-services-section" class="section-content" style="display:none;">
                    <div class="section-header">
                        <h2 class="section-title">🧼 My Cleaning Services</h2>
                        <p style="color: #666;">Services from leads you created</p>
                    </div>
                    
                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table" id="sm-cleaning-services-table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Plan</th>
                                        <th>System</th>
                                        <th>Visits</th>
                                        <th>Payment</th>
                                        <th>Next Visit</th>
                                    </tr>
                                </thead>
                                <tbody id="sm-cleaning-services-tbody">
                                    <tr><td colspan="6">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
            <a href="javascript:void(0)" class="mobile-nav-item" data-section="cleaning-services">
                <span class="nav-icon">🧼</span>
                <span>Cleaning</span>
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

    <!-- Schedule Visit Modal -->
    <div id="schedule-visit-modal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width: 500px;">
            <span class="close-modal">&times;</span>
            <h3>+ Schedule Cleaning Visit</h3>
            <form id="schedule-visit-form">
                <input type="hidden" id="schedule_service_id">
                <div class="form-group">
                    <label>Customer</label>
                    <p id="schedule_customer_name" class="customer-name-display"></p>
                </div>
                <div class="form-group">
                    <label for="schedule_cleaner_id">Select Cleaner *</label>
                    <select id="schedule_cleaner_id" name="cleaner_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="">Loading cleaners...</option>
                    </select>
                </div>
                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="schedule_date">Date *</label>
                        <input type="date" id="schedule_date" name="scheduled_date" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="schedule_time">Time *</label>
                        <input type="time" id="schedule_time" name="scheduled_time" value="09:00" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">+ Schedule Visit</button>
                <div id="schedule-visit-feedback" style="margin-top: 15px;"></div>
            </form>
        </div>
    </div>

    <!-- Service Detail Modal -->
    <div id="service-detail-modal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width: 700px;">
            <span class="close-modal">&times;</span>
            <h3>🧼 Cleaning Service Details</h3>
            <div id="service-detail-content">
                <p>Loading...</p>
            </div>
        </div>
    </div>

    <!-- Book Cleaning Modal -->
    <div id="book-cleaning-modal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width: 500px;">
            <span class="close-modal">&times;</span>
            <h3>🧹 Book Cleaning Service</h3>
            <form id="book-cleaning-form">
                <input type="hidden" id="book_lead_id" name="lead_id">
                
                <div class="form-group">
                    <label>Customer</label>
                    <p id="book_customer_name" class="customer-name-display"></p>
                </div>

                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="book_plan_type">Plan Type *</label>
                        <select id="book_plan_type" name="plan_type" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            <option value="one_time">One Time</option>
                            <option value="monthly">Monthly</option>
                            <option value="6_month">6 Months</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="book_system_size">System Size (kW) *</label>
                        <input type="number" id="book_system_size" name="system_size" min="1" step="0.1" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="book_total_amount">Total Amount (₹) *</label>
                    <input type="number" id="book_total_amount" name="total_amount" min="0" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                </div>

                <div class="form-group">
                    <label for="book_cleaner_id">Assign Cleaner *</label>
                    <select id="book_cleaner_id" name="cleaner_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="">Loading cleaners...</option>
                    </select>
                </div>

                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="book_visit_date">First Visit Date *</label>
                        <input type="date" id="book_visit_date" name="visit_date" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="book_visit_time">Time *</label>
                        <input type="time" id="book_visit_time" name="visit_time" value="09:00" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">✅ Book Service</button>
            </form>
        </div>
    </div>

    <!-- Edit Visit Modal -->
    <div id="edit-visit-modal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width: 500px;">
            <span class="close-modal">&times;</span>
            <h3>✏️ Edit Visit</h3>
            <form id="edit-visit-form">
                <input type="hidden" id="edit_visit_id" name="visit_id">
                <input type="hidden" id="edit_service_id" name="service_id">
                
                <div class="form-group">
                    <label>Customer</label>
                    <p id="edit_customer_name" class="customer-name-display"></p>
                </div>

                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="edit_visit_date">Date *</label>
                        <input type="date" id="edit_visit_date" name="scheduled_date" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="edit_visit_time">Time *</label>
                        <input type="time" id="edit_visit_time" name="scheduled_time" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_visit_cleaner">Assign Cleaner</label>
                    <select id="edit_visit_cleaner" name="cleaner_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="">Select Cleaner</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">💾 Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Cancel Visit Modal -->
    <div id="cancel-visit-modal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width: 400px;">
            <span class="close-modal">&times;</span>
            <h3 style="color: #dc3545;">❌ Cancel Visit</h3>
            <p>Are you sure you want to cancel this visit?</p>
            <form id="cancel-visit-form">
                <input type="hidden" id="cancel_visit_id" name="visit_id">
                
                <div class="form-group">
                    <label for="cancel_reason">Reason for Cancellation *</label>
                    <textarea id="cancel_reason" name="reason" rows="3" required placeholder="Client requested rescheduling..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;"></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="button" class="btn btn-secondary close-modal" style="flex: 1;">Go Back</button>
                    <button type="submit" class="btn btn-danger" style="flex: 1; background-color: #dc3545; color: white;">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <?php
    return ob_get_clean();
}
