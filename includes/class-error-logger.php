<?php
/**
 * Error Logger Class
 * 
 * Provides centralized error logging with database storage.
 * Useful for debugging and monitoring production issues.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Error_Logger {
    
    private static $instance = null;
    private $table_name;
    private $debug_mode;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ksc_error_log';
        $this->debug_mode = get_option('ksc_debug_mode', false);
    }
    
    /**
     * Log an error
     * 
     * @param string $context Context of error (e.g., 'AJAX', 'API', 'VENDOR')
     * @param string $message Error message
     * @param array $data Additional data to log
     * @param string $level Error level (error, warning, info)
     */
    public function log($context, $message, $data = [], $level = 'error') {
        if (!$this->debug_mode) {
            return;
        }
        
        global $wpdb;
        
        $wpdb->insert(
            $this->table_name,
            [
                'context' => sanitize_text_field($context),
                'message' => sanitize_text_field($message),
                'level' => sanitize_text_field($level),
                'data' => json_encode($data),
                'user_id' => get_current_user_id(),
                'url' => isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : '',
                'ip_address' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Log AJAX error
     * 
     * @param string $action AJAX action name
     * @param string $error_message Error message
     * @param array $post_data POST data (will be sanitized)
     */
    public function log_ajax_error($action, $error_message, $post_data = []) {
        // Remove sensitive data
        $safe_data = $this->sanitize_log_data($post_data);
        
        $this->log(
            'AJAX',
            "Action: {$action} - Error: {$error_message}",
            [
                'action' => $action,
                'error' => $error_message,
                'post_data' => $safe_data
            ],
            'error'
        );
    }
    
    /**
     * Log API error
     * 
     * @param string $endpoint API endpoint
     * @param string $error_message Error message
     * @param array $context Additional context
     */
    public function log_api_error($endpoint, $error_message, $context = []) {
        $this->log(
            'API',
            "Endpoint: {$endpoint} - Error: {$error_message}",
            array_merge(['endpoint' => $endpoint], $context),
            'error'
        );
    }
    
    /**
     * Log warning
     */
    public function log_warning($context, $message, $data = []) {
        $this->log($context, $message, $data, 'warning');
    }
    
    /**
     * Log info
     */
    public function log_info($context, $message, $data = []) {
        $this->log($context, $message, $data, 'info');
    }
    
    /**
     * Get recent errors
     * 
     * @param int $limit Number of errors to retrieve
     * @param string $level Filter by level (optional)
     * @param string $context Filter by context (optional)
     * @return array Array of error objects
     */
    public function get_recent_errors($limit = 50, $level = null, $context = null) {
        global $wpdb;
        
        $query = "SELECT * FROM {$this->table_name}";
        $where = [];
        $values = [];
        
        if ($level) {
            $where[] = 'level = %s';
            $values[] = $level;
        }
        
        if ($context) {
            $where[] = 'context = %s';
            $values[] = $context;
        }
        
        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }
        
        $query .= ' ORDER BY created_at DESC LIMIT %d';
        $values[] = $limit;
        
        if (!empty($values)) {
            return $wpdb->get_results($wpdb->prepare($query, $values));
        } else {
            return $wpdb->get_results($wpdb->prepare($query, $limit));
        }
    }
    
    /**
     * Get error count
     * 
     * @param string $level Filter by level (optional)
     * @return int Error count
     */
    public function get_error_count($level = null) {
        global $wpdb;
        
        if ($level) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE level = %s",
                $level
            ));
        } else {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        }
    }
    
    /**
     * Get errors by date range
     */
    public function get_errors_by_date($start_date, $end_date, $limit = 100) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE created_at BETWEEN %s AND %s 
             ORDER BY created_at DESC 
             LIMIT %d",
            $start_date,
            $end_date,
            $limit
        ));
    }
    
    /**
     * Clear all logs
     */
    public function clear_logs() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }
    
    /**
     * Clear old logs (older than X days)
     * 
     * @param int $days Number of days to keep
     */
    public function clear_old_logs($days = 30) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    /**
     * Check if debug mode is enabled
     */
    public function is_debug_enabled() {
        return (bool) $this->debug_mode;
    }
    
    /**
     * Enable debug mode
     */
    public function enable_debug() {
        update_option('ksc_debug_mode', true);
        $this->debug_mode = true;
    }
    
    /**
     * Disable debug mode
     */
    public function disable_debug() {
        update_option('ksc_debug_mode', false);
        $this->debug_mode = false;
    }
    
    /**
     * Sanitize log data (remove passwords, tokens, etc.)
     */
    private function sanitize_log_data($data) {
        if (!is_array($data)) {
            return $data;
        }
        
        $sensitive_keys = ['password', 'pwd', 'token', 'secret', 'api_key', 'nonce'];
        
        foreach ($data as $key => $value) {
            $key_lower = strtolower($key);
            
            // Remove sensitive fields
            foreach ($sensitive_keys as $sensitive) {
                if (strpos($key_lower, $sensitive) !== false) {
                    $data[$key] = '[REDACTED]';
                    continue 2;
                }
            }
            
            // Recursively sanitize arrays
            if (is_array($value)) {
                $data[$key] = $this->sanitize_log_data($value);
            }
        }
        
        return $data;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Create database table
     * Called during plugin activation
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ksc_error_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            context varchar(50) NOT NULL,
            message text NOT NULL,
            level varchar(20) DEFAULT 'error',
            data longtext,
            user_id bigint(20),
            url varchar(500),
            ip_address varchar(45),
            user_agent varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY context (context),
            KEY level (level),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
