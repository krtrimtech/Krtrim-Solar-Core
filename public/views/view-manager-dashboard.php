<?php
/**
 * Shortcode and logic for the Manager frontend dashboard.
 * Manager has multi-state access and can assign areas to Area Managers.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_manager_dashboard_shortcode() {
    // Security check
    require_once plugin_dir_path(__FILE__) . '../../includes/components/class-cleaner-component.php';

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( get_permalink() ) );
        exit;
    }

    $current_user = wp_get_current_user();
    $user_roles   = $current_user->roles;

    if ( ! in_array( 'manager', $user_roles, true ) && ! in_array( 'administrator', $user_roles, true ) ) {
        // Redirect non-managers to appropriate dashboard
        if ( in_array( 'area_manager', $user_roles, true ) ) {
            $dashboard_url = get_permalink( get_page_by_path( 'area-manager-dashboard' ) );
            wp_safe_redirect( $dashboard_url );
            exit;
        } elseif ( in_array( 'solar_client', $user_roles, true ) || in_array( 'solar_vendor', $user_roles, true ) ) {
            $dashboard_url = get_permalink( get_page_by_path( 'solar-dashboard' ) );
            wp_safe_redirect( $dashboard_url );
            exit;
        } else {
            wp_safe_redirect( admin_url() );
            exit;
        }
    }

    $user = wp_get_current_user();

    // Handle Award Bid Action (Frontend)
    if (isset($_POST['action']) && $_POST['action'] === 'am_award_bid' && isset($_POST['bid_nonce']) && wp_verify_nonce($_POST['bid_nonce'], 'am_award_bid_action')) {
        $project_id = intval($_POST['project_id']);
        $vendor_id = intval($_POST['vendor_id']);
        $bid_amount = floatval($_POST['bid_amount']);
        
        // Verify Project Ownership (Security)
        $project_owner = get_post_meta($project_id, '_created_by_area_manager', true);
        // Fallback to post_author if meta not set
        if (!$project_owner) {
            $project = get_post($project_id);
            $project_owner = $project->post_author;
        }

        if ($project_owner == $user->ID || current_user_can('administrator')) {
             update_post_meta($project_id, 'winning_vendor_id', $vendor_id);
            update_post_meta($project_id, 'winning_bid_amount', $bid_amount);
            update_post_meta($project_id, '_assigned_vendor_id', $vendor_id);
            update_post_meta($project_id, '_total_project_cost', $bid_amount);
            update_post_meta($project_id, 'project_status', 'assigned');
            
            // Notify Vendor
            $winning_vendor = get_userdata($vendor_id);
            $project_title = get_the_title($project_id);
            if ($winning_vendor) {
                $subject = 'Congratulations! You Won the Bid for Project: ' . $project_title;
                $message = "Congratulations! Your bid of ₹" . number_format($bid_amount, 2) . " for project '" . $project_title . "' has been accepted.";
                wp_mail($winning_vendor->user_email, $subject, $message);
            }
            
            echo '<div class="alert alert-success" style="margin: 20px;">Project awarded successfully!</div>';
        } else {
             echo '<div class="alert alert-danger" style="margin: 20px;">Permission denied. You do not own this project.</div>';
        }
    }

    ob_start();
    ?>
    <div id="managerDashboard" class="modern-solar-dashboard manager-dashboard">
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
                <a href="javascript:void(0)" class="nav-item" data-section="bid-management"><span>🔨</span> Bid Management</a>

                <a href="javascript:void(0)" class="nav-item" data-section="leads"><span>🎯</span> Leads</a>
                <a href="javascript:void(0)" class="nav-item" data-section="my-clients"><span>💼</span> My Clients</a>
                
                <a href="javascript:void(0)" class="nav-item" data-section="manage-cleaners"><span>🧹</span> Manage Cleaners</a>
                <a href="javascript:void(0)" class="nav-item" data-section="cleaning-services"><span>🧼</span> Cleaning Services</a>
                
                <!-- Manager-Specific Sections -->
                <div style="margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                    <p style="font-size: 11px; text-transform: uppercase; color: #94a3b8; margin-bottom: 10px; padding: 0 20px; font-weight: 600;">Manager Tools</p>
                </div>
                <a href="javascript:void(0)" class="nav-item" data-section="team-analysis"><span>👥</span> Team Overview</a>
                <a href="javascript:void(0)" class="nav-item" data-section="am-assignment"><span>🗺️</span> AM Assignment</a>
            </nav>
            <div class="sidebar-profile">
                <div class="profile-info">
                    <h4><?php echo esc_html($user->display_name); ?></h4>
                    <p>Manager</p>
                </div>
                <!-- Assigned States -->
                <div class="supervisor-info" style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                    <p style="font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.6); margin-bottom: 5px;">Assigned States</p>
                    <?php
                    $assigned_states = get_user_meta($user->ID, '_assigned_states', true);
                    if (!empty($assigned_states) && is_array($assigned_states)) {
                        echo '<p style="font-size: 13px; font-weight: 500;">' . esc_html(implode(', ', $assigned_states)) . '</p>';
                    } else {
                        echo '<p style="font-size: 12px; opacity: 0.8;">No states assigned</p>';
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
                <!--Dashboard Section -->
                <section id="dashboard-section" class="section-content">
                    <!-- Stats Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">📊</div>
                            <div class="stat-details">
                                <h3 id="total-projects-stat">0</h3>
                                <span>Total Projects</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">💰</div>
                            <div class="stat-details">
                                <h3 id="total-revenue-stat">₹0</h3>
                                <span>Total Revenue</span>
                            </div>
                        </div>
                        <div class="stat-card stat-success">
                            <div class="stat-icon">✅</div>
                            <div class="stat-details">
                                <h3 id="client-payments-stat">₹0</h3>
                                <span>Client Payments Collected</span>
                            </div>
                        </div>
                        <div class="stat-card stat-warning">
                            <div class="stat-icon">⏳</div>
                            <div class="stat-details">
                                <h3 id="outstanding-balance-stat">₹0</h3>
                                <span>Outstanding Balance</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">💸</div>
                            <div class="stat-details">
                                <h3 id="total-costs-stat">₹0</h3>
                                <span>Vendor Costs</span>
                            </div>
                        </div>
                        <div class="stat-card stat-highlight">
                            <div class="stat-icon">💵</div>
                            <div class="stat-details">
                                <h3 id="total-profit-stat">₹0</h3>
                                <span>Company Profit</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">📈</div>
                            <div class="stat-details">
                                <h3 id="profit-margin-stat">0%</h3>
                                <span>Profit Margin</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">📊</div>
                            <div class="stat-details">
                                <h3 id="collection-rate-stat">0%</h3>
                                <span>Collection Rate</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">👥</div>
                            <div class="stat-details">
                                <h3 id="total-leads-stat">0</h3>
                                <span>Total Leads</span>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="charts-grid">
                        <div class="chart-card">
                            <h3>📊 Project Status Distribution</h3>
                            <div class="chart-container">
                                <canvas id="project-status-chart"></canvas>
                            </div>
                        </div>
                        <div class="chart-card">
                            <h3>📈 Monthly Project Trends</h3>
                            <div class="chart-container">
                                <canvas id="monthly-trend-chart"></canvas>
                            </div>
                        </div>
                        <div class="chart-card">
                            <h3>💰 Financial Overview</h3>
                            <div class="chart-container">
                                <canvas id="financial-chart"></canvas>
                            </div>
                        </div>
                        <div class="chart-card">
                            <h3>🎯 Lead Conversion</h3>
                            <div class="chart-container">
                                <canvas id="lead-chart"></canvas>
                            </div>
                        </div>
                    </div>


                </section>

                <!-- Projects List Section -->
                <section id="projects-section" class="section-content" style="display:none;">
                    <div class="section-header">
                        <h2 class="section-title">Your Projects</h2>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="jQuery('.nav-item[data-section=create-project]').click()">
                                ➕ New Project
                            </button>
                        </div>
                    </div>
                    
                    <!-- Project Filters - Simplified -->
                    <div class="content-card" style="margin-bottom: 20px;">
                        <div class="filter-row" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: end;">
                            <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                                <label for="filter-status">Status</label>
                                <select id="filter-status" class="project-filter">
                                    <option value="">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                                <label for="filter-date-preset">Date</label>
                                <select id="filter-date-preset" class="project-filter">
                                    <option value="">Show All</option>
                                    <option value="today">Today</option>
                                    <option value="yesterday">Yesterday</option>
                                    <option value="week">This Week</option>
                                    <option value="custom">Custom Date</option>
                                </select>
                            </div>
                            <div class="form-group" id="custom-date-wrapper" style="margin: 0; flex: 1; min-width: 200px; display: none;">
                                <label for="filter-custom-date">Select Date</label>
                                <input type="date" id="filter-custom-date" class="project-filter">
                            </div>
                            <button class="btn btn-secondary clear-project-filters-btn" style="margin: 0;">
                                🔄 Clear
                            </button>
                        </div>
                    </div>
                    
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
                            <?php wp_nonce_field('sp_create_project_nonce_field', 'sp_create_project_nonce'); ?>
                            <div class="form-group">
                                <label for="project_title">Project Title</label>
                                <input type="text" id="project_title" name="project_title" required>
                            </div>
                            <div class="form-group">
                                <label for="project_description">Project Description</label>
                                <textarea id="project_description" name="project_description" rows="5" placeholder="Enter detailed information about the project, requirements, specifications, etc."></textarea>
                                <small style="color: #666;">Provide detailed information about the project that will be visible to vendors and clients.</small>
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
                                    'id' => 'client_user_id',
                                    'show_option_none' => 'Select Client',
                                    'selected' => 0,
                                ) );
                                ?>
                            </div>
                            
                            <!-- Financial Field -->
                            <div class="form-group">
                                <label for="total_project_cost">Total Project Cost (₹) *</label>
                                <input type="number" id="total_project_cost" name="total_project_cost" step="0.01" min="0" required style="width: 100%;" placeholder="Amount client will pay">
                                <small style="color: #666;">Total amount the client will pay for this project. Profit will be calculated based on vendor bid/assignment.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="paid_amount">Amount Paid by Client (₹)</label>
                                <input type="number" id="paid_amount" name="paid_amount" step="0.01" min="0" style="width: 100%;" placeholder="Token/advance received">
                                <small style="color: #666;">Optional: Amount already received from client (token money, advance, etc.)</small>
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
                                <select name="assigned_vendor_id" id="assigned_vendor_id">
                                    <option value="">Select Vendor</option>
                                    <?php
                                    // Get area manager's assigned city
                                    $manager_city = get_user_meta(get_current_user_id(), 'city', true);
                                    
                                    // Get all vendors
                                    $all_vendors = get_users(array('role' => 'solar_vendor'));
                                    
                                    // Filter vendors who have coverage for this specific city
                                    foreach ($all_vendors as $vendor) {
                                        $purchased_cities = get_user_meta($vendor->ID, 'purchased_cities', true) ?: array();
                                        
                                        // Check if vendor has purchased this specific city
                                        $has_city_coverage = false;
                                        if (is_array($purchased_cities)) {
                                            foreach ($purchased_cities as $city_obj) {
                                                // Handle both array and string formats
                                                $city_name = '';
                                                if (is_array($city_obj) && isset($city_obj['city'])) {
                                                    $city_name = $city_obj['city'];
                                                } elseif (is_string($city_obj)) {
                                                    $city_name = $city_obj;
                                                }
                                                
                                                if ($city_name === $manager_city) {
                                                    $has_city_coverage = true;
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        // Only show vendor if they have coverage for this city
                                        if ($has_city_coverage) {
                                            echo '<option value="' . esc_attr($vendor->ID) . '">' . esc_html($vendor->display_name) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <small style="color: #666;">Only vendors with coverage for <?php echo esc_html($manager_city); ?> are shown</small>
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


                <!-- Bid Management Section -->
                <section id="bid-management-section" class="section-content" style="display:none;">
                    <div class="section-header">
                        <h2 class="section-title">Bid Management</h2>
                        <p style="color: #666; margin-top: 8px;">View and award bids for your projects</p>
                    </div>
                    
                    <div id="bid-management-container">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Loading bids...</p>
                        </div>
                    </div>
                </section>


                <!-- Leads Section - Using Shared Component -->
                <section id="leads-section" class="section-content" style="display:none;">
                    <?php 
                    // Include and render shared lead component
                    require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/components/class-lead-component.php';
                    KSC_Lead_Component::render_lead_section($user_roles, 'area_manager');
                    ?>
                </section>

                <!-- My Clients Section -->
                <section id="my-clients-section" class="section-content" style="display:none;">
                    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0;">🧑‍💼 My Clients</h2>
                        <button id="open-create-client-modal" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px;">
                            <span>➕</span> Add New Client
                        </button>
                    </div>
                    <div class="card">
                        <div id="my-clients-container">
                            <p>Loading clients...</p>
                        </div>
                    </div>
                </section>

                <!-- Create Client Modal -->
                <div id="create-client-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
                    <div class="modal-box" style="background: #fff; border-radius: 12px; padding: 30px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 style="margin: 0;">➕ Create Paid Client</h3>
                            <button id="close-create-client-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
                        </div>
                        <div class="alert alert-info" style="background: #e3f2fd; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #1976d2;">
                            <p style="margin: 0; font-size: 14px;"><strong>Note:</strong> Only create accounts for clients who have paid and are ready to start a project.</p>
                        </div>
                        <form id="create-client-form">
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="client_name" style="display: block; margin-bottom: 5px; font-weight: 600;">Name *</label>
                                <input type="text" id="client_name" name="client_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            </div>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="client_username" style="display: block; margin-bottom: 5px; font-weight: 600;">Username *</label>
                                <input type="text" id="client_username" name="client_username" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            </div>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="client_email" style="display: block; margin-bottom: 5px; font-weight: 600;">Email *</label>
                                <input type="email" id="client_email" name="client_email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            </div>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="client_password" style="display: block; margin-bottom: 5px; font-weight: 600;">Password *</label>
                                <div style="display: flex; align-items: stretch; gap: 5px;">
                                    <input type="password" id="client_password" name="client_password" required style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                    <button type="button" class="toggle-password" data-target="client_password" style="padding: 10px 15px; border: 1px solid #ddd; background: #f8f9fa; cursor: pointer; border-radius: 6px; font-size: 18px;" title="Toggle Password">👁️</button>
                                    <button type="button" class="generate-password-btn" data-target="client_password" style="padding: 10px 15px; border: 1px solid #ddd; background: #f8f9fa; cursor: pointer; border-radius: 6px; font-size: 18px;" title="Generate Password">🎲</button>
                                </div>
                                <div id="password-strength-meter" style="height: 5px; background: #eee; margin-top: 5px; border-radius: 3px;">
                                    <div id="password-strength-bar" style="height: 100%; width: 0%; background: red; transition: all 0.3s; border-radius: 3px;"></div>
                                </div>
                                <small id="password-strength-text" style="display: block; margin-top: 2px; font-size: 12px; color: #666;"></small>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">Create Client</button>
                            <div id="create-client-feedback" style="margin-top: 15px;"></div>
                        </form>
                    </div>
                </div>

                <!-- Manage Cleaners Section -->
                <!-- Manage Cleaners Section -->
                <section id="manage-cleaners-section" class="section-content" style="display:none;">
                    <?php 
                    // Use Shared Cleaner Component
                    if (class_exists('KSC_Cleaner_Component')) {
                        KSC_Cleaner_Component::render_cleaner_section($user_roles, 'manager'); 
                    } else {
                        echo '<p>Error: Cleaner component not loaded.</p>';
                    }
                    ?>
                </section>

                <!-- Cleaning Services Section -->
                <section id="cleaning-services-section" class="section-content" style="display:none;">
                    <div class="section-header">
                        <h2 class="section-title">Cleaning Services</h2>
                        <div class="section-actions">
                            <div class="search-box">
                                <input type="text" id="cleaning-search" placeholder="Search services..." />
                                <span class="search-icon">🔍</span>
                            </div>
                            <select id="filter-cleaning-status" style="padding: 10px; border: 2px solid var(--border-color); border-radius: 8px;">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Cleaning Bookings Table -->
                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table" id="cleaning-services-table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Plan</th>
                                        <th>Visits</th>
                                        <th>Next Visit</th>
                                        <th>Assigned Cleaner</th>
                                        <th>Payment Option</th>
                                        <th>Payment Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="cleaning-services-tbody">
                                    <tr><td colspan="8">Loading cleaning services...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Team Overview Section (Manager Only) -->
                <section id="team-analysis-section" class="section-content" style="display:none;">
                    <div class="section-header">
                        <h2 class="section-title">👥 Team Overview</h2>
                        <p style="color: #666; margin-top: 8px;">Complete overview of Area Managers, Sales Managers, and Cleaners across your organization</p>
                    </div>
                    
                    <!-- Team Stats -->
                    <div class="stats-grid" style="margin-bottom: 30px;">
                        <div class="stat-card">
                            <div class="stat-icon">👔</div>
                            <div class="stat-details">
                                <h3 id="team-am-count">0</h3>
                                <span>Area Managers</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">👥</div>
                            <div class="stat-details">
                                <h3 id="team-sm-count">0</h3>
                                <span>Sales Managers</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">🧹</div>
                            <div class="stat-details">
                                <h3 id="team-cleaner-count">0</h3>
                                <span>Cleaners</span>
                            </div>
                        </div>
                        <div class="stat-card stat-success">
                            <div class="stat-icon">🏗️</div>
                            <div class="stat-details">
                                <h3 id="team-project-count">0</h3>
                                <span>Total Projects</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Area Managers Table -->
                    <div class="card" style="margin-bottom: 20px;">
                        <h3>👔 Area Managers</h3>
                        <div class="table-responsive">
                            <table class="data-table" id="team-am-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>City</th>
                                        <th>State</th>
                                        <th>Projects</th>
                                        <th>Team Size</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="team-am-tbody">
                                    <tr><td colspan="6">Loading area managers...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Sales Managers Table -->
                    <div class="card" style="margin-bottom: 20px;">
                        <h3>👥 Sales Managers</h3>
                        <div class="table-responsive">
                            <table class="data-table" id="team-sm-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Supervising AM</th>
                                        <th>Leads</th>
                                        <th>Conversions</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="team-sm-tbody">
                                    <tr><td colspan="5">Loading sales managers...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Cleaners Table -->
                    <div class="card">
                        <h3>🧹 Cleaners</h3>
                        <div class="table-responsive">
                            <table class="data-table" id="team-cleaners-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Supervising AM</th>
                                        <th>Completed Visits</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="team-cleaners-tbody">
                                    <tr><td colspan="5">Loading cleaners...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- AM Assignment Section (Manager Only) -->
                <section id="am-assignment-section" class="section-content" style="display:none;">
                    <div class="section-header">
                        <h2 class="section-title">🗺️ AM Assignment</h2>
                        <p style="color: #666; margin-top: 8px;">Assign cities to Area Managers within your assigned states</p>
                    </div>
                    
                    <!-- Assignment Form -->
                    <div class="card" style="margin-bottom: 20px;">
                        <h3>➕ Assign Area to Area Manager</h3>
                        <form id="assign-am-location-form">
                            <div class="form-row" style="display: flex; gap: 15px; flex-wrap: wrap;">
                                <div class="form-group" style="flex: 1; min-width: 200px;">
                                    <label for="assign_am_id">Select Area Manager *</label>
                                    <select id="assign_am_id" name="manager_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                        <option value="">Loading area managers...</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 200px;">
                                    <label for="assign_state">State *</label>
                                    <select id="assign_state" name="state" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                        <option value="">Select state</option>
                                        <?php
                                        $manager_states = get_user_meta($user->ID, '_assigned_states', true);
                                        
                                        // If manager has assigned states, show only those
                                        // Otherwise, show all Indian states (Manager can assign anywhere)
                                        $states_to_show = [];
                                        if (!empty($manager_states) && is_array($manager_states)) {
                                            $states_to_show = $manager_states;
                                        } else {
                                            // All Indian states
                                            $states_to_show = [
                                                'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
                                                'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
                                                'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
                                                'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
                                                'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
                                                'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
                                                'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Puducherry'
                                            ];
                                        }
                                        
                                        foreach ($states_to_show as $state) {
                                            echo '<option value="' . esc_attr($state) . '">' . esc_html($state) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 200px;">
                                    <label for="assign_city">City *</label>
                                    <select id="assign_city" name="city" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                        <option value="">Select state first</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="margin-top: 15px;">Assign Area</button>
                            <div id="assign-am-feedback" style="margin-top: 15px;"></div>
                        </form>
                    </div>
                    
                    <!-- Current Assignments -->
                    <div class="card">
                        <h3>📍 Current Area Assignments</h3>
                        <div class="table-responsive">
                            <table class="data-table" id="am-assignments-table">
                                <thead>
                                    <tr>
                                        <th>Area Manager</th>
                                        <th>State</th>
                                        <th>City</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="am-assignments-tbody">
                                    <tr><td colspan="4">Loading assignments...</td></tr>
                                </tbody>
                            </table>
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

            </main>
        </div>
    </div>

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

    <!-- Project Detail Modal -->
    <div class="project-detail-modal" id="projectDetailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalProjectTitle">Project Details</h2>
                <button class="modal-close">×</button>
            </div>
            <div class="modal-body">
                <div class="detail-section">
                    <h3>📋 Project Information</h3>
                    <div class="detail-grid" id="modalProjectInfo"></div>
                </div>
                <div class="detail-section">
                    <h3>👤 Client Details</h3>
                    <div class="detail-grid" id="modalClientInfo"></div>
                </div>
                <div class="detail-section">
                    <h3>🔧 Vendor Details</h3>
                    <div class="detail-grid" id="modalVendorInfo"></div>
                </div>
                <div class="detail-section">
                    <h3>📊 Project Progress</h3>
                    <div class="progress-overview">
                        <p class="progress-percentage" id="modalProgressPercentage">0%</p>
                        <p class="progress-label">Complete</p>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" id="modalProgressBar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="step-list" id="modalProgressSteps"></div>
                </div>
            </div>
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
                    <p id="schedule_customer_name" style="font-weight: 600; margin: 5px 0;"></p>
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


    <!-- Team Member Detail Modal -->
    <div id="team-member-modal" class="modal" style="display:none;">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h2 id="member-modal-name">Team Member Details</h2>
                <span class="close-modal" id="close-member-modal">&times;</span>
            </div>
            <div class="modal-body" id="member-detail-content">
                <p style="text-align: center; padding: 40px; color: #666;">Loading member details...</p>
            </div>
        </div>
    </div>

    <!-- Service Details Modal -->
    <div id="service-details-modal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <span class="close-modal" onclick="document.getElementById('service-details-modal').style.display='none'">&times;</span>
            <h3 id="service-details-title">Service Details</h3>
            
            <div id="service-details-content" style="margin-top: 20px;">
                <p style="text-align: center; color: #666;">Loading...</p>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>


    <?php
    return ob_get_clean();
}

// Shortcode registration moved to unified-solar-dashboard.php to avoid duplicate registration