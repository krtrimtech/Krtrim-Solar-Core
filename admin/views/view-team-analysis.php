<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_render_team_analysis_page() {
    $manager_id = isset($_GET['manager_id']) ? intval($_GET['manager_id']) : 0;

    if ($manager_id > 0) {
        sp_render_single_manager_view($manager_id);
    } else {
        sp_render_leaderboard_view();
    }
}

function sp_render_leaderboard_view() {
    $active_tab = isset($_GET['team_tab']) ? sanitize_text_field($_GET['team_tab']) : 'area_managers';
    
    // Get Sales Managers
    $sales_managers = get_users(['role' => 'sales_manager']);
    $sm_data = [];
    
    foreach ($sales_managers as $sm) {
        $assigned_am_id = get_user_meta($sm->ID, '_assigned_area_manager', true);
        $assigned_am_name = 'Unassigned';
        
        if ($assigned_am_id) {
            $am_user = get_userdata($assigned_am_id);
            if ($am_user) $assigned_am_name = $am_user->display_name;
        }

        $total_leads = count(get_posts(['post_type' => 'solar_lead', 'author' => $sm->ID, 'posts_per_page' => -1]));
        // Sales Managers don't own projects directly usually, but if they created leads that converted...
        // For simple stats, let's show leads created.
        
        $sm_data[] = [
            'id' => $sm->ID,
            'name' => $sm->display_name,
            'email' => $sm->user_email,
            'phone' => get_user_meta($sm->ID, 'phone_number', true),
            'assigned_am_name' => $assigned_am_name,
            'total_leads' => $total_leads,
        ];
    }
    
    // Get Area Managers
    $area_managers = get_users(['role' => 'area_manager']);
    $am_data = [];
    $chart_labels = [];
    $chart_projects = [];
    $chart_profit = [];

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
        // For now, project ownership remains with AM, but SMs generate leads.
        // We track "Total Team Projects" as AM projects (since AM converts leads to projects).
        
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
        // AM's leads
        $team_leads += count(get_posts(['post_type' => 'solar_lead', 'author' => $manager->ID, 'posts_per_page' => -1]));
        // SMs' leads
        if (!empty($team_ids)) {
            $team_leads += count(get_posts(['post_type' => 'solar_lead', 'author__in' => $team_ids, 'posts_per_page' => -1]));
        }

        $am_data[] = [
            'id' => $manager->ID,
            'name' => $manager->display_name,
            'total_projects' => $total_projects, // Projects are owned by AM
            'total_leads' => $team_leads, // Leads are cumulative
            'company_profit' => $company_profit,
            'assigned_state' => get_user_meta($manager->ID, 'state', true),
            'assigned_city' => get_user_meta($manager->ID, 'city', true),
            'sales_team' => $team_names,
            'team_ids' => $team_ids,
        ];

        $chart_labels[] = $manager->display_name;
        $chart_projects[] = $total_projects;
        $chart_profit[] = $company_profit;
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Team Analysis</h1>
        <p>Monitor and analyze the performance of your Sales Managers and Area Managers.</p>
        
        <!-- Tabs -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=team-analysis&team_tab=area_managers" class="nav-tab <?php echo $active_tab == 'area_managers' ? 'nav-tab-active' : ''; ?>">👔 Area Managers</a>
            <a href="?page=team-analysis&team_tab=sales_managers" class="nav-tab <?php echo $active_tab == 'sales_managers' ? 'nav-tab-active' : ''; ?>">📊 Sales Managers</a>
        </h2>
        
        <div class="analysis-dashboard">
            <?php if ($active_tab == 'sales_managers') : ?>
            <!-- SALES MANAGERS TAB -->
            <div class="leaderboard-container">
                <h2>Sales Manager Leaderboard</h2>
                <table class="wp-list-table widefat fixed striped users">
                    <thead>
                        <tr>
                            <th>Sales Manager</th>
                            <th>Contact</th>
                            <th>Assigned Area Manager</th>
                            <th>Leads Generated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($sm_data)) {
                            foreach ($sm_data as $data) {
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($data['name']); ?></strong></td>
                                    <td>
                                        <?php echo esc_html($data['email']); ?><br>
                                        <small><?php echo esc_html($data['phone'] ?: 'No phone'); ?></small>
                                    </td>
                                    <td><?php echo esc_html($data['assigned_am_name']); ?></td>
                                    <td><?php echo $data['total_leads']; ?></td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="4">No Sales Managers found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
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
    if (!$manager || !in_array('area_manager', (array)$manager->roles)) {
        echo '<div class="wrap"><h1>Invalid Manager</h1><p>The specified user is not an Area Manager.</p></div>';
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
            <div class="detail-card wide">
                <h3>Associated Vendors</h3>
                <ul>
                    <?php foreach($vendors as $vendor) { if($vendor) echo '<li><a href="' . admin_url('user-edit.php?user_id=' . $vendor->ID) . '">' . esc_html($vendor->display_name) . '</a> (' . esc_html($vendor->user_email) . ')</li>'; } ?>
                </ul>
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
    <?php
}
