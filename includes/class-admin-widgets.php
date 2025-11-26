<?php
/**
 * Admin Dashboard Widgets
 * 
 * Provides comprehensive widgets for WordPress admin dashboard
 * showing key metrics for the Kritim Solar Core plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SP_Admin_Widgets {

    public function __construct() {
        add_action('wp_dashboard_setup', [$this, 'register_widgets']);
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
        
        $total_leads = wp_count_posts('solar_lead')->publish;
        $converted_leads = $this->count_leads_by_status('converted');
        $conversion_rate = $total_leads > 0 ? ($converted_leads / $total_leads) * 100 : 0;

        return [
            'total_projects' => $total_projects,
            'completed_projects' => $completed,
            'active_projects' => $active,
            'total_leads' => $total_leads,
            'conversion_rate' => $conversion_rate
        ];
    }

    private function get_financial_stats() {
        global $wpdb;
        
        $revenue = $wpdb->get_var("
            SELECT SUM(meta_value) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_total_project_cost'
        ");
        
        $costs = $wpdb->get_var("
            SELECT SUM(meta_value) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_vendor_paid_amount'
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
                ['key' => '_project_status', 'value' => 'completed']
            ]
        ]);

        foreach ($completed as $project) {
            $activities[] = [
                'icon' => '✅',
                'text' => 'Project completed: ' . $project->post_title,
                'time' => strtotime($project->post_modified)
            ];
        }

        // Sort by time
        usort($activities, function($a, $b) {
            return $b['time'] - $a['time'];
        });

        return array_slice($activities, 0, 5);
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
                ['key' => '_project_status', 'value' => 'pending']
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
                ['key' => '_project_status', 'value' => $status]
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
            'fields' => 'ids'
        ]);
        return count($count);
    }
}
