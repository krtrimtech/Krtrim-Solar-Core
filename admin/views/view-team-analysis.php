<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_render_team_analysis_page() {
    $manager_id = isset($_GET['manager_id']) ? intval($_GET['manager_id']) : 0;

    if ($manager_id > 0) {
        $manager = get_userdata($manager_id);
        if ($manager && (in_array('area_manager', (array)$manager->roles) || in_array('manager', (array)$manager->roles))) {
             sp_render_single_manager_view($manager_id);
        } else {
             sp_render_leaderboard_view();
        }
    } else {
        sp_render_leaderboard_view();
    }
}

function sp_render_leaderboard_view() {
    $active_tab = isset($_GET['team_tab']) ? sanitize_text_field($_GET['team_tab']) : 'managers';
    
    global $wpdb;
    $table_followups = $wpdb->prefix . 'solar_lead_followups';
    
    // Get Sales Managers with enhanced lead/follow-up data
    $sales_managers = get_users(['role' => 'sales_manager']);
    $sm_data = [];
    
    // Get Managers (Top Level)
    $managers = get_users(['role' => 'manager']);
    $manager_data = [];

    foreach ($managers as $mgr) {
        // Get all leads created by this Manager
        $mgr_leads = get_posts([
            'post_type' => 'solar_lead',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'author' => $mgr->ID // Leads created by Manager have them as author
        ]);
        $total_mgr_leads = count($mgr_leads);
        
        // Count lead statuses
        $mgr_lead_statuses = ['new' => 0, 'contacted' => 0, 'interested' => 0, 'converted' => 0, 'lost' => 0];
        foreach ($mgr_leads as $lead) {
            $status = get_post_meta($lead->ID, '_lead_status', true) ?: 'new';
            if (isset($mgr_lead_statuses[$status])) {
                $mgr_lead_statuses[$status]++;
            }
        }
        
        // Get follow-up count (sales_manager_id column stores the actor ID)
        $mgr_followup_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_followups} WHERE sales_manager_id = %d",
            $mgr->ID
        ));


        // Get assigned Area Managers
        // Logic: If manager has NO assigned states, they see ALL AMs (global access)
        //        If manager has assigned states, they only see AMs in those states
        $manager_assigned_states = get_user_meta($mgr->ID, '_assigned_states', true);
        
        if (empty($manager_assigned_states)) {
            // No states assigned = Global access to ALL Area Managers
            $assigned_ams = get_users([
                'role' => 'area_manager',
                'fields' => ['ID', 'display_name']
            ]);
        } else {
            // Has assigned states = Only show AMs supervised by this manager
            $assigned_ams = get_users([
                'role' => 'area_manager',
                'meta_key' => '_supervised_by_manager',
                'meta_value' => $mgr->ID,
                'fields' => ['ID', 'display_name']
            ]);
        }
        
        $regions = [];
        $total_team_size = count($assigned_ams); // Start with AM count
        
        foreach ($assigned_ams as $am) {
            $state = get_user_meta($am->ID, 'state', true);
            $city = get_user_meta($am->ID, 'city', true);
            if ($city) $regions[] = $city;
            elseif ($state) $regions[] = $state;
            
            // Count SMs
            $sms = get_users([
                'role' => 'sales_manager',
                'meta_key' => '_assigned_area_manager',
                'meta_value' => $am->ID,
                'fields' => 'ID'
            ]);
            $total_team_size += count($sms);
            
            // Count Cleaners
            $cleaners = get_users([
                'role' => 'solar_cleaner',
                'meta_key' => '_supervised_by_area_manager',
                'meta_value' => $am->ID,
                'fields' => 'ID'
            ]);
            $total_team_size += count($cleaners);
        }
        
        $manager_data[] = [
            'id' => $mgr->ID,
            'name' => $mgr->display_name,
            'email' => $mgr->user_email,
            'phone' => get_user_meta($mgr->ID, 'phone_number', true),
            'total_leads' => $total_mgr_leads,
            'lead_statuses' => $mgr_lead_statuses,
            'followup_count' => intval($mgr_followup_count),
            'conversion_rate' => $total_mgr_leads > 0 ? round(($mgr_lead_statuses['converted'] / $total_mgr_leads) * 100, 1) : 0,
            'assigned_ams_count' => count($assigned_ams),
            'regions' => array_unique($regions),
            'team_size' => $total_team_size,
            'assigned_states' => $manager_assigned_states ?: []
        ];
    }
    
    foreach ($sales_managers as $sm) {
        $assigned_am_id = get_user_meta($sm->ID, '_assigned_area_manager', true);
        $assigned_am_name = 'Unassigned';
        
        if ($assigned_am_id) {
            $am_user = get_userdata($assigned_am_id);
            if ($am_user) $assigned_am_name = $am_user->display_name;
        }

        // Get all leads created by this SM
        $leads = get_posts([
            'post_type' => 'solar_lead',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                ['key' => '_created_by_sales_manager', 'value' => $sm->ID]
            ]
        ]);
        $total_leads = count($leads);
        
        // Count lead statuses
        $lead_statuses = ['new' => 0, 'contacted' => 0, 'interested' => 0, 'converted' => 0, 'lost' => 0];
        foreach ($leads as $lead) {
            $status = get_post_meta($lead->ID, '_lead_status', true) ?: 'new';
            if (isset($lead_statuses[$status])) {
                $lead_statuses[$status]++;
            }
        }
        
        // Get follow-up count
        $followup_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_followups} WHERE sales_manager_id = %d",
            $sm->ID
        ));
        
        // Get cleaning services created by this SM
        $sm_cleaning_services = get_posts([
            'post_type' => 'cleaning_service',
            'posts_per_page' => -1,
            'author' => $sm->ID
        ]);
        
        $sm_data[] = [
            'id' => $sm->ID,
            'name' => $sm->display_name,
            'email' => $sm->user_email,
            'phone' => get_user_meta($sm->ID, 'phone_number', true),
            'assigned_am_id' => $assigned_am_id,
            'assigned_am_name' => $assigned_am_name,
            'total_leads' => $total_leads,
            'lead_statuses' => $lead_statuses,
            'followup_count' => intval($followup_count),
            'conversion_rate' => $total_leads > 0 ? round(($lead_statuses['converted'] / $total_leads) * 100, 1) : 0,
            'cleaning_services_count' => count($sm_cleaning_services),
        ];
    }
    
    // Get Area Managers
    $area_managers = get_users(['role' => 'area_manager']);
    $am_data = [];
    $chart_labels = [];
    $chart_projects = [];
    $chart_profit = [];
    
    // Cleaning-specific chart data
    $chart_cleaning_services = [];
    $chart_cleaning_revenue = [];
    
    // Global cleaning stats
    $total_cleaners_all = 0;
    $total_cleaning_services_all = 0;
    $total_cleaning_visits_all = 0;
    $total_cleaning_revenue_all = 0;

    foreach ($area_managers as $manager) {
        // 1. Get AM's own projects
        $am_projects = get_posts([
            'post_type' => 'solar_project',
            'author' => $manager->ID,
            'posts_per_page' => -1,
            'post_status' => ['publish', 'completed', 'assigned', 'in_progress'],
        ]);

        // 2. Get Sales Team (SMs assigned to this AM)
        $sales_team = get_users([
            'role' => 'sales_manager',
            'meta_key' => '_assigned_area_manager',
            'meta_value' => $manager->ID,
            'fields' => ['ID', 'display_name', 'user_email']
        ]);
        
        $team_names = wp_list_pluck($sales_team, 'display_name');
        $team_ids = wp_list_pluck($sales_team, 'ID');

        // 3. Aggregate Stats (AM Projects + SM Leads/Projects if applicable)
        $total_projects = count($am_projects);
        $paid_to_vendors = 0;
        $company_profit = 0;

        foreach ($am_projects as $project) {
            $paid = get_post_meta($project->ID, '_paid_to_vendor', true) ?: 0;
            $total_cost = get_post_meta($project->ID, '_total_project_cost', true) ?: 0;
            $profit = $total_cost - $paid;
            $company_profit += $profit;
        }
        
        // Count total leads from the team
        $team_leads = 0;
        $team_leads += count(get_posts(['post_type' => 'solar_lead', 'author' => $manager->ID, 'posts_per_page' => -1]));
        if (!empty($team_ids)) {
            $team_leads += count(get_posts(['post_type' => 'solar_lead', 'author__in' => $team_ids, 'posts_per_page' => -1]));
        }
        
        // === CLEANING DATA COLLECTION ===
        
        // Get cleaners supervised by this AM
        $cleaners = get_users([
            'role' => 'solar_cleaner',
            'meta_key' => '_supervised_by_area_manager',
            'meta_value' => $manager->ID,
        ]);
        
        $cleaners_data = [];
        foreach ($cleaners as $cleaner) {
            // Get visits for this cleaner
            $scheduled_visits = get_posts([
                'post_type' => 'cleaning_visit',
                'posts_per_page' => -1,
                'meta_query' => [
                    ['key' => '_cleaner_id', 'value' => $cleaner->ID],
                    ['key' => '_status', 'value' => 'scheduled'],
                ]
            ]);
            $completed_visits = get_posts([
                'post_type' => 'cleaning_visit',
                'posts_per_page' => -1,
                'meta_query' => [
                    ['key' => '_cleaner_id', 'value' => $cleaner->ID],
                    ['key' => '_status', 'value' => 'completed'],
                ]
            ]);
            
            $cleaners_data[] = [
                'id' => $cleaner->ID,
                'name' => $cleaner->display_name,
                'phone' => get_user_meta($cleaner->ID, 'phone', true),
                'scheduled_visits' => count($scheduled_visits),
                'completed_visits' => count($completed_visits),
            ];
        }
        
        // Get cleaning services assigned to this AM (user-booked)
        $am_cleaning_services = get_posts([
            'post_type' => 'cleaning_service',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => '_assigned_area_manager', 'value' => $manager->ID],
            ]
        ]);
        
        // Get cleaning services created by SMs under this AM
        $sm_cleaning_services = [];
        if (!empty($team_ids)) {
            $sm_cleaning_services = get_posts([
                'post_type' => 'cleaning_service',
                'posts_per_page' => -1,
                'author__in' => $team_ids,
            ]);
        }
        
        $all_am_services = array_merge($am_cleaning_services, $sm_cleaning_services);
        $am_cleaning_revenue = 0;
        $am_visits_total = 0;
        $am_visits_completed = 0;
        
        foreach ($all_am_services as $service) {
            $amount = floatval(get_post_meta($service->ID, '_total_amount', true));
            $payment_status = get_post_meta($service->ID, '_payment_status', true);
            if ($payment_status === 'paid') {
                $am_cleaning_revenue += $amount;
            }
            
            // Count visits for this service
            $service_visits = get_posts([
                'post_type' => 'cleaning_visit',
                'posts_per_page' => -1,
                'meta_key' => '_service_id',
                'meta_value' => $service->ID,
            ]);
            $am_visits_total += count($service_visits);
            
            foreach ($service_visits as $visit) {
                if (get_post_meta($visit->ID, '_status', true) === 'completed') {
                    $am_visits_completed++;
                }
            }
        }
        
        // Update global stats
        $total_cleaners_all += count($cleaners);
        $total_cleaning_services_all += count($all_am_services);
        $total_cleaning_visits_all += $am_visits_total;
        $total_cleaning_revenue_all += $am_cleaning_revenue;

        $am_data[] = [
            'id' => $manager->ID,
            'name' => $manager->display_name,
            'total_projects' => $total_projects,
            'total_leads' => $team_leads,
            'company_profit' => $company_profit,
            'assigned_state' => get_user_meta($manager->ID, 'state', true),
            'assigned_city' => get_user_meta($manager->ID, 'city', true),
            'sales_team' => $team_names,
            'team_ids' => $team_ids,
            // Cleaning data
            'cleaners' => $cleaners_data,
            'cleaners_count' => count($cleaners),
            'cleaning_services_count' => count($all_am_services),
            'cleaning_visits_total' => $am_visits_total,
            'cleaning_visits_completed' => $am_visits_completed,
            'cleaning_revenue' => $am_cleaning_revenue,
        ];

        $chart_labels[] = $manager->display_name;
        $chart_projects[] = $total_projects;
        $chart_profit[] = $company_profit;
        $chart_cleaning_services[] = count($all_am_services);
        $chart_cleaning_revenue[] = $am_cleaning_revenue;
    }
    
    // Prepare cleaning summary stats
    $cleaning_summary = [
        'total_cleaners' => $total_cleaners_all,
        'total_services' => $total_cleaning_services_all,
        'total_visits' => $total_cleaning_visits_all,
        'total_revenue' => $total_cleaning_revenue_all,
    ];
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Team Analysis</h1>
        <p>Monitor and analyze the performance of your Sales Managers and Area Managers.</p>
        
        <!-- Tabs -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=team-analysis&team_tab=managers" class="nav-tab <?php echo $active_tab == 'managers' ? 'nav-tab-active' : ''; ?>">🕴️ Managers</a>
            <a href="?page=team-analysis&team_tab=area_managers" class="nav-tab <?php echo $active_tab == 'area_managers' ? 'nav-tab-active' : ''; ?>">👔 Area Managers</a>
            <a href="?page=team-analysis&team_tab=sales_managers" class="nav-tab <?php echo $active_tab == 'sales_managers' ? 'nav-tab-active' : ''; ?>">📊 Sales Managers</a>
            <a href="?page=team-analysis&team_tab=cleaning_services" class="nav-tab <?php echo $active_tab == 'cleaning_services' ? 'nav-tab-active' : ''; ?>">🧹 Cleaning Services</a>
        </h2>
        
        <div class="analysis-dashboard">
            <?php if ($active_tab == 'sales_managers') : ?>
            <!-- SALES MANAGERS TAB - Enhanced with Lead/Follow-up Visibility -->
            <div class="leaderboard-container" style="grid-column: span 2;">
                <h2>Sales Manager Leaderboard</h2>
                <table class="wp-list-table widefat fixed striped users">
                    <thead>
                        <tr>
                            <th>Sales Manager</th>
                            <th>Contact</th>
                            <th>Area Manager</th>
                            <th>Leads</th>
                            <th>Follow-ups</th>
                            <th>Conversion</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($sm_data)) {
                            usort($sm_data, fn($a, $b) => $b['total_leads'] <=> $a['total_leads']);
                            foreach ($sm_data as $data) {
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($data['name']); ?></strong></td>
                                    <td>
                                        <?php echo esc_html($data['email']); ?><br>
                                        <small><?php echo esc_html($data['phone'] ?: 'No phone'); ?></small>
                                    </td>
                                    <td><?php echo esc_html($data['assigned_am_name']); ?></td>
                                    <td>
                                        <strong><?php echo $data['total_leads']; ?></strong>
                                        <br><small style="color: #666;">
                                            🆕 <?php echo $data['lead_statuses']['new']; ?> |
                                            📞 <?php echo $data['lead_statuses']['contacted']; ?> |
                                            ✅ <?php echo $data['lead_statuses']['converted']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span style="background: #e3f2fd; padding: 3px 8px; border-radius: 3px;">
                                            <?php echo $data['followup_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="background: <?php echo $data['conversion_rate'] >= 20 ? '#c8e6c9' : ($data['conversion_rate'] > 0 ? '#fff3e0' : '#ffebee'); ?>; padding: 3px 8px; border-radius: 3px;">
                                            <?php echo $data['conversion_rate']; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?page=team-analysis&team_tab=sm_leads&sm_id=<?php echo $data['id']; ?>" class="button button-small">View Leads</a>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="7">No Sales Managers found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <?php elseif ($active_tab == 'managers') : ?>
            <!-- MANAGERS TAB -->
            <div class="leaderboard-container" style="grid-column: span 2;">
                <h2>Managers Leaderboard</h2>
                <table class="wp-list-table widefat fixed striped users">
                    <thead>
                        <tr>
                            <th>Manager</th>
                            <th>Contact</th>
                            <th>Team Size</th>
                            <th>Leads (Direct)</th>
                            <th>Lead Status</th>
                            <th>Conversion</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($manager_data)) {
                            usort($manager_data, fn($a, $b) => $b['total_leads'] <=> $a['total_leads']);
                            foreach ($manager_data as $data) {
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($data['name']); ?></strong></td>
                                    <td>
                                        <?php echo esc_html($data['email']); ?><br>
                                        <small><?php echo esc_html($data['phone'] ?: 'No phone'); ?></small>
                                    </td>
                                    <td>
                                        <span class="team-badge" style="background:#f3e5f5; color:#7b1fa2; padding:2px 6px; border-radius:4px;">
                                            <?php echo $data['team_size']; ?> Members
                                        </span>
                                        <br><small style="color:#666;"><?php echo $data['assigned_ams_count']; ?> AMs</small>
                                    </td>
                                    <td><strong><?php echo $data['total_leads']; ?></strong></td>
                                    <td>
                                        <small style="color: #666;">
                                            🆕 <?php echo $data['lead_statuses']['new']; ?> |
                                            ✅ <?php echo $data['lead_statuses']['converted']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span style="background: <?php echo $data['conversion_rate'] >= 20 ? '#c8e6c9' : ($data['conversion_rate'] > 0 ? '#fff3e0' : '#ffebee'); ?>; padding: 3px 8px; border-radius: 3px;">
                                            <?php echo $data['conversion_rate']; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?page=team-analysis&team_tab=manager_leads&manager_id=<?php echo $data['id']; ?>" class="button button-small">Details</a>
                                        <button class="button button-small assign-states-btn" data-id="<?php echo $data['id']; ?>" data-name="<?php echo esc_attr($data['name']); ?>" data-states="<?php echo esc_attr(json_encode($data['assigned_states'])); ?>">Assign States</button>
                                        <a href="user-edit.php?user_id=<?php echo $data['id']; ?>" class="button button-small">Edit</a>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="7">No Managers found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <!-- Assign States Modal -->
            <div id="assign-states-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
                <div class="modal-box" style="background: #fff; border-radius: 8px; padding: 25px; width: 400px; max-width: 90%;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;">Assign States to <span id="assign-modal-manager-name"></span></h3>
                        <button class="close-modal-btn" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
                    </div>
                    
                    <form id="assign-states-form">
                        <input type="hidden" id="assign_states_manager_id" name="manager_id">
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="display:flex; align-items:center; font-weight:bold; margin-bottom:10px;">
                                <input type="checkbox" id="assign_all_states" name="assign_all" value="true">
                                Assign All States (Manager sees everything)
                            </label>
                        </div>
                        
                        <div id="states-list-container" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                            <?php
                            $indian_states = [
                                "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", 
                                "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", 
                                "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", 
                                "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal",
                                "Andaman and Nicobar Islands", "Chandigarh", "Dadra and Nagar Haveli and Daman and Diu", 
                                "Delhi", "Jammu and Kashmir", "Ladakh", "Lakshadweep", "Puducherry"
                            ];
                            
                            foreach ($indian_states as $state) {
                                echo '<label style="display:block; margin-bottom:5px;">';
                                echo '<input type="checkbox" name="states[]" value="' . esc_attr($state) . '" class="state-checkbox"> ' . esc_html($state);
                                echo '</label>';
                            }
                            ?>
                        </div>
                        <div style="margin-top: 5px; font-size: 12px; color: #666;">
                            * If "Assign All" is unchecked and no states are selected, Manager will see NO projects.
                        </div>
                        
                        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                            <button type="button" class="button close-modal-btn">Cancel</button>
                            <button type="submit" class="button button-primary" id="save-states-btn">Save Assignments</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Open Modal
                $('.assign-states-btn').on('click', function(e) {
                    e.preventDefault();
                    const managerId = $(this).data('id');
                    const managerName = $(this).data('name');
                    const assignedStates = $(this).data('states') || []; // Array of strings
                    
                    $('#assign_states_manager_id').val(managerId);
                    $('#assign-modal-manager-name').text(managerName);
                    
                    // Reset checkboxes
                    $('.state-checkbox').prop('checked', false);
                    $('#assign_all_states').prop('checked', false);
                    
                    if (assignedStates.length === 0) {
                        // If empty in DB, logic says "Global View" aka "All".
                        // But wait, the button data-states might be empty string if not set?
                        // PHP: json_encode(get_user_meta(...) ?: [])
                        // If user has NEVER been assigned, it is empty array.
                        // In my logic: Empty Array = All.
                        // So default check "All".
                        $('#assign_all_states').prop('checked', true);
                        $('#states-list-container').css('opacity', '0.5').css('pointer-events', 'none');
                    } else {
                        $('#states-list-container').css('opacity', '1').css('pointer-events', 'auto');
                        assignedStates.forEach(state => {
                            $(`input[name="states[]"][value="${state}"]`).prop('checked', true);
                        });
                    }
                    
                    $('#assign-states-modal').fadeIn(200).css('display', 'flex');
                });
                
                // Toggle All
                $('#assign_all_states').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('#states-list-container').css('opacity', '0.5').css('pointer-events', 'none');
                        $('.state-checkbox').prop('checked', false);
                    } else {
                        $('#states-list-container').css('opacity', '1').css('pointer-events', 'auto');
                    }
                });
                
                // Close Modal
                $('.close-modal-btn').on('click', function() {
                    $('#assign-states-modal').fadeOut(200);
                });
                
                // Submit Form
                $('#assign-states-form').on('submit', function(e) {
                    e.preventDefault();
                    const btn = $('#save-states-btn');
                    btn.text('Saving...').prop('disabled', true);
                    
                    const formData = $(this).serialize();
                    const nonce = '<?php echo wp_create_nonce("admin_manager_action_nonce"); ?>';
                    
                    $.post(ajaxurl, formData + '&action=update_manager_assigned_states&nonce=' + nonce, function(response) {
                        if (response.success) {
                            const data = response.data;
                            let message = 'States updated successfully!\n';
                            message += `✅ Total Area Managers supervised: ${data.total_supervised_ams || 0}\n`;
                            message += `➕ Newly assigned: ${data.assigned_count || 0}\n`;
                            message += `➖ Unassigned: ${data.unassigned_count || 0}`;
                            alert(message);
                            location.reload();
                        } else {
                            alert('Error: ' + (response.data.message || 'Unknown error'));
                            btn.text('Save Assignments').prop('disabled', false);
                        }
                    });
                });
            });
            </script>
            <?php elseif ($active_tab == 'cleaning_services') : ?>
            <!-- CLEANING SERVICES TAB -->
            <div class="leaderboard-container" style="grid-column: span 2;">
                <!-- Summary Stats Cards -->
                <div class="cleaning-stats-cards" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div class="stat-card" style="background: #e3f2fd; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 28px; font-weight: 700; color: #1565c0;"><?php echo $cleaning_summary['total_cleaners']; ?></div>
                        <div style="color: #666;">Total Cleaners</div>
                    </div>
                    <div class="stat-card" style="background: #e8f5e9; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 28px; font-weight: 700; color: #2e7d32;"><?php echo $cleaning_summary['total_services']; ?></div>
                        <div style="color: #666;">Total Services</div>
                    </div>
                    <div class="stat-card" style="background: #fff3e0; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 28px; font-weight: 700; color: #ef6c00;"><?php echo $cleaning_summary['total_visits']; ?></div>
                        <div style="color: #666;">Total Visits</div>
                    </div>
                    <div class="stat-card" style="background: #fce4ec; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 28px; font-weight: 700; color: #c2185b;">₹<?php echo number_format($cleaning_summary['total_revenue']); ?></div>
                        <div style="color: #666;">Total Revenue</div>
                    </div>
                </div>

                <!-- All Cleaners List -->
                <div class="leaderboard-container" style="grid-column: span 2; margin-bottom: 30px;">
                    <h2>🧹 All Cleaners</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Location</th>
                                <th>Supervisor</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $all_cleaners = get_users(['role' => 'solar_cleaner']);
                            if (!empty($all_cleaners)) {
                                foreach ($all_cleaners as $cleaner) {
                                    $sup_id = get_user_meta($cleaner->ID, '_supervised_by_area_manager', true);
                                    $sup_name = $sup_id ? get_userdata($sup_id)->display_name : 'Unassigned';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($cleaner->display_name); ?></strong></td>
                                        <td><?php echo esc_html(get_user_meta($cleaner->ID, 'phone', true)); ?></td>
                                        <td><?php echo esc_html(get_user_meta($cleaner->ID, 'city', true) . ', ' . get_user_meta($cleaner->ID, 'state', true)); ?></td>
                                        <td><?php echo esc_html($sup_name); ?></td>
                                        <td>
                                            <button class="button button-small admin-view-cleaner-btn" data-id="<?php echo $cleaner->ID; ?>">👤 Profile</button>
                                            <a href="user-edit.php?user_id=<?php echo $cleaner->ID; ?>" class="button button-small">✏️ Edit</a>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="5">No cleaners found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <h2>Cleaning Hierarchy by Area Manager</h2>
                <table class="wp-list-table widefat fixed striped users">
                    <thead>
                        <tr>
                            <th>Area Manager</th>
                            <th>Cleaners</th>
                            <th>Services</th>
                            <th>Visits (Completed/Total)</th>
                            <th>Revenue</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($am_data)) {
                            usort($am_data, fn($a, $b) => $b['cleaning_revenue'] <=> $a['cleaning_revenue']);
                            foreach ($am_data as $data) {
                                ?>
                                <tr class="am-row" data-am-id="<?php echo $data['id']; ?>">
                                    <td>
                                        <strong><a href="?page=team-analysis&manager_id=<?php echo $data['id']; ?>"><?php echo esc_html($data['name']); ?></a></strong>
                                        <br><small style="color: #666;"><?php echo esc_html($data['assigned_city'] . ', ' . $data['assigned_state']); ?></small>
                                    </td>
                                    <td>
                                        <span style="background: #e3f2fd; padding: 3px 8px; border-radius: 3px;">
                                            <?php echo $data['cleaners_count']; ?>
                                        </span>
                                        <?php if ($data['cleaners_count'] > 0) : ?>
                                        <button class="button button-small toggle-cleaners-btn" data-am-id="<?php echo $data['id']; ?>" style="margin-left: 5px;">Show</button>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $data['cleaning_services_count']; ?></td>
                                    <td>
                                        <strong><?php echo $data['cleaning_visits_completed']; ?></strong> / <?php echo $data['cleaning_visits_total']; ?>
                                    </td>
                                    <td>
                                        <strong style="color: #2e7d32;">₹<?php echo number_format($data['cleaning_revenue']); ?></strong>
                                    </td>
                                    <td>
                                        <a href="?page=team-analysis&manager_id=<?php echo $data['id']; ?>#cleaning" class="button button-small">Details</a>
                                    </td>
                                </tr>
                                <?php if (!empty($data['cleaners'])) : ?>
                                <tr class="cleaners-detail-row" data-am-id="<?php echo $data['id']; ?>" style="display: none; background: #f9f9f9;">
                                    <td colspan="6">
                                        <table class="wp-list-table widefat" style="margin: 10px 0;">
                                            <thead>
                                                <tr style="background: #eee;">
                                                    <th>Cleaner Name</th>
                                                    <th>Phone</th>
                                                    <th>Scheduled Visits</th>
                                                    <th>Completed Visits</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($data['cleaners'] as $cleaner) : ?>
                                                <tr>
                                                    <td><?php echo esc_html($cleaner['name']); ?></td>
                                                    <td><?php echo esc_html($cleaner['phone']); ?></td>
                                                    <td><?php echo $cleaner['scheduled_visits']; ?></td>
                                                    <td><?php echo $cleaner['completed_visits']; ?></td>
                                                    <td>
                                                        <button class="button button-small admin-view-cleaner-btn" data-id="<?php echo $cleaner['id']; ?>">👤 Profile</button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="6">No Area Managers found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- All Cleaning Services with Schedule Actions -->
            <div class="leaderboard-container" style="grid-column: span 2; margin-top: 30px;">
                <h2>📋 All Cleaning Services</h2>
                <p style="color: #666; margin-bottom: 15px;">Click on a service row to view details or schedule visits.</p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Plan</th>
                            <th>System</th>
                            <th>Visits (Used/Total)</th>
                            <th>Payment</th>
                            <th>Area Manager</th>
                            <th>Next Visit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="admin-cleaning-services-tbody">
                        <?php
                        $all_services = get_posts([
                            'post_type' => 'cleaning_service',
                            'posts_per_page' => -1,
                            'post_status' => 'publish',
                        ]);
                        
                        $plan_labels = [
                            'one_time' => 'One-Time',
                            'monthly' => 'Monthly',
                            '6_month' => '6-Month',
                            'yearly' => 'Yearly',
                        ];
                        
                        if (!empty($all_services)) {
                            foreach ($all_services as $service) {
                                $sid = $service->ID;
                                $customer_name = get_post_meta($sid, '_customer_name', true);
                                $customer_phone = get_post_meta($sid, '_customer_phone', true);
                                $plan_type = get_post_meta($sid, '_plan_type', true);
                                $system_size = get_post_meta($sid, '_system_size_kw', true);
                                $visits_total = intval(get_post_meta($sid, '_visits_total', true));
                                $visits_used = intval(get_post_meta($sid, '_visits_used', true));
                                $payment_status = get_post_meta($sid, '_payment_status', true);
                                $total_amount = floatval(get_post_meta($sid, '_total_amount', true));
                                $am_id = get_post_meta($sid, '_assigned_area_manager', true);
                                $am_name = $am_id ? get_userdata($am_id)->display_name : 'Unassigned';
                                
                                // Get next scheduled visit
                                $next_visit = get_posts([
                                    'post_type' => 'cleaning_visit',
                                    'meta_query' => [
                                        ['key' => '_service_id', 'value' => $sid],
                                        ['key' => '_status', 'value' => 'scheduled'],
                                    ],
                                    'meta_key' => '_scheduled_date',
                                    'orderby' => 'meta_value',
                                    'order' => 'ASC',
                                    'numberposts' => 1,
                                ]);
                                $next_date = !empty($next_visit) ? get_post_meta($next_visit[0]->ID, '_scheduled_date', true) : '';
                                ?>
                                <tr class="admin-service-row" data-id="<?php echo $sid; ?>" data-name="<?php echo esc_attr($customer_name); ?>" style="cursor: pointer;">
                                    <td>
                                        <strong><?php echo esc_html($customer_name); ?></strong><br>
                                        <small style="color: #666;"><?php echo esc_html($customer_phone); ?></small>
                                    </td>
                                    <td><?php echo esc_html($plan_labels[$plan_type] ?? $plan_type); ?></td>
                                    <td><?php echo $system_size; ?> kW</td>
                                    <td>
                                        <span style="background: <?php echo $visits_used >= $visits_total ? '#ffebee' : '#e8f5e9'; ?>; padding: 3px 8px; border-radius: 4px;">
                                            <?php echo $visits_used; ?> / <?php echo $visits_total; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="background: <?php echo $payment_status === 'paid' ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $payment_status === 'paid' ? '#047857' : '#b45309'; ?>; padding: 3px 8px; border-radius: 4px;">
                                            <?php echo ucfirst($payment_status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($am_name); ?></td>
                                    <td>
                                        <?php 
                                        $preferred_date = get_post_meta($sid, '_preferred_date', true);
                                        if ($next_date) : ?>
                                        <span style="color: #4f46e5;">✓ <?php echo $next_date; ?></span>
                                        <?php elseif ($preferred_date && $visits_used < $visits_total) : ?>
                                        <span style="color: #b45309;">⏳ Requested: <?php echo $preferred_date; ?></span>
                                        <?php elseif ($visits_used < $visits_total) : ?>
                                        <span style="color: #dc2626;">Not Scheduled</span>
                                        <?php else : ?>
                                        <span style="color: #9ca3af;">Complete</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($visits_used < $visits_total) : ?>
                                        <button class="button button-primary button-small admin-schedule-btn" 
                                                data-id="<?php echo $sid; ?>" 
                                                data-name="<?php echo esc_attr($customer_name); ?>"
                                                onclick="event.stopPropagation();">+ Schedule</button>
                                        <?php else : ?>
                                        <span style="color: #9ca3af;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="8">No cleaning services found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Admin Schedule Visit Modal -->
            <div id="admin-schedule-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
                <div class="modal-box" style="background: #fff; border-radius: 12px; padding: 30px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
                    <h3 style="margin-top: 0;">+ Schedule Cleaning Visit</h3>
                    <form id="admin-schedule-form">
                        <input type="hidden" id="admin_schedule_service_id">
                        <p><strong>Customer:</strong> <span id="admin_schedule_customer_name"></span></p>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Select Cleaner *</label>
                            <select id="admin_schedule_cleaner_id" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                <option value="">Loading cleaners...</option>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Date *</label>
                                <input type="date" id="admin_schedule_date" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            </div>
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Time *</label>
                                <input type="time" id="admin_schedule_time" value="09:00" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="button button-primary" style="flex: 1;">+ Schedule Visit</button>
                            <button type="button" class="button" onclick="document.getElementById('admin-schedule-modal').style.display='none';">Cancel</button>
                        </div>
                        <div id="admin-schedule-feedback" style="margin-top: 15px;"></div>
                    </form>
                </div>
            </div>
            
            <?php elseif ($active_tab == 'sm_leads' && isset($_GET['sm_id'])) : ?>
            <!-- SM LEADS DETAIL VIEW -->
            <?php
            $sm_id = intval($_GET['sm_id']);
            $sm_user = get_userdata($sm_id);
            
            if ($sm_user && in_array('sales_manager', (array) $sm_user->roles)) {
                // Get all leads for this SM
                $sm_leads = get_posts([
                    'post_type' => 'solar_lead',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'meta_query' => [
                        ['key' => '_created_by_sales_manager', 'value' => $sm_id]
                    ],
                    'orderby' => 'date',
                    'order' => 'DESC',
                ]);
            ?>
            <div class="leaderboard-container" style="grid-column: span 2;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2>📋 Leads by <?php echo esc_html($sm_user->display_name); ?></h2>
                    <a href="?page=team-analysis&team_tab=sales_managers" class="button">← Back to Sales Managers</a>
                </div>
                
                <!-- Lead Stats -->
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 20px;">
                    <?php
                    $sm_lead_stats = ['new' => 0, 'contacted' => 0, 'interested' => 0, 'converted' => 0, 'lost' => 0];
                    foreach ($sm_leads as $lead) {
                        $status = get_post_meta($lead->ID, '_lead_status', true) ?: 'new';
                        if (isset($sm_lead_stats[$status])) {
                            $sm_lead_stats[$status]++;
                        }
                    }
                    $status_colors = [
                        'new' => '#e3f2fd',
                        'contacted' => '#fff3e0',
                        'interested' => '#e8f5e9',
                        'converted' => '#c8e6c9',
                        'lost' => '#ffebee',
                    ];
                    foreach ($sm_lead_stats as $status => $count) : ?>
                    <div style="background: <?php echo $status_colors[$status]; ?>; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 700;"><?php echo $count; ?></div>
                        <div style="color: #666; text-transform: capitalize;"><?php echo $status; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Lead Name</th>
                            <th>Phone</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Source</th>
                            <th>Created</th>
                            <th>Follow-ups</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($sm_leads)) {
                            foreach ($sm_leads as $lead) {
                                $lead_id = $lead->ID;
                                $lead_status = get_post_meta($lead_id, '_lead_status', true) ?: 'new';
                                $lead_type = get_post_meta($lead_id, '_lead_type', true) ?: 'solar_project';
                                $lead_source = get_post_meta($lead_id, '_lead_source', true) ?: '-';
                                
                                // Get follow-ups
                                $lead_followups = $wpdb->get_results($wpdb->prepare(
                                    "SELECT * FROM {$table_followups} WHERE lead_id = %d ORDER BY activity_date DESC",
                                    $lead_id
                                ));
                                ?>
                                <tr class="lead-row" data-lead-id="<?php echo $lead_id; ?>">
                                    <td>
                                        <strong><?php echo esc_html($lead->post_title); ?></strong>
                                        <br><small><?php echo esc_html(get_post_meta($lead_id, '_lead_email', true)); ?></small>
                                    </td>
                                    <td><?php echo esc_html(get_post_meta($lead_id, '_lead_phone', true)); ?></td>
                                    <td>
                                        <span style="background: <?php echo $lead_type === 'cleaning_service' ? '#e8f5e9' : '#e3f2fd'; ?>; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                            <?php echo $lead_type === 'cleaning_service' ? '🧹 Cleaning' : '☀️ Solar'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="background: <?php echo $status_colors[$lead_status] ?? '#eee'; ?>; padding: 3px 8px; border-radius: 3px; text-transform: capitalize;">
                                            <?php echo $lead_status; ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($lead_source); ?></td>
                                    <td><?php echo get_the_date('M j, Y', $lead_id); ?></td>
                                    <td>
                                        <span style="background: #e3f2fd; padding: 3px 8px; border-radius: 3px;">
                                            <?php echo count($lead_followups); ?>
                                        </span>
                                        <?php if (count($lead_followups) > 0) : ?>
                                        <button class="button button-small toggle-followups-btn" data-lead-id="<?php echo $lead_id; ?>" style="margin-left: 5px;">Show</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if (!empty($lead_followups)) : ?>
                                <tr class="followups-detail-row" data-lead-id="<?php echo $lead_id; ?>" style="display: none; background: #fafafa;">
                                    <td colspan="7">
                                        <div style="padding: 10px; border-left: 3px solid #2196f3;">
                                            <strong>📞 Follow-up History:</strong>
                                            <div style="margin-top: 10px;">
                                                <?php foreach ($lead_followups as $followup) : 
                                                    $activity_icons = [
                                                        'phone_call' => '📞',
                                                        'whatsapp' => '💬',
                                                        'email' => '📧',
                                                        'meeting_offline' => '🤝',
                                                        'meeting_online' => '💻',
                                                        'site_visit' => '🏠',
                                                    ];
                                                    $icon = $activity_icons[$followup->activity_type] ?? '📌';
                                                ?>
                                                <div style="background: #fff; padding: 10px; margin-bottom: 8px; border-radius: 4px; border: 1px solid #eee;">
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <span><?php echo $icon; ?> <strong><?php echo ucwords(str_replace('_', ' ', $followup->activity_type)); ?></strong></span>
                                                        <small style="color: #666;"><?php echo date('M j, Y g:i A', strtotime($followup->activity_date)); ?></small>
                                                    </div>
                                                    <div style="margin-top: 5px; color: #555;"><?php echo esc_html($followup->notes); ?></div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="7">No leads found for this Sales Manager.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php } else { ?>
            <div class="leaderboard-container" style="grid-column: span 2;">
                <p>Invalid Sales Manager ID.</p>
                <a href="?page=team-analysis&team_tab=sales_managers" class="button">← Back to Sales Managers</a>
            </div>
            <?php } ?>
            
            <?php elseif ($active_tab == 'manager_leads' && isset($_GET['manager_id'])) : ?>
            <!-- MANAGER LEADS DETAIL VIEW -->
            <?php
            $mgr_id = intval($_GET['manager_id']);
            $mgr_user = get_userdata($mgr_id);
            
            if ($mgr_user && in_array('manager', (array) $mgr_user->roles)) {
                // Get all leads for this Manager
                $mgr_leads = get_posts([
                    'post_type' => 'solar_lead',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'author' => $mgr_id, // Leads created by Manager
                    'orderby' => 'date',
                    'order' => 'DESC',
                ]);
            ?>
            <div class="leaderboard-container" style="grid-column: span 2;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2>📋 Leads by <?php echo esc_html($mgr_user->display_name); ?></h2>
                    <a href="?page=team-analysis&team_tab=managers" class="button">← Back to Managers</a>
                </div>
                
                <!-- Lead Stats -->
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 20px;">
                    <?php
                    $mgr_lead_stats = ['new' => 0, 'contacted' => 0, 'interested' => 0, 'converted' => 0, 'lost' => 0];
                    foreach ($mgr_leads as $lead) {
                        $status = get_post_meta($lead->ID, '_lead_status', true) ?: 'new';
                        if (isset($mgr_lead_stats[$status])) {
                            $mgr_lead_stats[$status]++;
                        }
                    }
                    $status_colors = [
                        'new' => '#e3f2fd',
                        'contacted' => '#fff3e0',
                        'interested' => '#e8f5e9',
                        'converted' => '#c8e6c9',
                        'lost' => '#ffebee',
                    ];
                    foreach ($mgr_lead_stats as $status => $count) : ?>
                    <div style="background: <?php echo $status_colors[$status]; ?>; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 700;"><?php echo $count; ?></div>
                        <div style="color: #666; text-transform: capitalize;"><?php echo $status; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Lead Name</th>
                            <th>Phone</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Source</th>
                            <th>Created</th>
                            <th>Follow-ups</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($mgr_leads)) {
                            foreach ($mgr_leads as $lead) {
                                $lead_id = $lead->ID;
                                $lead_status = get_post_meta($lead_id, '_lead_status', true) ?: 'new';
                                $lead_type = get_post_meta($lead_id, '_lead_type', true) ?: 'solar_project';
                                $lead_source = get_post_meta($lead_id, '_lead_source', true) ?: '-';
                                
                                // Get follow-ups
                                $lead_followups = $wpdb->get_results($wpdb->prepare(
                                    "SELECT * FROM {$table_followups} WHERE lead_id = %d ORDER BY activity_date DESC",
                                    $lead_id
                                ));
                                ?>
                                <tr class="lead-row" data-lead-id="<?php echo $lead_id; ?>">
                                    <td>
                                        <strong><?php echo esc_html($lead->post_title); ?></strong>
                                        <br><small><?php echo esc_html(get_post_meta($lead_id, '_lead_email', true)); ?></small>
                                    </td>
                                    <td><?php echo esc_html(get_post_meta($lead_id, '_lead_phone', true)); ?></td>
                                    <td>
                                        <span style="background: <?php echo $lead_type === 'cleaning_service' ? '#e8f5e9' : '#e3f2fd'; ?>; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                            <?php echo $lead_type === 'cleaning_service' ? '🧹 Cleaning' : '☀️ Solar'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="background: <?php echo $status_colors[$lead_status] ?? '#eee'; ?>; padding: 3px 8px; border-radius: 3px; text-transform: capitalize;">
                                            <?php echo $lead_status; ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($lead_source); ?></td>
                                    <td><?php echo get_the_date('M j, Y', $lead_id); ?></td>
                                    <td>
                                        <span style="background: #e3f2fd; padding: 3px 8px; border-radius: 3px;">
                                            <?php echo count($lead_followups); ?>
                                        </span>
                                        <?php if (count($lead_followups) > 0) : ?>
                                        <button class="button button-small toggle-followups-btn" data-lead-id="<?php echo $lead_id; ?>" style="margin-left: 5px;">Show</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if (!empty($lead_followups)) : ?>
                                <tr class="followups-detail-row" data-lead-id="<?php echo $lead_id; ?>" style="display: none; background: #fafafa;">
                                    <td colspan="7">
                                        <div style="padding: 10px; border-left: 3px solid #2196f3;">
                                            <strong>📞 Follow-up History:</strong>
                                            <div style="margin-top: 10px;">
                                                <?php foreach ($lead_followups as $followup) : 
                                                    $activity_icons = [
                                                        'phone_call' => '📞',
                                                        'whatsapp' => '💬',
                                                        'email' => '📧',
                                                        'meeting_offline' => '🤝',
                                                        'meeting_online' => '💻',
                                                        'site_visit' => '🏠',
                                                    ];
                                                    $icon = $activity_icons[$followup->activity_type] ?? '📌';
                                                ?>
                                                <div style="background: #fff; padding: 10px; margin-bottom: 8px; border-radius: 4px; border: 1px solid #eee;">
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <span><?php echo $icon; ?> <strong><?php echo ucwords(str_replace('_', ' ', $followup->activity_type)); ?></strong></span>
                                                        <small style="color: #666;"><?php echo date('M j, Y g:i A', strtotime($followup->activity_date)); ?></small>
                                                    </div>
                                                    <div style="margin-top: 5px; color: #555;"><?php echo esc_html($followup->notes); ?></div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="7">No leads found for this Manager.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php } else { ?>
            <div class="leaderboard-container" style="grid-column: span 2;">
                <p>Invalid Manager ID.</p>
                <a href="?page=team-analysis&team_tab=managers" class="button">← Back to Managers</a>
            </div>
            <?php } ?>
            
            <?php else : ?>
            <!-- AREA MANAGERS TAB -->
            <div class="leaderboard-container">
                <h2>Area Manager Leaderboard</h2>
                <table class="wp-list-table widefat fixed striped users">
                    <thead>
                        <tr>
                            <th scope="col">Manager</th>
                            <th scope="col">Sales Team</th>
                            <th scope="col">Location</th>
                            <th scope="col">Team Leads</th>
                            <th scope="col">Projects</th>
                            <th scope="col">Profit</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($am_data)) {
                            usort($am_data, fn($a, $b) => $b['company_profit'] <=> $a['company_profit']);
                            foreach ($am_data as $data) {
                                ?>
                                <tr>
                                    <td><strong><a href="?page=team-analysis&manager_id=<?php echo $data['id']; ?>"><?php echo esc_html($data['name']); ?></a></strong></td>
                                    <td>
                                        <?php 
                                        if (!empty($data['sales_team'])) {
                                            echo esc_html(implode(', ', $data['sales_team']));
                                        } else {
                                            echo '<span style="color:#999;">No team assigned</span>';
                                        }
                                        ?>
                                        <br><button class="button button-small assign-team-btn" data-manager-id="<?php echo $data['id']; ?>" data-current-team='<?php echo json_encode($data['team_ids'] ?: []); ?>'>Assign Team</button>
                                    </td>
                                    <td>
                                        <?php
                                        if ($data['assigned_state'] && $data['assigned_city']) {
                                            echo esc_html($data['assigned_city'] . ', ' . $data['assigned_state']);
                                        } else {
                                            echo '<span style="color:#999;">Not assigned</span>';
                                        }
                                        ?>
                                        <br><button class="button button-small change-location-btn" data-manager-id="<?php echo $data['id']; ?>">Change</button>
                                    </td>
                                    <td><?php echo $data['total_leads']; ?></td>
                                    <td><?php echo $data['total_projects']; ?></td>
                                    <td>₹<?php echo number_format($data['company_profit'], 0); ?></td>
                                    <td><a href="?page=team-analysis&manager_id=<?php echo $data['id']; ?>" class="button">View</a></td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="7">No Area Managers found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="charts-container">
                <h2>Visual Overview</h2>
                <canvas id="manager-performance-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Location Modal -->
    <div id="location-modal" style="display:none;">
        <div id="location-modal-content">
            <h2>Assign Location</h2>
            <input type="hidden" id="manager-id-input">
            <p>
                <label for="state-select">State</label>
                <select id="state-select" style="width: 100%;"></select>
            </p>
            <p>
                <label for="city-select">City</label>
                <select id="city-select" style="width: 100%;"></select>
            </p>
            <p>
                <button class="button button-primary" id="save-location-btn">Save</button>
                <button class="button" id="cancel-location-btn">Cancel</button>
            </p>
        </div>
    </div>

    <!-- Team Assignment Modal -->
    <div id="team-modal" style="display:none;">
        <div id="team-modal-content">
            <h2>👥 Assign Sales Team</h2>
            <p>Select Sales Managers to assign to this Area Manager:</p>
            <input type="hidden" id="team-manager-id">
            <div id="team-checkboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 15px;">
                <?php foreach ($sales_managers as $sm) : ?>
                <label style="display: block; padding: 5px 0;">
                    <input type="checkbox" name="team[]" value="<?php echo $sm->ID; ?>">
                    <?php echo esc_html($sm->display_name); ?>
                </label>
                <?php endforeach; ?>
                <?php if (empty($sales_managers)) : ?>
                <p style="color: #999;">No Sales Managers available. <a href="<?php echo admin_url('user-new.php'); ?>">Create one</a></p>
                <?php endif; ?>
            </div>
            <p>
                <button class="button button-primary" id="save-team-btn">Save Team</button>
                <button class="button" id="cancel-team-btn">Cancel</button>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Chart logic can remain similar, just reflects AM stats
            const ctx = document.getElementById('manager-performance-chart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Total Projects',
                        data: <?php echo json_encode($chart_projects); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    }, {
                        label: 'Company Profit (₹)',
                        data: <?php echo json_encode($chart_profit); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    }]
                },
            });
        });
    </script>
    <script>
        jQuery(document).ready(function($) {
            // --- Location Modal ---
            let statesAndCities = [];
            $.getJSON('<?php echo plugin_dir_url( __FILE__ ) . '../../assets/data/indian-states-cities.json'; ?>', function(data) {
                statesAndCities = data.states;
            });

            $('.change-location-btn').on('click', function() {
                const managerId = $(this).data('manager-id');
                $('#manager-id-input').val(managerId);
                const stateSelect = $('#state-select');
                stateSelect.empty().append('<option value="">Select State</option>');
                statesAndCities.forEach(state => {
                    stateSelect.append(`<option value="${state.state}">${state.state}</option>`);
                });
                $('#location-modal').show();
            });

            $('#state-select').on('change', function() {
                const selectedState = $(this).val();
                const citySelect = $('#city-select');
                citySelect.empty().append('<option value="">Select City</option>');
                if (selectedState) {
                    const stateData = statesAndCities.find(state => state.state === selectedState);
                    if (stateData) {
                        stateData.districts.forEach(city => {
                            citySelect.append(`<option value="${city}">${city}</option>`);
                        });
                    }
                }
            });

            $('#save-location-btn').on('click', function() {
                const managerId = $('#manager-id-input').val();
                const state = $('#state-select').val();
                const city = $('#city-select').val();
                if (!managerId || !state || !city) {
                    alert('Please select a state and city.');
                    return;
                }
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'assign_area_manager_location',
                        manager_id: managerId,
                        state: state,
                        city: city,
                        nonce: '<?php echo wp_create_nonce("assign_location_nonce"); ?>',
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    }
                });
            });

            $('#cancel-location-btn').on('click', function() {
                $('#location-modal').hide();
            });

            // --- Team Assignment Modal (Replaced Supervisor Modal) ---
            $('.assign-team-btn').on('click', function() {
                const managerId = $(this).data('manager-id');
                const currentTeam = $(this).data('current-team') || [];
                
                $('#team-manager-id').val(managerId);
                
                // Reset and check current team
                $('#team-checkboxes input[type="checkbox"]').prop('checked', false);
                currentTeam.forEach(smId => {
                    $('#team-checkboxes input[value="' + smId + '"]').prop('checked', true);
                });
                
                $('#team-modal').show();
            });

            $('#save-team-btn').on('click', function() {
                const managerId = $('#team-manager-id').val();
                const selectedTeam = [];
                $('#team-checkboxes input[type="checkbox"]:checked').each(function() {
                    selectedTeam.push($(this).val());
                });

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'assign_team_to_area_manager',
                        manager_id: managerId,
                        team_ids: selectedTeam,
                        nonce: '<?php echo wp_create_nonce("assign_team_nonce"); ?>',
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ ' + response.data.message);
                            location.reload();
                        } else {
                            alert('❌ Error: ' + response.data.message);
                        }
                    }
                });
            });

            $('#cancel-team-btn').on('click', function() {
                $('#team-modal').hide();
            });
            
            // --- Toggle Cleaners in Cleaning Services Tab ---
            $('.toggle-cleaners-btn').on('click', function() {
                const amId = $(this).data('am-id');
                const detailRow = $('.cleaners-detail-row[data-am-id="' + amId + '"]');
                if (detailRow.is(':visible')) {
                    detailRow.hide();
                    $(this).text('Show');
                } else {
                    detailRow.show();
                    $(this).text('Hide');
                }
            });
            
            // --- Toggle Follow-ups in SM Leads View ---
            $('.toggle-followups-btn').on('click', function() {
                const leadId = $(this).data('lead-id');
                const detailRow = $('.followups-detail-row[data-lead-id="' + leadId + '"]');
                if (detailRow.is(':visible')) {
                    detailRow.hide();
                    $(this).text('Show');
                } else {
                    detailRow.show();
                    $(this).text('Hide');
                }
            });

            // --- Admin View Cleaner Profile ---
            $(document).on('click', '.admin-view-cleaner-btn', function(e) {
                e.preventDefault();
                const cleanerId = $(this).data('id');
                // Could pass full object via data attribute or fetch dynamically.
                // Since lists are small, we can fetch via AJAX or just passed updated data. 
                // Let's assume we have cleaner data accessible or fetch it.
                // A better approach for specific details (images) is to fetch fresh data.
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'get_cleaners' }, // This returns ALL cleaners (filtered by role), could be optimized but okay for now.
                    success: function(response) {
                        if (response.success) {
                            const cleaner = response.data.find(c => c.id == cleanerId);
                            if (cleaner) {
                                $('#admin-cleaner-name').text(cleaner.name);
                                $('#admin-cleaner-phone').text(cleaner.phone);
                                $('#admin-cleaner-email').text(cleaner.email);
                                $('#admin-cleaner-address').text(cleaner.address);
                                $('#admin-cleaner-aadhaar').text(cleaner.aadhaar);
                                $('#admin-cleaner-created').text(cleaner.created_at);
                                
                                if (cleaner.photo_url) {
                                    $('#admin-cleaner-photo').attr('src', cleaner.photo_url);
                                } else {
                                    $('#admin-cleaner-photo').attr('src', '<?php echo plugin_dir_url(__FILE__) . "../../assets/images/default-avatar.png"; ?>'); 
                                }
                                
                                if (cleaner.aadhaar_image_url) {
                                    $('#admin-cleaner-aadhaar-img').attr('src', cleaner.aadhaar_image_url);
                                } else {
                                    $('#admin-cleaner-aadhaar-img').attr('src', '');
                                }
                                
                                $('#admin-cleaner-profile-modal').css('display', 'flex');
                                $('#admin-edit-cleaner-btn').data('cleaner-id', cleanerId); // Store ID for edit button
                            }
                        }
                    }
                });
            });

            // --- Admin Edit Cleaner Redirect ---
            $(document).on('click', '#admin-edit-cleaner-btn', function() {
                const cleanerId = $(this).data('cleaner-id');
                if (cleanerId) {
                    window.location.href = 'user-edit.php?user_id=' + cleanerId;
                }
            });
            
            // --- Admin Schedule Visit Handlers ---
            let adminCleanersList = [];
            
            // Load all cleaners for admin
            function loadAdminCleaners() {
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'get_cleaners' },
                    success: function(response) {
                        if (response.success) {
                            adminCleanersList = response.data;
                        }
                    }
                });
            }
            
            // Load cleaners on page load if on cleaning services tab
            if (window.location.search.includes('cleaning_services')) {
                loadAdminCleaners();
            }
            
            // Open admin schedule modal
            $(document).on('click', '.admin-schedule-btn', function(e) {
                e.preventDefault();
                const serviceId = $(this).data('id');
                const customerName = $(this).data('name');
                
                $('#admin_schedule_service_id').val(serviceId);
                $('#admin_schedule_customer_name').text(customerName);
                
                // Set min date to today
                const today = new Date().toISOString().split('T')[0];
                $('#admin_schedule_date').attr('min', today).val(today);
                
                // Populate cleaners dropdown
                const select = $('#admin_schedule_cleaner_id');
                select.empty().append('<option value="">Select Cleaner</option>');
                adminCleanersList.forEach(cleaner => {
                    select.append(`<option value="${cleaner.id}">${cleaner.name} (📞 ${cleaner.phone})</option>`);
                });
                
                $('#admin-schedule-feedback').html('');
                $('#admin-schedule-modal').css('display', 'flex');
            });
            
            // Submit admin schedule form
            $('#admin-schedule-form').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const feedback = $('#admin-schedule-feedback');
                const submitBtn = form.find('button[type="submit"]');
                
                const serviceId = $('#admin_schedule_service_id').val();
                const cleanerId = $('#admin_schedule_cleaner_id').val();
                const scheduledDate = $('#admin_schedule_date').val();
                const scheduledTime = $('#admin_schedule_time').val();
                
                if (!cleanerId) {
                    feedback.html('<div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;">Please select a cleaner</div>');
                    return;
                }
                
                submitBtn.prop('disabled', true).text('Scheduling...');
                feedback.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'schedule_cleaning_visit',
                        service_id: serviceId,
                        cleaner_id: cleanerId,
                        scheduled_date: scheduledDate,
                        scheduled_time: scheduledTime
                    },
                    success: function(response) {
                        if (response.success) {
                            feedback.html('<div style="background:#d4edda;color:#155724;padding:10px;border-radius:6px;">✅ ' + response.data.message + '</div>');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            feedback.html('<div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;">❌ ' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        feedback.html('<div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;">❌ Error scheduling visit</div>');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).text('+ Schedule Visit');
                    }
                });
            });
        });
    </script>
    <style>
        .analysis-dashboard { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px; }
        @media (max-width: 782px) { .analysis-dashboard { grid-template-columns: 1fr; } }
        #location-modal, #team-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        #location-modal-content, #team-modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
        }
        .nav-tab-wrapper { margin-bottom: 0; }
    </style>
    <?php
}

