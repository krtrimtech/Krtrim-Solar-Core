<?php
/**
 * Debug Panel Admin Page
 * 
 * Displays error logs and provides debug mode toggle.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Permission check
if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Handle form submissions
if (isset($_POST['ksc_debug_settings_nonce']) && wp_verify_nonce($_POST['ksc_debug_settings_nonce'], 'ksc_debug_settings')) {
    $debug_mode = isset($_POST['debug_mode']) ? true : false;
    $logger = KSC_Error_Logger::instance();
    
    if ($debug_mode) {
        $logger->enable_debug();
        echo '<div class="notice notice-success"><p>Debug mode enabled</p></div>';
    } else {
        $logger->disable_debug();
        echo '<div class="notice notice-success"><p>Debug mode disabled</p></div>';
    }
}

if (isset($_POST['ksc_clear_logs_nonce']) && wp_verify_nonce($_POST['ksc_clear_logs_nonce'], 'ksc_clear_logs')) {
    KSC_Error_Logger::instance()->clear_logs();
    echo '<div class="notice notice-success"><p>All logs cleared!</p></div>';
}

$logger = KSC_Error_Logger::instance();
$debug_mode = $logger->is_debug_enabled();
$errors = $logger->get_recent_errors(100);
$error_count = $logger->get_error_count();
$error_level_count = $logger->get_error_count('error');
$warning_count = $logger->get_error_count('warning');
?>

<div class="wrap">
    <h1>🐛 Debug Panel</h1>
    
    <div class="card" style="max-width: none;">
        <h2>Debug Mode</h2>
        <form method="post" style="margin: 20px 0;">
            <?php wp_nonce_field('ksc_debug_settings', 'ksc_debug_settings_nonce'); ?>
            <label style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" name="debug_mode" value="1" <?php checked($debug_mode, true); ?>>
                <strong>Enable Debug Logging</strong>
            </label>
            <p class="description">
                When enabled, all errors, AJAX failures, and API issues will be logged to the database.<br>
                <strong>Warning:</strong> This may impact performance on high-traffic sites. Use for debugging only.
            </p>
            <p>
                <button type="submit" class="button button-primary">Save Settings</button>
            </p>
        </form>
        
        <div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-top: 20px;">
            <h3 style="margin: 0 0 10px 0;">Statistics</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <div>
                    <div style="font-size: 24px; font-weight: bold;"><?php echo $error_count; ?></div>
                    <div style="color: #666;">Total Logs</div>
                </div>
                <div>
                    <div style="font-size: 24px; font-weight: bold; color: #dc3232;"><?php echo $error_level_count; ?></div>
                    <div style="color: #666;">Errors</div>
                </div>
                <div>
                    <div style="font-size: 24px; font-weight: bold; color: #f0ad4e;"><?php echo $warning_count; ?></div>
                    <div style="color: #666;">Warnings</div>
                </div>
                <div>
                    <div style="font-size: 24px; font-weight: bold; color: <?php echo $debug_mode ? '#46b450' : '#999'; ?>">
                        <?php echo $debug_mode ? 'ON' : 'OFF'; ?>
                    </div>
                    <div style="color: #666;">Debug Mode</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card" style="max-width: none; margin-top: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0;">Recent Errors (Last 100)</h2>
            <div>
                <button class="button" onclick="location.reload()">Refresh</button>
                <form method="post" style="display: inline;" onsubmit="return confirm('Clear all logs?');">
                    <?php wp_nonce_field('ksc_clear_logs', 'ksc_clear_logs_nonce'); ?>
                    <button type="submit" class="button">Clear All Logs</button>
                </form>
            </div>
        </div>
        
        <?php if (empty($errors)): ?>
            <p style="color: #46b450; padding: 20px; text-align: center; background: #f0f9f4; border-radius: 4px;">
                ✓ No errors logged<?php echo !$debug_mode ? ' (Debug mode is disabled)' : ''; ?>
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Level</th>
                            <th style="width: 100px;">Context</th>
                            <th style="width: 150px;">Time</th>
                            <th>Message</th>
                            <th style="width: 100px;">User</th>
                            <th style="width: 100px;">IP</th>
                            <th style="width: 60px;">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($errors as $error): ?>
                            <?php
                            $level_colors = [
                                'error' => '#dc3232',
                                'warning' => '#f0ad4e',
                                'info' => '#0073aa'
                            ];
                            $color = isset($level_colors[$error->level]) ? $level_colors[$error->level] : '#999';
                            ?>
                            <tr>
                                <td>
                                    <span style="background: <?php echo $color; ?>; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                        <?php echo strtoupper(esc_html($error->level)); ?>
                                    </span>
                                </td>
                                <td><code><?php echo esc_html($error->context); ?></code></td>
                                <td style="font-size: 12px;" title="<?php echo esc_attr($error->created_at); ?>">
                                    <?php echo human_time_diff(strtotime($error->created_at), current_time('timestamp')); ?> ago
                                </td>
                                <td style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo esc_html($error->message); ?>
                                </td>
                                <td>
                                    <?php
                                    if ($error->user_id) {
                                        $user = get_userdata($error->user_id);
                                        echo $user ? esc_html($user->display_name) : 'User #' . $error->user_id;
                                    } else {
                                        echo 'Guest';
                                    }
                                    ?>
                                </td>
                                <td style="font-size: 11px;"><?php echo esc_html($error->ip_address); ?></td>
                                <td>
                                    <?php if (!empty($error->data) && $error->data !== 'null'): ?>
                                        <button type="button" class="button button-small" onclick="toggleDetails(<?php echo $error->id; ?>)">View</button>
                                        <div id="details-<?php echo $error->id; ?>" style="display: none; margin-top: 10px; background: #f8f8f8; padding: 10px; border-radius: 4px; font-size: 12px; max-width: 600px;">
                                            <strong>URL:</strong> <?php echo esc_html($error->url); ?><br>
                                            <strong>User Agent:</strong> <?php echo esc_html(substr($error->user_agent, 0, 60)); ?><br>
                                            <strong>Data:</strong>
                                            <pre style="background: white; padding: 10px; border-radius: 3px; overflow-x: auto; max-height: 300px;"><?php echo esc_html(json_encode(json_decode($error->data), JSON_PRETTY_PRINT)); ?></pre>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDetails(id) {
    var el = document.getElementById('details-' + id);
    if (el.style.display === 'none') {
        el.style.display = 'block';
    } else {
        el.style.display = 'none';
    }
}
</script>

<style>
.card {
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e0;
}
</style>