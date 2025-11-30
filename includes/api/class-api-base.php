<?php
/**
 * Base API Class
 * 
 * Provides shared utilities for all API modules.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

abstract class KSC_API_Base {
    
    /**
     * Verify user is logged in as vendor
     * 
     * @return int Vendor user ID
     * @sends JSON error if not vendor
     */
    protected function verify_vendor_role() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
        }
        
        $user = wp_get_current_user();
        if (!in_array('solar_vendor', (array)$user->roles)) {
            wp_send_json_error(['message' => 'Access denied. Vendor role required.']);
        }
        
        return get_current_user_id();
    }
    
    /**
     * Verify user is logged in as area manager
     * 
     * @return WP_User Area manager user object
     * @sends JSON error if not area manager
     */
    protected function verify_area_manager_role() {
        if (!is_user_logged_in() || !in_array('area_manager', (array)wp_get_current_user()->roles)) {
            wp_send_json_error(['message' => 'Permission denied. Area Manager role required.']);
        }
        
        return wp_get_current_user();
    }
    
    /**
     * Verify user is admin or area manager
     * 
     * @return array ['user' => WP_User, 'is_admin' => bool]
     * @sends JSON error if neither role
     */
    protected function verify_admin_or_manager() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
        }
        
        $user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_manager = in_array('area_manager', (array)$user->roles);
        
        if (!$is_admin && !$is_manager) {
            wp_send_json_error(['message' => 'Permission denied. Admin or Area Manager role required.']);
        }
        
        return [
            'user' => $user,
            'is_admin' => $is_admin,
            'is_manager' => $is_manager
        ];
    }
    
    /**
     * Verify user is client
     * 
     * @return int Client user ID
     * @sends JSON error if not client
     */
    protected function verify_client_role() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
        }
        
        $user = wp_get_current_user();
        if (!in_array('solar_client', (array)$user->roles)) {
            wp_send_json_error(['message' => 'Access denied. Client role required.']);
        }
        
        return get_current_user_id();
    }
    
    /**
     * Check if vendor has coverage for a project
     * 
     * @param int $vendor_id Vendor user ID
     * @param int $project_id Project post ID
     * @return array ['has_coverage' => bool, 'state' => string, 'city' => string]
     */
    protected function check_vendor_coverage($vendor_id, $project_id) {
        $project_state = get_post_meta($project_id, '_project_state', true);
        $project_city = get_post_meta($project_id, '_project_city', true);
        
        $purchased_states = get_user_meta($vendor_id, 'purchased_states', true) ?: [];
        $purchased_cities = get_user_meta($vendor_id, 'purchased_cities', true) ?: [];
        
        $has_state_coverage = in_array($project_state, $purchased_states);
        
        // Check city coverage
        $has_city_coverage = false;
        if (is_array($purchased_cities)) {
            foreach ($purchased_cities as $city_obj) {
                if (is_array($city_obj) && isset($city_obj['city']) && $city_obj['city'] === $project_city) {
                    $has_city_coverage = true;
                    break;
                } elseif (is_string($city_obj) && $city_obj === $project_city) {
                    $has_city_coverage = true;
                    break;
                }
            }
        }
        
        return [
            'has_coverage' => ($has_state_coverage || $has_city_coverage),
            'state' => $project_state,
            'city' => $project_city,
            'has_state' => $has_state_coverage,
            'has_city' => $has_city_coverage
        ];
    }
    
    /**
     * Verify project ownership for area manager
     * 
     * @param int $project_id Project post ID
     * @param int $manager_id Area manager user ID
     * @return WP_Post Project object
     * @sends JSON error if not owner
     */
    protected function verify_project_ownership($project_id, $manager_id) {
        $project = get_post($project_id);
        
        if (!$project || $project->post_type !== 'solar_project') {
            wp_send_json_error(['message' => 'Invalid project']);
        }
        
        if ($project->post_author != $manager_id) {
            wp_send_json_error(['message' => 'You do not have permission to manage this project.']);
        }
        
        return $project;
    }
    
    /**
     * Sanitize and validate email
     * 
     * @param string $email Email to validate
     * @return string Sanitized email
     * @sends JSON error if invalid
     */
    protected function validate_email($email) {
        $email = sanitize_email($email);
        
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email address']);
        }
        
        return $email;
    }
    
    /**
     * Auto-update project status to 'in_progress' when vendor submits first step
     * 
     * @param int $project_id Project post ID
     * @return void
     */
    protected function check_and_update_project_status($project_id) {
        $current_status = get_post_meta($project_id, 'project_status', true);
        if ($current_status === 'assigned') {
            update_post_meta($project_id, 'project_status', 'in_progress');
        }
    }
    
    /**
     * Get vendor display name (company name or user display name)
     * 
     * @param int $vendor_id Vendor user ID
     * @return string Display name
     */
    protected function get_vendor_display_name($vendor_id) {
        $company_name = get_user_meta($vendor_id, 'company_name', true);
        if (!empty($company_name)) {
            return $company_name;
        }
        
        $vendor = get_userdata($vendor_id);
        return $vendor ? $vendor->display_name : 'Unknown Vendor';
    }
}