function sp_render_single_manager_view($manager_id) {
    $manager = get_userdata($manager_id);
    if (!$manager || (!in_array('area_manager', (array)$manager->roles) && !in_array('manager', (array)$manager->roles))) {
        echo '<div class="wrap"><h1>Invalid Manager</h1><p>The specified user is not a Manager or Area Manager.</p></div>';
        return;
    }

    $args = [
        'post_type' => 'solar_project',
        'author' => $manager_id,
        'posts_per_page' => -1,
        'post_status' => ['publish', 'completed', 'assigned', 'in_progress', 'pending'],
    ];
    $projects = get_posts($args);

    $stats = ['completed' => 0, 'in_progress' => 0, 'pending' => 0, 'total' => count($projects)];
    $clients = [];
    $vendors = [];
    
    // Get Assigned Area Managers (if viewing a Manager)
    $assigned_ams = [];
    if (in_array('manager', (array)$manager->roles)) {
        $assigned_ams = get_users([
            'role' => 'area_manager',
            'meta_key' => '_supervised_by_manager',
            'meta_value' => $manager_id,
        ]);
    }

    foreach ($projects as $project) {
        $status = get_post_meta($project->ID, 'project_status', true) ?: 'pending';
        if (isset($stats[$status])) {
            $stats[$status]++;
        }

        $client_id = get_post_meta($project->ID, '_client_user_id', true);
        if ($client_id && !isset($clients[$client_id])) {
            $clients[$client_id] = get_userdata($client_id);
        }

        $vendor_id = get_post_meta($project->ID, '_assigned_vendor_id', true);
        if ($vendor_id && !isset($vendors[$vendor_id])) {
            $vendors[$vendor_id] = get_userdata($vendor_id);
        }
    }
    
    // Fetch assigned Area Managers for this Manager
    // MUST match main table logic (lines 63-81)
    $assigned_ams = [];
    if (in_array('manager', (array)$manager->roles)) {
        $manager_assigned_states = get_user_meta($manager_id, '_assigned_states', true);
        
        if (empty($manager_assigned_states)) {
            // No states = Global access to ALL AMs
            $assigned_ams = get_users([
                'role' => 'area_manager',
                'fields' => 'all'
            ]);
        } else {
            // Has states = Only AMs supervised by this manager
            $assigned_ams = get_users([
                'role' => 'area_manager',
                'meta_key' => '_supervised_by_manager',
                'meta_value' => $manager_id,
                'fields' => 'all'
            ]);
        }
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Manager Analysis: <?php echo esc_html($manager->display_name); ?></h1>
        <a href="?page=team-analysis" class="page-title-action">← Back to Leaderboard</a>
        
        <div class="manager-details-grid">
            <div class="detail-card">
                <h3>Project Stats</h3>
                <p><strong>Total:</strong> <?php echo $stats['total']; ?></p>
                <p><strong>Completed:</strong> <?php echo $stats['completed']; ?></p>
                <p><strong>In Progress:</strong> <?php echo $stats['in_progress']; ?></p>
                <p><strong>Pending:</strong> <?php echo $stats['pending']; ?></p>
            </div>
            <div class="detail-card">
                <h3>Actions</h3>
                <button class="button button-primary manager-report-btn" data-action="generate" data-manager-id="<?php echo $manager_id; ?>">📄 Generate Report</button>
                <button class="button manager-report-btn" data-action="email" data-manager-id="<?php echo $manager_id; ?>">📧 Share via Email</button>
                <button class="button manager-report-btn" data-action="whatsapp" data-manager-id="<?php echo $manager_id; ?>">📱 Share via WhatsApp</button>
            </div>
            <div class="detail-card wide">
                <h3>Associated Clients</h3>
                <ul>
                    <?php foreach($clients as $client) { if($client) echo '<li><a href="' . admin_url('user-edit.php?user_id=' . $client->ID) . '">' . esc_html($client->display_name) . '</a> (' . esc_html($client->user_email) . ')</li>'; } ?>
                </ul>
            </div>
            
            <?php if (!empty($assigned_ams)) : ?>
            <div class="detail-card wide">
                <h3>Assigned Area Managers</h3>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr><th>Name</th><th>Location</th><th>Cleaners</th><th>Revenue</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($assigned_ams as $am) : 
                            $am_revenue = 0; // Ideally fetch via helper or query, for now just show basic info
                            $am_cleaners = get_users(['role' => 'solar_cleaner', 'meta_key' => '_supervised_by_area_manager', 'meta_value' => $am->ID, 'fields' => 'ID']);
                            $am_services = get_posts(['post_type' => 'cleaning_service', 'meta_key' => '_assigned_area_manager', 'meta_value' => $am->ID]);
                            foreach($am_services as $svc) {
                                if (get_post_meta($svc->ID, '_payment_status', true) === 'paid') {
                                    $am_revenue += floatval(get_post_meta($svc->ID, '_total_amount', true));
                                }
                            }
                        ?>
                            <tr>
                                <td><strong><a href="?page=team-analysis&manager_id=<?php echo $am->ID; ?>"><?php echo esc_html($am->display_name); ?></a></strong></td>
                                <td><?php echo esc_html(get_user_meta($am->ID, 'city', true) . ', ' . get_user_meta($am->ID, 'state', true)); ?></td>
                                <td><?php echo count($am_cleaners); ?></td>
                                <td>₹<?php echo number_format($am_revenue); ?></td>
                                <td><a href="user-edit.php?user_id=<?php echo $am->ID; ?>" class="button button-small">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Regions Section -->
            <div class="detail-card wide">
                <h3>📍 Regions Covered</h3>
                <?php
                // DEBUG: Show what we're working with
                echo '<pre style="background:#f0f0f0; padding:10px; margin:10px 0; font-size:11px;">';
                echo '<strong>DEBUG INFO:</strong><br>';
                echo 'Manager ID: ' . $manager_id . '<br>';
                echo 'Assigned AMs count: ' . count($assigned_ams) . '<br>';
                if (!empty($assigned_ams)) {
                    echo 'AMs found:<br>';
                    foreach ($assigned_ams as $am) {
                        echo '  - ' . $am->display_name . ' (ID: ' . $am->ID . ')<br>';
                        $state = get_user_meta($am->ID, 'state', true);
                        $city = get_user_meta($am->ID, 'city', true);
                        echo '    State: ' . ($state ?: 'EMPTY') . ', City: ' . ($city ?: 'EMPTY') . '<br>';
                    }
                } else {
                    echo 'No AMs found!<br>';
                    echo 'Checking meta key "_supervised_by_manager" with value: ' . $manager_id . '<br>';
                }
                echo '</pre>';
                
                // Get regions from assigned AMs
                $regions = [];
                if (!empty($assigned_ams)) {
                    foreach ($assigned_ams as $am) {
                        $state = get_user_meta($am->ID, 'state', true);
                        $city = get_user_meta($am->ID, 'city', true);
                        if ($city && $state) {
                            $regions[] = $city . ', ' . $state;
                        } elseif ($state) {
                            $regions[] = $state;
                        }
                    }
                    $regions = array_unique($regions);
                }
                
                if (!empty($regions)) {
                    echo '<div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;">';
                    foreach ($regions as $region) {
                        echo '<span style="background: #e3f2fd; color: #1565c0; padding: 5px 12px; border-radius: 15px; font-size: 13px;">' . esc_html($region) . '</span>';
                    }
                    echo '</div>';
                    echo '<p style="margin-top: 15px; color: #666;"><small><strong>Total Regions:</strong> ' . count($regions) . '</small></p>';
                } else {
                    echo '<p style="color: #999;">No regions assigned yet.</p>';
                }
                ?>
            </div>

            <?php
            // Fetch Sales Managers (Aggregate from AMs)
            $all_sms = [];
            if (!empty($assigned_ams)) {
                $am_ids = wp_list_pluck($assigned_ams, 'ID');
                foreach ($am_ids as $aid) {
                    $sms = get_users([
                        'role' => 'sales_manager',
                        'meta_key' => '_assigned_area_manager',
                        'meta_value' => $aid
                    ]);
                    $all_sms = array_merge($all_sms, $sms);
                }
            }
            ?>
            <?php if (!empty($all_sms)) : ?>
            <div class="detail-card wide">
                <h3>Assigned Sales Managers</h3>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Phone</th><th>Area Manager</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_sms as $sm) : 
                             $am_id = get_user_meta($sm->ID, '_assigned_area_manager', true);
                             $am_name = $am_id ? get_userdata($am_id)->display_name : 'Unassigned';
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($sm->display_name); ?></strong></td>
                                <td><?php echo esc_html($sm->user_email); ?></td>
                                <td><?php echo esc_html(get_user_meta($sm->ID, 'phone_number', true)); ?></td>
                                <td><?php echo esc_html($am_name); ?></td>
                                <td><a href="user-edit.php?user_id=<?php echo $sm->ID; ?>" class="button button-small">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php
            // Fetch Cleaners (Aggregate from AMs)
            $all_cleaners = [];
            if (!empty($assigned_ams)) {
                 foreach ($assigned_ams as $am) {
                     $cleaners = get_users([
                        'role' => 'solar_cleaner',
                        'meta_key' => '_supervised_by_area_manager',
                        'meta_value' => $am->ID
                     ]);
                     $all_cleaners = array_merge($all_cleaners, $cleaners);
                 }
            }
            ?>
             <?php if (!empty($all_cleaners)) : ?>
            <div class="detail-card wide">
                <h3>Assigned Cleaners</h3>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr><th>Name</th><th>Phone</th><th>Area Manager</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_cleaners as $cl) : 
                             $am_id = get_user_meta($cl->ID, '_supervised_by_area_manager', true);
                             $am_name = $am_id ? get_userdata($am_id)->display_name : 'Unassigned';
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($cl->display_name); ?></strong></td>
                                <td><?php echo esc_html(get_user_meta($cl->ID, 'phone', true)); ?></td>
                                <td><?php echo esc_html($am_name); ?></td>
                                <td><a href="user-edit.php?user_id=<?php echo $cl->ID; ?>" class="button button-small">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <div class="detail-card wide">
                <h3>Associated Vendors</h3>
                <ul>
                    <?php foreach($vendors as $vendor) { if($vendor) echo '<li><a href="' . admin_url('user-edit.php?user_id=' . $vendor->ID) . '">' . esc_html($vendor->display_name) . '</a> (' . esc_html($vendor->user_email) . ')</li>'; } ?>
                </ul>
            </div>
            
            <!-- Cleaning Services Section -->
            <div class="detail-card wide" id="cleaning">
                <h3>🧹 Cleaning Services</h3>
                <?php
                // Get cleaners: If Manager, get all from assigned AMs. If AM, get directly supervised.
                $cleaners = [];
                $cleaner_ids = [];
                
                if (in_array('manager', (array)$manager->roles)) {
                    // Fetch for all assigned AMs
                    if (!empty($assigned_ams)) {
                        $am_ids = wp_list_pluck($assigned_ams, 'ID');
                        // Loop through AMs to find cleaners? Or simple meta query?
                        // Meta query 'IN' doesn't work for single key-value standard loop unless we do multiple ORs or custom query.
                        // Loop is safer for now.
                         foreach ($am_ids as $aid) {
                             $sub_cleaners = get_users([
                                'role' => 'solar_cleaner',
                                'meta_key' => '_supervised_by_area_manager',
                                'meta_value' => $aid,
                            ]);
                            $cleaners = array_merge($cleaners, $sub_cleaners);
                         }
                    }
                } else {
                    // Area Manager
                    $cleaners = get_users([
                        'role' => 'solar_cleaner',
                        'meta_key' => '_supervised_by_area_manager',
                        'meta_value' => $manager_id,
                    ]);
                }
                
                foreach($cleaners as $c) $cleaner_ids[] = $c->ID;
                
                // Get cleaning services
                $cleaning_services = [];
                
                if (in_array('manager', (array)$manager->roles)) {
                    if (!empty($assigned_ams)) {
                         $am_ids = wp_list_pluck($assigned_ams, 'ID');
                         $cleaning_services = get_posts([
                            'post_type' => 'cleaning_service',
                            'posts_per_page' => -1,
                            'meta_query' => [
                                ['key' => '_assigned_area_manager', 'value' => $am_ids, 'compare' => 'IN'],
                            ]
                        ]);
                    }
                } else {
                    $cleaning_services = get_posts([
                        'post_type' => 'cleaning_service',
                        'posts_per_page' => -1,
                        'meta_query' => [
                            ['key' => '_assigned_area_manager', 'value' => $manager_id],
                        ]
                    ]);
                }
                
                $total_cleaning_revenue = 0;
                foreach ($cleaning_services as $service) {
                    if (get_post_meta($service->ID, '_payment_status', true) === 'paid') {
                        $total_cleaning_revenue += floatval(get_post_meta($service->ID, '_total_amount', true));
                    }
                }
                ?>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 700;"><?php echo count($cleaners); ?></div>
                        <div style="color: #666;">Cleaners</div>
                    </div>
                    <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 700;"><?php echo count($cleaning_services); ?></div>
                        <div style="color: #666;">Services</div>
                    </div>
                    <div style="background: #fce4ec; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: 700;">₹<?php echo number_format($total_cleaning_revenue); ?></div>
                        <div style="color: #666;">Revenue</div>
                    </div>
                </div>
                
                <?php if (!empty($cleaners)) : ?>
                <h4>Cleaners</h4>
                <table class="wp-list-table widefat striped" style="margin-bottom: 15px;">
                    <thead>
                        <tr><th>Name</th><th>Phone</th><th>Location</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cleaners as $cleaner) : ?>
                        <tr>
                            <td><a href="<?php echo admin_url('user-edit.php?user_id=' . $cleaner->ID); ?>"><?php echo esc_html($cleaner->display_name); ?></a></td>
                            <td><?php echo esc_html(get_user_meta($cleaner->ID, 'phone', true)); ?></td>
                            <td><?php echo esc_html(get_user_meta($cleaner->ID, 'city', true) . ', ' . get_user_meta($cleaner->ID, 'state', true)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <?php if (!empty($cleaning_services)) : ?>
                <h4>Recent Services</h4>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr><th>Customer</th><th>Plan</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($cleaning_services, 0, 10) as $service) : ?>
                        <tr>
                            <td><?php echo esc_html(get_post_meta($service->ID, '_customer_name', true)); ?></td>
                            <td><?php echo ucwords(str_replace('_', ' ', get_post_meta($service->ID, '_plan_type', true))); ?></td>
                            <td>₹<?php echo number_format(get_post_meta($service->ID, '_total_amount', true)); ?></td>
                            <td>
                                <?php $pay_status = get_post_meta($service->ID, '_payment_status', true); ?>
                                <span style="background: <?php echo $pay_status === 'paid' ? '#c8e6c9' : '#fff3e0'; ?>; padding: 3px 8px; border-radius: 3px;">
                                    <?php echo ucfirst($pay_status ?: 'pending'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <style>
        .manager-details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px; }
        .detail-card { background: #fff; padding: 20px; border: 1px solid #ddd; }
        .detail-card.wide { grid-column: span 2; }
    </style>
    <script>
        function handleWhatsAppRedirect(whatsapp_data) {
            if (whatsapp_data && whatsapp_data.phone && whatsapp_data.message) {
                const url = `https://wa.me/${whatsapp_data.phone}?text=${whatsapp_data.message}`;
                window.open(url, '_blank');
            }
        }

        jQuery(document).ready(function($) {
            $('.manager-report-btn').on('click', function() {
                const button = $(this);
                const action = button.data('action');
                const managerId = button.data('manager-id');
                const originalText = button.text();

                if (action === 'generate') {
                    // Generate Report - Download as text summary
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'generate_manager_report',
                            manager_id: managerId,
                            nonce: '<?php echo wp_create_nonce("manager_report_nonce"); ?>'
                        },
                        beforeSend: function() {
                            button.prop('disabled', true).text('⏳ Generating...');
                        },
                        success: function(response) {
                            if (response.success) {
                                // Create downloadable text file
                                const blob = new Blob([response.data.report_text], { type: 'text/plain' });
                                const url = window.URL.createObjectURL(blob);
                                const a = document.createElement('a');
                                a.href = url;
                                a.download = response.data.filename;
                                document.body.appendChild(a);
                                a.click();
                                window.URL.revokeObjectURL(url);
                                document.body.removeChild(a);
                                alert('✅ Report generated successfully!');
                            } else {
                                alert('❌ Error: ' + response.data.message);
                            }
                            button.prop('disabled', false).text(originalText);
                        },
                        error: function() {
                            alert('❌ An error occurred.');
                            button.prop('disabled', false).text(originalText);
                        }
                    });
                } else if (action === 'email') {
                    // Share via Email
                    const email = prompt('Enter email address to send report:');
                    if (!email) return;

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'email_manager_report',
                            manager_id: managerId,
                            email: email,
                            nonce: '<?php echo wp_create_nonce("manager_report_nonce"); ?>'
                        },
                        beforeSend: function() {
                            button.prop('disabled', true).text('⏳ Sending...');
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('✅ ' + response.data.message);
                            } else {
                                alert('❌ Error: ' + response.data.message);
                            }
                            button.prop('disabled', false).text(originalText);
                        },
                        error: function() {
                            alert('❌ An error occurred.');
                            button.prop('disabled', false).text(originalText);
                        }
                    });
                } else if (action === 'whatsapp') {
                    // Share via WhatsApp
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'whatsapp_manager_report',
                            manager_id: managerId,
                            nonce: '<?php echo wp_create_nonce("manager_report_nonce"); ?>'
                        },
                        beforeSend: function() {
                            button.prop('disabled', true).text('⏳ Preparing...');
                        },
                        success: function(response) {
                            if (response.success && response.data.whatsapp_data) {
                                handleWhatsAppRedirect(response.data.whatsapp_data);
                            } else {
                                alert('❌ Error: ' + (response.data.message || 'Manager phone number not found'));
                            }
                            button.prop('disabled', false).text(originalText);
                        },
                        error: function() {
                            alert('❌ An error occurred.');
                            button.prop('disabled', false).text(originalText);
                        }
                    });
                }
            });
        });
    </script>
    <!-- Admin Cleaner Profile Modal -->
    <div id="admin-cleaner-profile-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 10000; justify-content: center; align-items: center; overflow-y: auto;">
        <div class="modal-box" style="background: #fff; border-radius: 12px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; position: relative; animation: slideIn 0.3s ease-out;">
            <button class="close-modal-btn" style="position: absolute; top: 15px; right: 20px; background: none; border: none; font-size: 28px; cursor: pointer; color: #666; z-index: 10;" onclick="document.getElementById('admin-cleaner-profile-modal').style.display='none'">&times;</button>
            
            <!-- Modal Header -->
            <div style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); padding: 30px; color: white; border-radius: 12px 12px 0 0;">
                <div style="display: flex; gap: 20px; align-items: center;">
                    <div style="position: relative;">
                        <img id="admin-cleaner-photo" src="" alt="Cleaner Photo" style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid white; object-fit: cover; background: #eee;">
                    </div>
                    <div>
                        <h2 id="admin-cleaner-name" style="margin: 0; font-size: 24px; color: white;"></h2>
                        <p id="admin-cleaner-meta" style="margin: 5px 0 0; opacity: 0.9; font-size: 14px; color: #ecf0f1;"></p>
                    </div>
                </div>
            </div>

            <!-- Modal Content -->
            <div style="padding: 30px;">
                <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px;">
                    <!-- Left Column: Details -->
                    <div>
                        <h3 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0; font-size: 1.1em;">📋 Personal Details</h3>
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; color: #666; font-size: 12px; font-weight: 600; text-transform: uppercase;">Phone Number</label>
                            <p id="admin-cleaner-phone" style="font-size: 16px; margin: 5px 0 0; font-weight: 500;"></p>
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; color: #666; font-size: 12px; font-weight: 600; text-transform: uppercase;">Email</label>
                            <p id="admin-cleaner-email" style="font-size: 16px; margin: 5px 0 0;"></p>
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; color: #666; font-size: 12px; font-weight: 600; text-transform: uppercase;">Address</label>
                            <p id="admin-cleaner-address" style="font-size: 16px; margin: 5px 0 0; color: #444; line-height: 1.4;"></p>
                        </div>
                        <div>
                            <label style="display: block; color: #666; font-size: 12px; font-weight: 600; text-transform: uppercase;">Aadhaar Number</label>
                            <p id="admin-cleaner-aadhaar" style="font-size: 16px; margin: 5px 0 0; letter-spacing: 1px;"></p>
                        </div>
                    </div>

                    <!-- Right Column: Documents -->
                    <div>
                        <h3 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0; font-size: 1.1em;">🆔 Documents</h3>
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                            <p style="margin: 0 0 10px; font-weight: 600; color: #555;">Aadhaar Card Preview</p>
                            <img id="admin-cleaner-aadhaar-img" src="" alt="Aadhaar Card" style="max-width: 100%; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); cursor: pointer;" onclick="window.open(this.src, '_blank')">
                            <p style="margin-top: 10px; font-size: 12px; color: #888;">Click image to view full size</p>
                        </div>
                    </div>
                </div>

                <!-- Admin Actions -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 12px; color: #888;">
                        Account Created: <span id="admin-cleaner-created"></span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button id="admin-edit-cleaner-btn" class="button button-primary">Edit Profile</button>
                        <button id="admin-delete-cleaner-btn" class="button button-link-delete" style="color: #a00;">Delete Account</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    
<?php
}
