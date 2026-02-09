<?php
/**
 * Unified Lead Details API
 * 
 * Single API endpoint that works for all roles: Admin, Manager, Area Manager, Sales Manager
 * Shows creator information and complete follow-up history with attribution.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Unified_Lead_API {

    public function __construct() {
        // Unified lead details API - works for ALL roles
        add_action('wp_ajax_get_lead_details', [$this, 'get_lead_details']);
        
        // Keep old endpoints for backward compatibility (they call the unified one)
        add_action('wp_ajax_get_lead_details_for_sm', [$this, 'get_lead_details']);
        add_action('wp_ajax_get_lead_details_for_am', [$this, 'get_lead_details']);
    }

    /**
     * Universal Lead Details API
     * Works for Admin, Manager, Area Manager, Sales Manager
     */
    public function get_lead_details() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $user = wp_get_current_user();
        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        
        if (!$lead_id) {
            wp_send_json_error(['message' => 'No lead ID provided']);
        }
        
        $lead = get_post($lead_id);
        
        if (!$lead || $lead->post_type !== 'solar_lead') {
            wp_send_json_error(['message' => 'Invalid lead']);
        }
        
        // Universal permission check
        $has_access = $this->check_lead_access($user, $lead, $lead_id);
        
        if (!$has_access) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        
        // Get creator information
        $creator_info = $this->get_creator_info($lead, $lead_id);
        
        // Get follow-ups with attribution (who added each followup)
        $followups = $this->get_followups_with_attribution($lead_id);
        
        wp_send_json_success([
            'lead' => [
                'id' => $lead_id,
                'name' => $lead->post_title,
                'phone' => get_post_meta($lead_id, '_lead_phone', true),
                'email' => get_post_meta($lead_id, '_lead_email', true),
                'address' => get_post_meta($lead_id, '_lead_address', true),
                'status' => get_post_meta($lead_id, '_lead_status', true) ?: 'new',
                'lead_type' => get_post_meta($lead_id, '_lead_type', true) ?: 'solar_project',
                'project_type' => get_post_meta($lead_id, '_lead_project_type', true),
                'system_size' => get_post_meta($lead_id, '_lead_system_size', true),
                'source' => get_post_meta($lead_id, '_lead_source', true),
                'notes' => get_post_meta($lead_id, '_lead_notes', true),
                'created_date' => $lead->post_date,
            ],
            'creator' => $creator_info,
            'followups' => $followups,
        ]);
    }
    
    /**
     * Check if user has access to this lead
     */
    private function check_lead_access($user, $lead, $lead_id) {
        // Admin - always has access
        if (current_user_can('administrator')) {
            return true;
        }
        
        // Lead creator - always has access
        if ($lead->post_author == $user->ID) {
            return true;
        }
        
        // Check if created by Sales Manager
        $created_by_sm = get_post_meta($lead_id, '_created_by_sales_manager', true);
        
        if ($created_by_sm) {
            // Creator SM has access
            if ($created_by_sm == $user->ID) {
                return true;
            }
            
            // Area Manager supervising this SM
            $sm_supervisor = get_user_meta($created_by_sm, '_supervised_by_area_manager', true);
            if ($sm_supervisor == $user->ID) {
                return true;
            }
            
            // Manager supervising the AM who supervises the SM
            if (in_array('manager', (array)$user->roles)) {
                $supervised_ams = get_users([
                    'meta_key' => '_supervised_by_manager',
                    'meta_value' => $user->ID,
                    'fields' => 'ID'
                ]);
                if ($sm_supervisor && in_array($sm_supervisor, $supervised_ams)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get creator information
     */
    private function get_creator_info($lead, $lead_id) {
        $creator_id = $lead->post_author;
        $creator = get_userdata($creator_id);
        
        if (!$creator) {
            return [
                'id' => 0,
                'name' => 'Unknown',
                'role' => 'unknown',
                'role_label' => 'Unknown'
            ];
        }
        
        $created_by_sm = get_post_meta($lead_id, '_created_by_sales_manager', true);
        $role = !empty($creator->roles) ? $creator->roles[0] : 'unknown';
        
        $role_labels = [
            'administrator' => 'Admin',
            'manager' => 'Manager',
            'area_manager' => 'Area Manager',
            'sales_manager' => 'Sales Manager',
            'solar_vendor' => 'Vendor',
            'solar_client' => 'Client'
        ];
        
        return [
            'id' => $creator_id,
            'name' => $creator->display_name,
            'role' => $role,
            'role_label' => $role_labels[$role] ?? ucwords(str_replace('_', ' ', $role)),
            'created_via' => $created_by_sm ? 'sales_manager' : 'area_manager'
        ];
    }
    
    /**
     * Get follow-ups with attribution (who added each one)
     */
    private function get_followups_with_attribution($lead_id) {
        global $wpdb;
        $table_followups = $wpdb->prefix . 'solar_lead_followups';
        
        $followups = $wpdb->get_results($wpdb->prepare(
            "SELECT f.*, u.display_name as added_by_name, u.ID as added_by_id
             FROM {$table_followups} f
             LEFT JOIN {$wpdb->users} u ON f.sales_manager_id = u.ID
             WHERE f.lead_id = %d 
             ORDER BY f.activity_date DESC",
            $lead_id
        ));
        
        return $followups ?: [];
    }
}

// Initialize the API
new KSC_Unified_Lead_API();
