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
    $area_managers = get_users(['role' => 'area_manager']);
    $manager_data = [];
    $chart_labels = [];
    $chart_projects = [];
    $chart_profit = [];

    foreach ($area_managers as $manager) {
        $args = [
            'post_type' => 'solar_project',
            'author' => $manager->ID,
            'posts_per_page' => -1,
            'post_status' => ['publish', 'completed', 'assigned', 'in_progress'],
        ];
        $projects = get_posts($args);

        $total_projects = count($projects);
        $paid_to_vendors = 0;
        $company_profit = 0;

        foreach ($projects as $project) {
            $paid = get_post_meta($project->ID, '_paid_to_vendor', true) ?: 0;
            $winning_bid = get_post_meta($project->ID, '_winning_bid_amount', true) ?: 0;
            $profit = $winning_bid - $paid;
            $paid_to_vendors += $paid;
            $company_profit += $profit;
        }

        $manager_data[] = [
            'id' => $manager->ID,
            'name' => $manager->display_name,
            'total_projects' => $total_projects,
            'paid_to_vendors' => $paid_to_vendors,
            'company_profit' => $company_profit,
            'assigned_state' => get_user_meta($manager->ID, 'assigned_state', true),
            'assigned_city' => get_user_meta($manager->ID, 'assigned_city', true),
        ];

        $chart_labels[] = $manager->display_name;
        $chart_projects[] = $total_projects;
        $chart_profit[] = $company_profit;
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Team Analysis</h1>
        <p>Monitor and analyze the performance of your Area Managers.</p>
        <div class="analysis-dashboard">
            <div class="leaderboard-container">
                <h2>Leaderboard</h2>
                <table class="wp-list-table widefat fixed striped users">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column">Manager</th>
                            <th scope="col" class="manage-column">Assigned Location</th>
                            <th scope="col" class="manage-column">Total Projects</th>
                            <th scope="col" class="manage-column">Amount Paid to Vendors</th>
                            <th scope="col" class="manage-column">Company Profit</th>
                            <th scope="col" class="manage-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($manager_data)) {
                            usort($manager_data, fn($a, $b) => $b['company_profit'] <=> $a['company_profit']);
                            foreach ($manager_data as $data) {
                                ?>
                                <tr>
                                    <td><strong><a href="?page=team-analysis&manager_id=<?php echo $data['id']; ?>"><?php echo esc_html($data['name']); ?></a></strong></td>
                                    <td>
                                        <?php
                                        if ($data['assigned_state'] && $data['assigned_city']) {
                                            echo esc_html($data['assigned_city'] . ', ' . $data['assigned_state']);
                                        } else {
                                            echo 'Not Assigned';
                                        }
                                        ?>
                                        <button class="button button-small change-location-btn" data-manager-id="<?php echo $data['id']; ?>">Change</button>
                                    </td>
                                    <td><?php echo $data['total_projects']; ?></td>
                                    <td>₹<?php echo number_format($data['paid_to_vendors'], 2); ?></td>
                                    <td>₹<?php echo number_format($data['company_profit'], 2); ?></td>
                                    <td><a href="?page=team-analysis&manager_id=<?php echo $data['id']; ?>" class="button">View Details</a></td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="6">No Area Managers found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            // --- Location Modal Logic ---
            let statesAndCities = [];

            // Fetch location data
            $.getJSON('<?php echo plugin_dir_url( __FILE__ ) . '../../assets/data/indian-states-cities.json'; ?>', function(data) {
                statesAndCities = data.states;
            });

            $('.change-location-btn').on('click', function() {
                const managerId = $(this).data('manager-id');
                $('#manager-id-input').val(managerId);
                
                // Populate states
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
        });
    </script>
    <style>
        .analysis-dashboard { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        @media (max-width: 782px) { .analysis-dashboard { grid-template-columns: 1fr; } }
        #location-modal {
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
        #location-modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            width: 400px;
        }
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
        $status = get_post_meta($project->ID, '_project_status', true) ?: 'pending';
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
                <button class="button" disabled>Generate Report</button>
                <button class="button" disabled>Share via Email</button>
                <button class="button" disabled>Share via WhatsApp</button>
            </div>
            <div class="detail-card wide">
                <h3>Associated Clients</h3>
                <ul>
                    <?php foreach($clients as $client) { if($client) echo '<li>' . esc_html($client->display_name) . ' (' . esc_html($client->user_email) . ')</li>'; } ?>
                </ul>
            </div>
            <div class="detail-card wide">
                <h3>Associated Vendors</h3>
                <ul>
                    <?php foreach($vendors as $vendor) { if($vendor) echo '<li>' . esc_html($vendor->display_name) . ' (' . esc_html($vendor->user_email) . ')</li>'; } ?>
                </ul>
            </div>
        </div>
    </div>
    <style>
        .manager-details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px; }
        .detail-card { background: #fff; padding: 20px; border: 1px solid #ddd; }
        .detail-card.wide { grid-column: span 2; }
    </style>
    <?php
}
