<?php
/**
 * Client API Class
 * 
 * Handles all client-specific AJAX endpoints.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Client_API extends KSC_API_Base {
    
    public function __construct() {
        // Step comments
        add_action('wp_ajax_client_submit_step_comment', [$this, 'client_submit_step_comment']);
        
        // Note: Client payment recording is handled by Admin/Manager API
        // as it requires area manager permission
    }
    
    /**
     * Client submit comment on a process step
     */
    public function client_submit_step_comment() {
        check_ajax_referer('client_comment_nonce', 'nonce');
        
        $client_id = $this->verify_client_role();
        
        $step_id = isset($_POST['step_id']) ? intval($_POST['step_id']) : 0;
        $comment_text = isset($_POST['comment_text']) ? sanitize_textarea_field($_POST['comment_text']) : '';
        
        if (empty($step_id) || empty($comment_text)) {
            wp_send_json_error(['message' => 'Step ID and comment are required.']);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'solar_process_steps';
        
        $existing_comment = $wpdb->get_var($wpdb->prepare(
            "SELECT client_comment FROM {$table} WHERE id = %d",
            $step_id
        ));
        
        $updated_comment = trim($existing_comment . "\n\n" . "Client: " . $comment_text);
        
        $updated = $wpdb->update(
            $table,
            [
                'client_comment' => $updated_comment,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $step_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($updated === false) {
            wp_send_json_error(['message' => 'Failed to submit comment.']);
        }
        
        wp_send_json_success(['message' => 'Comment submitted successfully.']);
    }
}
