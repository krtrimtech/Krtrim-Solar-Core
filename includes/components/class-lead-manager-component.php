<?php
/**
 * Lead Manager Component
 * 
 * Centralized logic for managing Solar Leads.
 * used by KSC_Admin_Manager_API and KSC_Sales_Manager_API.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeadManagerComponent {

    /**
     * Get leads based on arguments
     * 
     * @param array $args Query arguments override
     * @return array List of formatted leads
     */
    public static function get_leads($args = []) {
        $defaults = [
            'post_type' => 'solar_lead',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query_args = wp_parse_args($args, $defaults);
        
        // Handle search
        if (!empty($args['search'])) {
            $query_args['s'] = sanitize_text_field($args['search']);
        }

        // ALWAYS exclude converted leads - they're now clients, not leads
        $meta_query = isset($query_args['meta_query']) ? $query_args['meta_query'] : [];
        $meta_query[] = [
            'key' => '_lead_status',
            'value' => 'converted',
            'compare' => '!='
        ];

        // Handle specific meta filters passed in 'filter_meta'
        if (isset($args['filter_meta']) && is_array($args['filter_meta'])) {
            foreach ($args['filter_meta'] as $key => $value) {
                if ($key === 'status') {
                     // Filter by specific status (but never show converted)
                     if ($value !== 'converted') {
                         $meta_query[] = [
                            'key' => '_lead_status',
                            'value' => $value,
                         ];
                     }
                } elseif ($key === 'lead_type') {
                     $meta_query[] = [
                        'key' => '_lead_type',
                        'value' => $value,
                     ];
                }
            }
        }
        
        if (!empty($meta_query)) {
            $query_args['meta_query'] = $meta_query;
        }

        $query = new WP_Query($query_args);
        $leads = [];
        
        // Pre-fetch followups if needed (optimization)
        global $wpdb;
        $table_followups = $wpdb->prefix . 'solar_lead_followups';

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $lead_id = get_the_ID();
                $leads[] = self::format_lead_data($lead_id, $table_followups);
            }
            wp_reset_postdata();
        }

        return $leads;
    }

    /**
     * Format a single lead's data
     * 
     * @param int $lead_id
     * @param string $table_followups Optional table name for optimization
     * @return array
     */
    public static function format_lead_data($lead_id, $table_followups = null) {
        if (!$table_followups) {
            global $wpdb;
            $table_followups = $wpdb->prefix . 'solar_lead_followups';
        }

        global $wpdb;
        
        // Count followups
        $followup_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_followups} WHERE lead_id = %d",
            $lead_id
        ));

        // Get recent followups (for Sales Manager view mainly, but useful generally)
        $recent_followups = $wpdb->get_results($wpdb->prepare(
            "SELECT activity_type, activity_date, notes FROM {$table_followups} 
             WHERE lead_id = %d ORDER BY activity_date DESC LIMIT 5",
            $lead_id
        ));

        // Get basic data
        $data = [
            'id' => $lead_id,
            'name' => get_the_title($lead_id),
            'phone' => get_post_meta($lead_id, '_lead_phone', true),
            'email' => get_post_meta($lead_id, '_lead_email', true),
            'status' => get_post_meta($lead_id, '_lead_status', true) ?: 'new',
            'lead_type' => get_post_meta($lead_id, '_lead_type', true) ?: 'solar_project',
            'project_type' => get_post_meta($lead_id, '_lead_project_type', true),
            'system_size' => get_post_meta($lead_id, '_lead_system_size', true),
            'source' => get_post_meta($lead_id, '_lead_source', true),
            'address' => get_post_meta($lead_id, '_lead_address', true),
            'notes' => get_post_meta($lead_id, '_lead_notes', true) ?: get_post_field('post_content', $lead_id),
            'content_notes' => get_post_field('post_content', $lead_id),
            'created_date' => get_the_date('Y-m-d', $lead_id),
            'followup_count' => intval($followup_count),
            'followups' => $recent_followups // Include recent history
        ];

        return $data;
    }

    /**
     * Create a new lead
     * 
     * @param array $data Input data
     * @param int $author_id User ID creating the lead
     * @return int|WP_Error Lead ID or Error
     */
    public static function create_lead($data, $author_id) {
        error_log('🔵 [LeadManagerComponent] create_lead called');
        error_log('📦 [LeadManagerComponent] Author ID: ' . $author_id);
        error_log('📦 [LeadManagerComponent] Input data: ' . print_r($data, true));
        
        $name = isset($data['lead_name']) ? sanitize_text_field($data['lead_name']) : '';
        $phone = isset($data['lead_phone']) ? sanitize_text_field($data['lead_phone']) : '';
        
        error_log('🔍 [LeadManagerComponent] Sanitized name: ' . $name);
        error_log('🔍 [LeadManagerComponent] Sanitized phone: ' . $phone);
        
        if (empty($name)) {
            error_log('❌ [LeadManagerComponent] Validation failed: Name is empty');
            return new WP_Error('missing_name', 'Lead name is required');
        }

        $notes = isset($data['lead_notes']) ? sanitize_textarea_field($data['lead_notes']) : '';
        
        $post_data = [
            'post_title' => $name,
            'post_content' => $notes,
            'post_type' => 'solar_lead',
            'post_status' => 'publish',
            'post_author' => $author_id,
        ];
        
        error_log('📝 [LeadManagerComponent] Post data prepared: ' . print_r($post_data, true));
        error_log('🚀 [LeadManagerComponent] Calling wp_insert_post...');

        $lead_id = wp_insert_post($post_data);

        if (is_wp_error($lead_id)) {
            error_log('❌ [LeadManagerComponent] wp_insert_post failed: ' . $lead_id->get_error_message());
            return $lead_id;
        }
        
        error_log('✅ [LeadManagerComponent] Post created! ID=' . $lead_id);
        error_log('💾 [LeadManagerComponent] Saving meta fields...');

        // Save Meta with proper sanitization
        if (isset($data['lead_phone'])) {
            update_post_meta($lead_id, '_lead_phone', sanitize_text_field($data['lead_phone']));
        }
        if (isset($data['lead_email'])) {
            update_post_meta($lead_id, '_lead_email', sanitize_email($data['lead_email']));
        }
        if (isset($data['lead_address'])) {
            update_post_meta($lead_id, '_lead_address', sanitize_textarea_field($data['lead_address']));
        }
        if (isset($data['lead_source'])) {
            update_post_meta($lead_id, '_lead_source', sanitize_text_field($data['lead_source']));
        }
        if (isset($data['lead_status'])) {
            update_post_meta($lead_id, '_lead_status', sanitize_text_field($data['lead_status']));
        }
        if (isset($data['lead_type'])) {
            update_post_meta($lead_id, '_lead_type', sanitize_text_field($data['lead_type']));
        }
        if (isset($data['lead_project_type'])) {
            update_post_meta($lead_id, '_lead_project_type', sanitize_text_field($data['lead_project_type']));
        }
        if (isset($data['lead_system_size'])) {
            update_post_meta($lead_id, '_lead_system_size', floatval($data['lead_system_size']));
        }
        
        // Ensure default status
        if (empty($data['lead_status'])) {
             update_post_meta($lead_id, '_lead_status', 'new');
        }
        
        // Also save notes to meta for SM compatibility (SM API uses _lead_notes meta)
        if (!empty($notes)) {
            update_post_meta($lead_id, '_lead_notes', $notes);
        }

        error_log('✨ [LeadManagerComponent] All meta saved successfully!');
        error_log('🎉 [LeadManagerComponent] Lead creation complete! Returning ID=' . $lead_id);
        return $lead_id;
    }
    
    /**
     * Update an existing lead
     * 
     * @param int $lead_id
     * @param array $data
     * @return bool|WP_Error
     */
    public static function update_lead($lead_id, $data) {
        $lead = get_post($lead_id);
        if (!$lead || $lead->post_type !== 'solar_lead') {
            return new WP_Error('invalid_lead', 'Invalid lead ID');
        }

        // Update main post fields if provided
        $post_updates = ['ID' => $lead_id];
        $updated = false;
        
        if (isset($data['lead_name'])) {
            $post_updates['post_title'] = sanitize_text_field($data['lead_name']);
            $updated = true;
        }
        if (isset($data['lead_notes'])) {
            $post_updates['post_content'] = sanitize_textarea_field($data['lead_notes']);
            $updated = true;
        }
        
        if ($updated) {
            wp_update_post($post_updates);
        }

        // Update Meta with proper sanitization
        if (isset($data['lead_phone'])) {
            update_post_meta($lead_id, '_lead_phone', sanitize_text_field($data['lead_phone']));
        }
        if (isset($data['lead_email'])) {
            update_post_meta($lead_id, '_lead_email', sanitize_email($data['lead_email']));
        }
        if (isset($data['lead_address'])) {
            update_post_meta($lead_id, '_lead_address', sanitize_textarea_field($data['lead_address']));
        }
        if (isset($data['lead_source'])) {
            update_post_meta($lead_id, '_lead_source', sanitize_text_field($data['lead_source']));
        }
        if (isset($data['lead_status'])) {
            update_post_meta($lead_id, '_lead_status', sanitize_text_field($data['lead_status']));
        }
        if (isset($data['lead_type'])) {
            update_post_meta($lead_id, '_lead_type', sanitize_text_field($data['lead_type']));
        }
        if (isset($data['lead_project_type'])) {
            update_post_meta($lead_id, '_lead_project_type', sanitize_text_field($data['lead_project_type']));
        }
        if (isset($data['lead_system_size'])) {
            update_post_meta($lead_id, '_lead_system_size', floatval($data['lead_system_size']));
        }
        if (isset($data['lead_notes'])) {
            update_post_meta($lead_id, '_lead_notes', sanitize_textarea_field($data['lead_notes']));
        }

        return true;
    }

    /**
     * Delete a lead
     * 
     * @param int $lead_id
     * @return bool|WP_Error
     */
    public static function delete_lead($lead_id) {
        $lead = get_post($lead_id);
        if (!$lead || $lead->post_type !== 'solar_lead') {
             return new WP_Error('invalid_lead', 'Invalid lead ID');
        }
        
        $deleted = wp_delete_post($lead_id, true);
        if (!$deleted) {
            return new WP_Error('delete_failed', 'Failed to delete lead');
        }
        
        return true;
    }
}
