<?php
/**
 * Admin Dashboard Widgets
 * 
 * Provides comprehensive widgets for WordPress admin dashboard
 * showing key metrics for the Krtrim Solar Core plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SP_Admin_Widgets {

    public function __construct() {
        add_action('wp_dashboard_setup', [$this, 'register_widgets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Enqueue scripts and styles for admin dashboard
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on dashboard
        if ('index.php' !== $hook) {
            return;
        }

        // Enqueue Chart.js from CDN
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', [], '3.9.1', true);
        
        // Enqueue custom dashboard CSS
        wp_enqueue_style('sp-admin-widgets', plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin-dashboard-widgets.css', [], '1.0.0');
        
        // Enqueue custom dashboard JS
        wp_add_inline_script('chartjs', $this->get_chart_js_code());
    }

    /**
     * Get JavaScript code for charts
     */
    private function get_chart_js_code() {
        $stats = $this->get_financial_stats();
        
        // Get last 6 months revenue data
        $revenue_data = $this->get_revenue_trend_data();
        
        return "
        jQuery(document).ready(function($) {
            // Financial Trend Chart
            var ctx = document.getElementById('spRevenueTrendCanvas');
            if (ctx) {
                ctx = ctx.getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: " . json_encode($revenue_data['labels']) . ",
                        datasets: [{
                            label: 'Revenue (₹)',
                            data: " . json_encode($revenue_data['values']) . ",
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderColor: '#667eea',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#667eea',
                            pointBorderColor: 'white',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Revenue Trend (Last 6 Months)',
                                font: {
                                    size: 14,
                                    weight: '600'
                                },
                                color: '#2d3748'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    callback: function(value) {
                                        return '₹' + value.toLocaleString();
                                    },
                                    color: '#718096'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#718096'
                                }
                            }
                        }
                    }
                });
            }
        });
        ";
    }


    public function register_widgets() {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'sp_business_overview',
            '📊 Solar Business Overview',
            [$this, 'render_business_overview']
        );

        wp_add_dashboard_widget(
            'sp_financial_summary',
            '💰 Financial Summary',
            [$this, 'render_financial_summary']
        );

        wp_add_dashboard_widget(
            'sp_area_manager_performance',
            '👥 Area Manager Performance',
            [$this, 'render_area_manager_performance']
        );

        wp_add_dashboard_widget(
            'sp_recent_activity',
            '🔔 Recent Activity',
            [$this, 'render_recent_activity']
        );

        wp_add_dashboard_widget(
            'sp_quick_stats',
            '📈 Quick Stats',
            [$this, 'render_quick_stats']
        );
    }

    /**
     * Widget 1: Business Overview
     */
    public function render_business_overview() {
        $stats = $this->get_business_stats();
        ?>
        <div class="sp-widget-content">
            <div class="sp-stats-grid">
                <div class="sp-stat-box sp-primary">
                    <div class="sp-stat-icon">📊</div>
                    <div class="sp-stat-info">
                        <span class="sp-stat-label">Total Projects</span>
                        <span class="sp-stat-value"><?php echo number_format($stats['total_projects']); ?></span>
                    </div>
                </div>
                <div class="sp-stat-box sp-success">
                    <div class="sp-stat-icon">✅</div>
                    <div class="sp-stat-info">
                        <span class="sp-stat-label">Completed</span>
                        <span class="sp-stat-value"><?php echo number_format($stats['completed_projects']); ?></span>
                    </div>
                </div>
                <div class="sp-stat-box sp-warning">
                    <div class="sp-stat-icon">⚡</div>
                    <div class="sp-stat-info">
                        <span class="sp-stat-label">Active</span>
                        <span class="sp-stat-value"><?php echo number_format($stats['active_projects']); ?></span>
                    </div>
                </div>
                <div class="sp-stat-box sp-info">
                    <div class="sp-stat-icon">👥</div>
                    <div class="sp-stat-info">
                        <span class="sp-stat-label">Total Leads</span>
                        <span class="sp-stat-value"><?php echo number_format($stats['total_leads']); ?></span>
                    </div>
                </div>
                <div class="sp-stat-box sp-info">
                    <div class="sp-stat-icon">✨</div>
                    <div class="sp-stat-info">
                        <span class="sp-stat-label">Conversion Rate</span>
                        <span class="sp-stat-value"><?php echo number_format($stats['conversion_rate'], 1); ?>%</span>
                    </div>
                </div>
                <div class="sp-stat-box sp-cleaning">
                    <div class="sp-stat-icon">🧹</div>
                    <div class="sp-stat-info">
                        <span class="sp-stat-label">Cleaning Services</span>
                        <span class="sp-stat-value"><?php echo number_format($stats['cleaning_services']); ?></span>
                    </div>
                </div>
                <div class="sp-stat-box sp-visit">
                    <div class="sp-stat-icon">📅</div>
                    <div class="sp-stat-info">
                        <span class="sp-stat-label">Cleaning Visits</span>
                        <span class="sp-stat-value"><?php echo number_format($stats['total_visits']); ?></span>
                    </div>
                </div>
                <div class="sp-stat-box sp-progress">
                    <div class="sp-stat-icon">⚡</div>
                    <div class="sp-stat-info">
                        <span class="sp-stat-label">Visits in Progress</span>
                        <span class="sp-stat-value"><?php echo number_format($stats['visits_in_progress']); ?></span>
                    </div>
                </div>
            </div>
            <div class="sp-widget-footer">
                <a href="<?php echo admin_url('edit.php?post_type=solar_project'); ?>" class="sp-view-all">View All Projects →</a>
            </div>
        </div>
        <?php
    }

    /**
     * Widget 2: Financial Summary
     */
    public function render_financial_summary() {
        $stats = $this->get_financial_stats();
        ?>
        <div class="sp-widget-content">
            <div class="sp-financial-grid">
                <div class="sp-financial-item sp-revenue">
                    <span class="sp-financial-label">💵 Total Revenue</span>
                    <span class="sp-financial-value">₹<?php echo number_format($stats['total_revenue'], 2); ?></span>
                </div>
                <div class="sp-financial-item sp-costs">
                    <span class="sp-financial-label">💸 Vendor Costs</span>
                    <span class="sp-financial-value">₹<?php echo number_format($stats['total_costs'], 2); ?></span>
                </div>
                <div class="sp-financial-item sp-profit">
                    <span class="sp-financial-label">💰 Company Profit</span>
                    <span class="sp-financial-value sp-highlight">₹<?php echo number_format($stats['total_profit'], 2); ?></span>
                </div>
                <div class="sp-financial-item sp-margin">
                    <span class="sp-financial-label">📊 Profit Margin</span>
                    <span class="sp-financial-value"><?php echo number_format($stats['profit_margin'], 1); ?>%</span>
                </div>
            </div>
            <div class="sp-trend-chart" id="spRevenueChart">
                <canvas id="spRevenueTrendCanvas" height="80"></canvas>
            </div>
        </div>
        <?php
    }

    /**
     * Widget 3: Area Manager Performance
     */
    public function render_area_manager_performance() {
        $managers = $this->get_area_manager_stats();
        ?>
        <div class="sp-widget-content">
            <?php if (!empty($managers)) : ?>
                <table class="sp-performance-table">
                    <thead>
                        <tr>
                            <th>Manager</th>
                            <th>Projects</th>
                            <th>Revenue</th>
                            <th>Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($managers, 0, 5) as $manager) : ?>
                            <tr>
                                <td class="sp-manager-name">
                                    <?php echo esc_html($manager['name']); ?>
                                </td>
                                <td><?php echo number_format($manager['projects']); ?></td>
                                <td>₹<?php echo number_format($manager['revenue'], 0); ?></td>
                                <td class="sp-profit-cell">₹<?php echo number_format($manager['profit'], 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="sp-no-data">No area managers found.</p>
            <?php endif; ?>
            <div class="sp-widget-footer">
                <a href="<?php echo admin_url('admin.php?page=team-analysis'); ?>" class="sp-view-all">View Full Analysis →</a>
            </div>
        </div>
        <?php
    }

    /**
     * Widget 4: Recent Activity
     */
    public function render_recent_activity() {
        $activities = $this->get_recent_activities();
        ?>
        <div class="sp-widget-content">
            <div class="sp-activity-feed">
                <?php if (!empty($activities)) : ?>
                    <?php foreach ($activities as $activity) : ?>
                        <div class="sp-activity-item">
                            <span class="sp-activity-icon"><?php echo $activity['icon']; ?></span>
                            <div class="sp-activity-details">
                                <span class="sp-activity-text"><?php echo esc_html($activity['text']); ?></span>
                                <span class="sp-activity-time"><?php echo human_time_diff($activity['time'], current_time('timestamp')); ?> ago</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="sp-no-data">No recent activity.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Widget 5: Quick Stats
     */
    public function render_quick_stats() {
        $stats = $this->get_quick_stats();
        ?>
        <div class="sp-widget-content">
            <div class="sp-quick-stats-grid">
                <div class="sp-quick-stat">
                    <div class="sp-quick-stat-number"><?php echo $stats['today_leads']; ?></div>
                    <div class="sp-quick-stat-label">Today's Leads</div>
                </div>
                <div class="sp-quick-stat">
                    <div class="sp-quick-stat-number"><?php echo $stats['week_projects']; ?></div>
                    <div class="sp-quick-stat-label">This Week's Projects</div>
                </div>
                <div class="sp-quick-stat sp-attention">
                    <div class="sp-quick-stat-number"><?php echo $stats['pending_approvals']; ?></div>
                    <div class="sp-quick-stat-label">Pending Approvals</div>
                </div>
                <div class="sp-quick-stat sp-attention">
                    <div class="sp-quick-stat-number"><?php echo $stats['unassigned_projects']; ?></div>
                    <div class="sp-quick-stat-label">Unassigned Projects</div>
                </div>
            </div>
            <div class="sp-widget-footer sp-actions">
                <a href="<?php echo admin_url('admin.php?page=vendor-approvals'); ?>" class="button button-small">Approvals</a>
                <a href="<?php echo admin_url('edit.php?post_type=solar_project'); ?>" class="button button-small">Projects</a>
            </div>
        </div>
        <?php
    }

    // === DATA METHODS ===

    private function get_business_stats() {
        $total_projects = wp_count_posts('solar_project')->publish;
        $completed = $this->count_projects_by_status('completed');
        $active = ($this->count_projects_by_status('in_progress') + $this->count_projects_by_status('assigned'));
        
        // Lead Count - Sum all statuses except trash and converted (converted are already clients)
        $lead_counts = wp_count_posts('solar_lead');
        $total_leads = 0;
        foreach ($lead_counts as $status => $count) {
            if ($status !== 'trash') {
                $total_leads += $count;
            }
        }
        
        $converted_leads = $this->count_leads_by_status('converted');
        $conversion_rate = $total_leads > 0 ? ($converted_leads / $total_leads) * 100 : 0;

        // Cleaning Services stats
        $cleaning_services = wp_count_posts('cleaning_service')->publish;
        $total_visits = wp_count_posts('cleaning_visit')->publish;
        
        $visits_in_progress = $this->count_visits_by_status('in_progress');
        $visits_completed = $this->count_visits_by_status('completed');

        return [
            'total_projects' => $total_projects,
            'completed_projects' => $completed,
            'active_projects' => $active,
            'total_leads' => $total_leads,
            'conversion_rate' => $conversion_rate,
            'cleaning_services' => $cleaning_services,
            'total_visits' => $total_visits,
            'visits_in_progress' => $visits_in_progress,
            'visits_completed' => $visits_completed
        ];
    }

    private function get_financial_stats() {
        global $wpdb;
        
        // Only count published (active) projects, exclude trashed/deleted
        $revenue = $wpdb->get_var("
            SELECT SUM(pm.meta_value) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_total_project_cost'
            AND p.post_type = 'solar_project'
            AND p.post_status = 'publish'
        ");
        
        $costs = $wpdb->get_var("
            SELECT SUM(pm.meta_value) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_paid_to_vendor'
            AND p.post_type = 'solar_project'
            AND p.post_status = 'publish'
        ");
        
        $profit = $revenue - $costs;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        return [
            'total_revenue' => floatval($revenue),
            'total_costs' => floatval($costs),
            'total_profit' => $profit,
            'profit_margin' => $margin
        ];
    }

    private function get_area_manager_stats() {
        $managers = get_users(['role' => 'area_manager']);
        $stats = [];

        foreach ($managers as $manager) {
            $projects = get_posts([
                'post_type' => 'solar_project',
                'author' => $manager->ID,
                'posts_per_page' => -1,
                'fields' => 'ids'
            ]);

            $revenue = 0;
            $profit = 0;

            foreach ($projects as $project_id) {
                $revenue += floatval(get_post_meta($project_id, '_total_project_cost', true));
                $profit += floatval(get_post_meta($project_id, '_company_profit', true));
            }

            $stats[] = [
                'name' => $manager->display_name,
                'projects' => count($projects),
                'revenue' => $revenue,
                'profit' => $profit
            ];
        }

        // Sort by revenue descending
        usort($stats, function($a, $b) {
            return $b['revenue'] - $a['revenue'];
        });

        return $stats;
    }

    private function get_recent_activities() {
        $activities = [];

        // Recent projects (last 7 days)
        $recent_projects = get_posts([
            'post_type' => 'solar_project',
            'posts_per_page' => 3,
            'date_query' => [
                ['after' => '7 days ago']
            ]
        ]);

        foreach ($recent_projects as $project) {
            $activities[] = [
                'icon' => '🆕',
                'text' => 'New project created: ' . $project->post_title,
                'time' => strtotime($project->post_date)
            ];
        }

        // Recent completed projects
        $completed = get_posts([
            'post_type' => 'solar_project',
            'posts_per_page' => 2,
            'meta_query' => [
                ['key' => 'project_status', 'value' => 'completed']
            ]
        ]);

        foreach ($completed as $project) {
            $activities[] = [
                'icon' => '✅',
                'text' => 'Project completed: ' . $project->post_title,
                'time' => strtotime($project->post_modified)
            ];
        }

        // ✅ RECENT BIDS (last 7 days)
        global $wpdb;
        $bids_table = $wpdb->prefix . 'project_bids';
        $recent_bids = $wpdb->get_results(
            "SELECT b.*, p.post_title, u.display_name 
            FROM {$bids_table} b 
            JOIN {$wpdb->posts} p ON b.project_id = p.ID 
            JOIN {$wpdb->users} u ON b.vendor_id = u.ID 
            WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY b.created_at DESC 
            LIMIT 3"
        );

        foreach ($recent_bids as $bid) {
            $activities[] = [
                'icon' => '🎯',
                'text' => sprintf('Bid by %s on %s - ₹%s', $bid->display_name, $bid->post_title, number_format($bid->bid_amount, 0)),
                'time' => strtotime($bid->created_at)
            ];
        }

        // ✅ RECENT STEP SUBMISSIONS (last 7 days)
        $steps_table = $wpdb->prefix . 'solar_process_steps';
        $recent_steps = $wpdb->get_results(
            "SELECT s.*, p.post_title 
            FROM {$steps_table} s 
            JOIN {$wpdb->posts} p ON s.project_id = p.ID 
            WHERE s.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND s.admin_status = 'under_review'
            ORDER BY s.updated_at DESC 
            LIMIT 5"
        );

        foreach ($recent_steps as $step) {
            $activities[] = [
                'icon' => '📤',
                'text' => sprintf('Step submitted: %s for %s', $step->step_name, $step->post_title),
                'time' => strtotime($step->updated_at)
            ];
        }

        // ✅ RECENT ACTIVITY LOGS (Cleaner & System Actions)
        $logs_table = $wpdb->prefix . 'solar_activity_logs';
        $recent_logs = $wpdb->get_results(
            "SELECT * FROM {$logs_table} 
            ORDER BY created_at DESC 
            LIMIT 10"
        );

        foreach ($recent_logs as $log) {
            $icon = '📝';
            if (strpos($log->action_type, 'create') !== false) $icon = '🆕';
            if (strpos($log->action_type, 'delete') !== false) $icon = '🗑️';
            if (strpos($log->action_type, 'update') !== false) $icon = '✏️';

            $activities[] = [
                'icon' => $icon,
                'text' => sprintf('%s by %s: %s', ucfirst(str_replace('_', ' ', $log->action_type)), ucfirst($log->user_role), $log->details),
                'time' => strtotime($log->created_at)
            ];
        }

        // ✅ RECENT CLEANING VISITS (last 7 days)
        $recent_visits = get_posts([
            'post_type' => 'cleaning_visit',
            'posts_per_page' => 5,
            'date_query' => [
                ['after' => '7 days ago']
            ],
            'orderby' => 'modified',
            'order' => 'DESC'
        ]);

        foreach ($recent_visits as $visit) {
            $v_status = get_post_meta($visit->ID, '_status', true);
            $icon = '📅';
            $text = 'Cleaning visit updated: ' . $visit->post_title;
            
            if ($v_status === 'scheduled') { $icon = '📅'; $text = 'New cleaning visit scheduled: ' . $visit->post_title; }
            if ($v_status === 'in_progress') { $icon = '⚡'; $text = 'Cleaning visit started: ' . $visit->post_title; }
            if ($v_status === 'completed') { $icon = '✅'; $text = 'Cleaning visit completed: ' . $visit->post_title; }
            if ($v_status === 'cancelled') { $icon = '❌'; $text = 'Cleaning visit cancelled: ' . $visit->post_title; }

            $activities[] = [
                'icon' => $icon,
                'text' => $text,
                'time' => strtotime($visit->post_modified)
            ];
        }

        // Sort by time
        usort($activities, function($a, $b) {
            return $b['time'] - $a['time'];
        });

        return array_slice($activities, 0, 10); // Increased limit to show more variety
    }

    private function get_quick_stats() {
        // Today's leads
        $today_leads = get_posts([
            'post_type' => 'solar_lead',
            'date_query' => [
                ['after' => 'today']
            ],
            'fields' => 'ids'
        ]);

        // This week's projects
        $week_projects = get_posts([
            'post_type' => 'solar_project',
            'date_query' => [
                ['after' => '1 week ago']
            ],
            'fields' => 'ids'
        ]);

        // Pending approvals (vendors with pending status)
        $pending_approvals = count(get_users([
            'role' => 'solar_vendor',
            'meta_query' => [
                ['key' => 'vendor_approval_status', 'value' => 'pending']
            ]
        ]));

        // Unassigned projects
        $unassigned = get_posts([
            'post_type' => 'solar_project',
            'meta_query' => [
                ['key' => 'project_status', 'value' => 'pending']
            ],
            'fields' => 'ids'
        ]);

        return [
            'today_leads' => count($today_leads),
            'week_projects' => count($week_projects),
            'pending_approvals' => $pending_approvals,
            'unassigned_projects' => count($unassigned)
        ];
    }

    private function count_projects_by_status($status) {
        $count = get_posts([
            'post_type' => 'solar_project',
            'meta_query' => [
                ['key' => 'project_status', 'value' => $status]
            ],
            'fields' => 'ids'
        ]);
        return count($count);
    }

    private function count_leads_by_status($status) {
        $count = get_posts([
            'post_type' => 'solar_lead',
            'meta_query' => [
                ['key' => '_lead_status', 'value' => $status]
            ],
            'fields' => 'ids',
            'numberposts' => -1
        ]);
        return count($count);
    }

    private function count_visits_by_status($status) {
        $count = get_posts([
            'post_type' => 'cleaning_visit',
            'meta_query' => [
                ['key' => '_status', 'value' => $status]
            ],
            'fields' => 'ids',
            'numberposts' => -1
        ]);
        return count($count);
    }

    /**
     * Get revenue trend data for last 6 months
     */
    private function get_revenue_trend_data() {
        global $wpdb;
        
        $labels = [];
        $values = [];
        
        // Get last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $date = date('Y-m', strtotime("-$i months"));
            $month_name = date('M', strtotime("-$i months"));
            
            $revenue = $wpdb->get_var($wpdb->prepare("
                SELECT SUM(pm.meta_value) 
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_total_project_cost'
                AND DATE_FORMAT(p.post_date, '%%Y-%%m') = %s
                AND p.post_type = 'solar_project'
            ", $date));
            
            $labels[] = $month_name;
            $values[] = floatval($revenue);
        }
        
        return [
            'labels' => $labels,
            'values' => $values
        ];
    }
}
