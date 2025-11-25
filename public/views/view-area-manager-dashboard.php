<?php
/**
 * Shortcode and logic for the Area Manager frontend dashboard.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_area_manager_dashboard_shortcode() {
    // Security check
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( get_permalink() ) );
        exit;
    }

    $current_user = wp_get_current_user();
    $user_roles   = $current_user->roles;

    if ( ! in_array( 'area_manager', $user_roles, true ) ) {
        if ( in_array( 'solar_client', $user_roles, true ) || in_array( 'solar_vendor', $user_roles, true ) ) {
            $dashboard_url = get_permalink( get_page_by_path( 'solar-dashboard' ) );
            wp_safe_redirect( $dashboard_url );
            exit;
        } else {
            wp_safe_redirect( admin_url() );
            exit;
        }
    }

    $user = wp_get_current_user();

    ob_start();
    ?>
    <div id="modernDashboard" class="modern-solar-dashboard area-manager-dashboard">
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
                <a href="#" class="nav-item active" data-section="dashboard"><span>🏠</span> Dashboard</a>
                <a href="#" class="nav-item" data-section="projects"><span>🏗️</span> Projects</a>
                <a href="#" class="nav-item" data-section="create-project"><span>➕</span> Create Project</a>
                <a href="#" class="nav-item" data-section="project-reviews"><span>📝</span> Project Reviews</a>
                <a href="#" class="nav-item" data-section="vendor-approvals"><span>👍</span> Vendor Approvals</a>
                <a href="#" class="nav-item" data-section="create-vendor"><span>👨‍💼</span> Create Vendor</a>
                <a href="#" class="nav-item" data-section="create-client"><span>👨‍👩‍👧‍👦</span> Create Client</a>
            </nav>
            <div class="sidebar-profile">
                <div class="profile-info">
                    <h4><?php echo esc_html($user->display_name); ?></h4>
                    <p>Area Manager</p>
                </div>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="logout-btn" title="Logout">🚪</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="dashboard-main">
            <header class="dashboard-header-top">
                <h1 id="section-title">Dashboard</h1>
            </header>
            <main class="dashboard-content">
                <!-- Dashboard Section -->
                <section id="dashboard-section" class="section-content">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <span class="stat-label">Total Projects</span>
                                <span class="stat-icon">🏗️</span>
                            </div>
                            <div class="stat-value" id="total-projects-stat">0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <span class="stat-label">Completed Projects</span>
                                <span class="stat-icon">✅</span>
                            </div>
                            <div class="stat-value" id="completed-projects-stat">0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <span class="stat-label">In-Progress Projects</span>
                                <span class="stat-icon">🔄</span>
                            </div>
                            <div class="stat-value" id="in-progress-projects-stat">0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <span class="stat-label">Total Paid to Vendors</span>
                                <span class="stat-icon">💰</span>
                            </div>
                            <div class="stat-value" id="total-paid-stat">₹0</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <span class="stat-label">Total Company Profit</span>
                                <span class="stat-icon">💼</span>
                            </div>
                            <div class="stat-value" id="total-profit-stat">₹0</div>
                        </div>
                    </div>
                    <div class="charts-container">
                        <canvas id="project-status-chart"></canvas>
                    </div>
                </section>

                <!-- Projects List Section -->
                <section id="projects-section" class="section-content" style="display:none;">
                    <h2>Your Projects</h2>
                    <div id="area-project-list-container">
                        <p>Loading projects...</p>
                    </div>
                </section>

                <!-- Project Detail Section -->
                <section id="project-detail-section" class="section-content" style="display:none;">
                    <button class="btn btn-secondary" id="back-to-projects-list">← Back to Projects</button>
                    <div class="card project-detail-card">
                        <h2 id="project-detail-title"></h2>
                        <div class="detail-grid" id="project-detail-meta">
                            <!-- Project meta will be loaded here -->
                        </div>

                        <div class="tabs-wrapper">
                            <div class="tabs-nav">
                                <button class="tab-button active" data-tab="progress">Progress</button>
                                <button class="tab-button" data-tab="bids">Bids</button>
                            </div>
                            <div class="tabs-content">
                                <div id="progress-tab" class="tab-pane active">
                                    <h3>Vendor Submissions</h3>
                                    <div id="vendor-submissions-list">Loading submissions...</div>
                                </div>
                                <div id="bids-tab" class="tab-pane">
                                    <h3>Project Bids</h3>
                                    <div id="project-bids-list">Loading bids...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Create Project Section -->
                <section id="create-project-section" class="section-content" style="display:none;">
                    <div class="card">
                        <h3>Create New Solar Project</h3>
                        <form id="create-project-form">
                            <?php wp_nonce_field('sp_create_project_nonce', 'sp_create_project_nonce_field'); ?>
                            <div class="form-group">
                                <label for="project_title">Project Title</label>
                                <input type="text" id="project_title" name="project_title" required>
                            </div>
                            <div class="form-group">
                                <label for="project_state">State</label>
                                <select name="project_state" id="project_state" required>
                                    <option value="">Select State</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="project_city">City</label>
                                <select name="project_city" id="project_city" required>
                                    <option value="">Select City</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="project_status">Project Status</label>
                                <select name="project_status" id="project_status" required>
                                    <option value="pending">Pending</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="client_user_id">Client</label>
                                <?php
                                wp_dropdown_users( array(
                                    'role' => 'solar_client',
                                    'name' => 'client_user_id',
                                    'show_option_none' => 'Select Client',
                                    'meta_key' => '_created_by_area_manager',
                                    'meta_value' => get_current_user_id(),
                                ) );
                                ?>
                            </div>
                            <div class="form-group">
                                <label for="solar_system_size_kw">Solar System Size (kW)</label>
                                <input type="number" id="solar_system_size_kw" name="solar_system_size_kw" step="0.1" required>
                            </div>
                            <div class="form-group">
                                <label for="client_address">Client Address</label>
                                <textarea id="client_address" name="client_address" rows="3" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="client_phone_number">Client Phone Number</label>
                                <input type="text" id="client_phone_number" name="client_phone_number" required>
                            </div>
                            <div class="form-group">
                                <label for="project_start_date">Project Start Date</label>
                                <input type="date" id="project_start_date" name="project_start_date" required>
                            </div>
                            <div class="form-group">
                                <label>Vendor Assignment</label>
                                <label><input type="radio" name="vendor_assignment_method" value="manual" checked> Manual</label>
                                <label style="margin-left: 15px;"><input type="radio" name="vendor_assignment_method" value="bidding"> Bidding</label>
                            </div>
                            <div class="form-group vendor-manual-fields">
                                <label for="assigned_vendor_id">Assign Vendor</label>
                                <?php
                                wp_dropdown_users( array(
                                    'role' => 'solar_vendor',
                                    'name' => 'assigned_vendor_id',
                                    'show_option_none' => 'Select Vendor',
                                    'meta_key' => '_created_by_area_manager',
                                    'meta_value' => get_current_user_id(),
                                ) );
                                ?>
                            </div>
                            <div class="form-group vendor-manual-fields">
                                <label for="paid_to_vendor">Amount to be Paid to Vendor</label>
                                <input type="number" id="paid_to_vendor" name="paid_to_vendor">
                            </div>
                            <button type="submit" class="btn btn-primary">Create Project</button>
                            <div id="create-project-feedback" style="margin-top:15px;"></div>
                        </form>
                    </div>
                </section>

                <!-- Project Reviews Section -->
                <section id="project-reviews-section" class="section-content" style="display:none;">
                    <h2>Project Reviews</h2>
                    <div id="project-reviews-container">
                        <p>Loading reviews...</p>
                    </div>
                </section>

                <!-- Vendor Approvals Section -->
                <section id="vendor-approvals-section" class="section-content" style="display:none;">
                    <h2>Vendor Approvals</h2>
                    <div id="vendor-approvals-container">
                        <p>Loading vendor approvals...</p>
                    </div>
                </section>

                <!-- Create Vendor Section -->
                <section id="create-vendor-section" class="section-content" style="display:none;">
                    <div class="card">
                        <h3>Create New Vendor</h3>
                        <form id="create-vendor-form">
                            <div class="form-group">
                                <label for="vendor_username">Username</label>
                                <input type="text" id="vendor_username" name="vendor_username" required>
                            </div>
                            <div class="form-group">
                                <label for="vendor_email">Email</label>
                                <input type="email" id="vendor_email" name="vendor_email" required>
                            </div>
                            <div class="form-group">
                                <label for="vendor_password">Password</label>
                                <input type="password" id="vendor_password" name="vendor_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Vendor</button>
                            <div id="create-vendor-feedback" style="margin-top:15px;"></div>
                        </form>
                    </div>
                </section>

                <!-- Create Client Section -->
                <section id="create-client-section" class="section-content" style="display:none;">
                    <div class="card">
                        <h3>Create New Client</h3>
                        <form id="create-client-form">
                            <div class="form-group">
                                <label for="client_username">Username</label>
                                <input type="text" id="client_username" name="client_username" required>
                            </div>
                            <div class="form-group">
                                <label for="client_email">Email</label>
                                <input type="email" id="client_email" name="client_email" required>
                            </div>
                            <div class="form-group">
                                <label for="client_password">Password</label>
                                <input type="password" id="client_password" name="client_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Client</button>
                            <div id="create-client-feedback" style="margin-top:15px;"></div>
                        </form>
                    </div>
                </section>
            </main>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('area_manager_dashboard', 'sp_area_manager_dashboard_shortcode');