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

    if ( ! in_array( 'area_manager', $user_roles, true ) && ! in_array( 'administrator', $user_roles, true ) && ! in_array( 'manager', $user_roles, true ) ) {
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
    <div id="areaManagerDashboard" class="modern-solar-dashboard area-manager-dashboard">
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
                <a href="javascript:void(0)" class="nav-item" data-section="projects"><span>🏗️</span> Projects</a>
                <a href="javascript:void(0)" class="nav-item" data-section="create-project"><span>➕</span> Create Project</a>
                <a href="javascript:void(0)" class="nav-item" data-section="project-reviews"><span>📝</span> Project Reviews</a>
                <a href="javascript:void(0)" class="nav-item" data-section="vendor-approvals"><span>👍</span> Vendor Approvals</a>

                <a href="javascript:void(0)" class="nav-item" data-section="leads"><span>👥</span> Leads</a>
                <a href="javascript:void(0)" class="nav-item" data-section="my-clients"><span>users</span> My Clients</a>
                <a href="javascript:void(0)" class="nav-item" data-section="create-client"><span>👨‍👩‍👧‍👦</span> Create Paid Client</a>
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
                            <?php
                            $user_state = get_user_meta($user->ID, 'state', true);
                            $user_city = get_user_meta($user->ID, 'city', true);

                            if ($user_state && $user_city) {
                                // Area Manager with assigned location
                                echo '<div class="form-group">';
                                echo '<label>Project Location</label>';
                                echo '<input type="text" value="' . esc_attr($user_city . ', ' . $user_state) . '" readonly class="form-control-plaintext">';
                                echo '<input type="hidden" name="project_state" id="project_state" value="' . esc_attr($user_state) . '">';
                                echo '<input type="hidden" name="project_city" id="project_city" value="' . esc_attr($user_city) . '">';
                                echo '</div>';
                            } else {
                                // Admin or Manager without assigned location
                                ?>
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
                                <?php
                            }
                            ?>
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
                                <label>Vendor Assignment Method</label>
                                <div class="assignment-method-options" style="display: flex; gap: 20px; margin-top: 5px;">
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="radio" name="vendor_assignment_method" value="manual" checked style="margin-right: 8px;"> 
                                        Manual Assignment
                                    </label>
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="radio" name="vendor_assignment_method" value="bidding" style="margin-right: 8px;"> 
                                        Bidding System
                                    </label>
                                </div>
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

                <!-- Leads Section -->
                <section id="leads-section" class="section-content" style="display:none;">
                    <h2>Lead Management</h2>
                    <div class="card">
                        <h3>Add New Lead</h3>
                        <form id="create-lead-form">
                            <div class="form-group">
                                <label for="lead_name">Name</label>
                                <input type="text" id="lead_name" name="lead_name" required>
                            </div>
                            <div class="form-group">
                                <label for="lead_phone">Phone Number</label>
                                <input type="text" id="lead_phone" name="lead_phone" required>
                            </div>
                            <div class="form-group">
                                <label for="lead_email">Email</label>
                                <input type="email" id="lead_email" name="lead_email">
                            </div>
                            <div class="form-group">
                                <label for="lead_status">Status</label>
                                <select id="lead_status" name="lead_status">
                                    <option value="new">New</option>
                                    <option value="contacted">Contacted</option>
                                    <option value="interested">Interested</option>
                                    <option value="converted">Converted</option>
                                    <option value="lost">Lost</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="lead_notes">Notes</label>
                                <textarea id="lead_notes" name="lead_notes" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Lead</button>
                            <div id="create-lead-feedback" style="margin-top:15px;"></div>
                        </form>
                    </div>

                    <div class="card" style="margin-top: 20px;">
                        <h3>Your Leads</h3>
                        <div id="leads-list-container">
                            <p>Loading leads...</p>
                        </div>
                    </div>
                </section>

                <!-- My Clients Section -->
                <section id="my-clients-section" class="section-content" style="display:none;">
                    <h2>My Clients</h2>
                    <div class="card">
                        <div id="my-clients-list-container">
                            <p>Loading clients...</p>
                        </div>
                    </div>
                </section>

                <!-- Reset Password Modal -->
                <div id="reset-password-modal" class="modal" style="display:none;">
                    <div class="modal-content">
                        <span class="close-modal">&times;</span>
                        <h3>Reset Password for <span id="reset-password-client-name"></span></h3>
                        <form id="reset-password-form">
                            <input type="hidden" id="reset_password_client_id">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <div style="position: relative;">
                                    <input type="password" id="new_password" name="new_password" required style="width: 100%; padding-right: 80px;">
                                    <span class="toggle-password" data-target="new_password" style="position: absolute; right: 40px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 2;">👁️</span>
                                    <button type="button" class="generate-password-btn" data-target="new_password" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer; z-index: 2;" title="Generate Password">🎲</button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Reset Password</button>
                            <div id="reset-password-feedback" style="margin-top:10px;"></div>
                        </form>
                    </div>
                </div>

                <!-- Message Modal -->
                <div id="message-modal" class="modal" style="display:none;">
                    <div class="modal-content">
                        <span class="close-modal">&times;</span>
                        <h3>Send Message</h3>
                        <form id="send-message-form">
                            <input type="hidden" id="msg_lead_id">
                            <input type="hidden" id="msg_type">
                            <div class="form-group">
                                <label>To: <span id="msg_recipient"></span></label>
                            </div>
                            <div class="form-group">
                                <label for="msg_content">Message</label>
                                <textarea id="msg_content" name="msg_content" rows="5" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Send</button>
                            <div id="send-message-feedback" style="margin-top:10px;"></div>
                        </form>
                    </div>
                </div>

                <!-- Create Client Section -->
                <section id="create-client-section" class="section-content" style="display:none;">
                    <div class="card">
                        <h3>Create Paid Client Account</h3>
                        <div class="alert alert-info">
                            <p><strong>Note:</strong> Only create accounts for clients who have paid and are ready to start a project. This gives them access to the client dashboard.</p>
                        </div>
                        <form id="create-client-form">
                            <div class="form-group">
                                <label for="client_name">Name</label>
                                <input type="text" id="client_name" name="client_name" required>
                            </div>
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
                                <div style="position: relative;">
                                    <input type="password" id="client_password" name="client_password" required style="width: 100%; padding-right: 80px;">
                                    <span class="toggle-password" data-target="client_password" style="position: absolute; right: 40px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 2;">👁️</span>
                                    <button type="button" class="generate-password-btn" data-target="client_password" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer; z-index: 2;" title="Generate Password">🎲</button>
                                </div>
                                <div id="password-strength-meter" style="height: 5px; background: #eee; margin-top: 5px; width: 100%; border-radius: 3px;">
                                    <div id="password-strength-bar" style="height: 100%; width: 0%; background: red; transition: width 0.3s, background 0.3s; border-radius: 3px;"></div>
                                </div>
                                <small id="password-strength-text" style="display: block; margin-top: 2px; font-size: 12px; color: #666;"></small>
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