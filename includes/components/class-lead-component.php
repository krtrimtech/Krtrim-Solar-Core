<?php
/**
 * Shared Lead Component
 * 
 * Reusable lead management component for Area Manager and Sales Manager dashboards.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.1
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Lead_Component {

    /**
     * Check if user role can create client
     */
    public static function can_create_client($user_roles) {
        $allowed = ['administrator', 'manager', 'area_manager'];
        foreach ($allowed as $role) {
            if (in_array($role, (array) $user_roles)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user role can delete leads
     */
    public static function can_delete_lead($user_roles) {
        $allowed = ['administrator', 'manager', 'area_manager'];
        foreach ($allowed as $role) {
            if (in_array($role, (array) $user_roles)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all lead status options (including converted)
     */
    public static function get_status_options() {
        return [
            'new' => ['label' => 'New', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
            'interested' => ['label' => 'Interested', 'color' => '#8b5cf6', 'bg' => '#ede9fe'],
            'in_process' => ['label' => 'In Process', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
            'converted' => ['label' => 'Converted', 'color' => '#10b981', 'bg' => '#d1fae5'],
            'lost' => ['label' => 'Lost', 'color' => '#ef4444', 'bg' => '#fee2e2'],
        ];
    }

    /**
     * Get manual status options (excludes 'converted' - reserved for auto-conversion only)
     */
    public static function get_manual_status_options() {
        $all_statuses = self::get_status_options();
        unset($all_statuses['converted']); // Remove converted from manual selection
        return $all_statuses;
    }

    /**
     * Render the lead management section
     */
    public static function render_lead_section($user_roles, $dashboard_type = 'sales_manager') {
        $can_create_client = self::can_create_client($user_roles);
        $can_delete = self::can_delete_lead($user_roles);
        $all_statuses = self::get_status_options(); // For display/filtering
        $manual_statuses = self::get_manual_status_options(); // For manual selection
        ?>
        <div class="lead-component" data-dashboard="<?php echo esc_attr($dashboard_type); ?>" data-can-create-client="<?php echo $can_create_client ? 'true' : 'false'; ?>" data-can-delete="<?php echo $can_delete ? 'true' : 'false'; ?>">
            
            <!-- Header with Add Lead Button -->
            <div class="lead-section-header">
                <div class="lead-filters">
                    <div class="search-box">
                        <input type="text" id="lead-search" placeholder="Search leads..." />
                        <span class="search-icon">🔍</span>
                    </div>
                    <select id="filter-lead-type" class="lead-filter-select">
                        <option value="">All Types</option>
                        <option value="solar_project">☀️ Solar Projects</option>
                        <option value="cleaning_service">🧹 Cleaning Services</option>
                    </select>
                    <select id="filter-lead-status" class="lead-filter-select">
                        <option value="">All Status</option>
                        <?php foreach ($all_statuses as $key => $status): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($status['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary" id="btn-open-add-lead">
                    ➕ Add Lead
                </button>
            </div>

            <!-- Leads Table -->
            <div class="lead-table-wrapper">
                <table class="lead-table" id="leads-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Follow-ups</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="leads-table-body">
                        <tr><td colspan="7" class="loading-cell">Loading leads...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Lead Cards (shown on small screens) -->
            <div class="lead-cards-mobile" id="leads-cards-mobile">
                <p>Loading leads...</p>
            </div>
        </div>

        <!-- Add Lead Modal -->
        <div id="add-lead-modal" class="lead-modal" style="display:none;">
            <div class="lead-modal-content">
                <span class="close-lead-modal">&times;</span>
                <h3>➕ Add New Lead</h3>
                <form id="create-lead-form">
                    <?php wp_nonce_field('sp_lead_nonce', 'lead_nonce'); ?>
                    
                    <!-- Lead Type Selection -->
                    <div class="form-group">
                        <label>Lead Type *</label>
                        <style>
                            .lead-type-options {
                                display: flex;
                                gap: 15px;
                                margin-top: 8px;
                            }
                            .lead-type-option {
                                flex: 1;
                                padding: 15px;
                                border: 2px solid #e0e0e0;
                                border-radius: 8px;
                                cursor: pointer;
                                text-align: center;
                                transition: all 0.2s;
                                position: relative;
                                background: white;
                            }
                            .lead-type-option:has(input:checked) {
                                border-color: #4f46e5;
                                background: #f5f3ff;
                            }
                            .lead-type-option input[type="radio"] {
                                position: absolute;
                                top: 8px;
                                right: 8px;
                                width: 16px;
                                height: 16px;
                                cursor: pointer;
                            }
                        </style>
                        <div class="lead-type-options">
                            <label class="lead-type-option">
                                <input type="radio" name="lead_type" value="solar_project" checked>
                                <span style="font-size: 24px; display: block;">☀️</span>
                                <div style="margin-top: 5px; font-weight: 500;">Solar Project</div>
                            </label>
                            <label class="lead-type-option">
                                <input type="radio" name="lead_type" value="cleaning_service">
                                <span style="font-size: 24px; display: block;">🧹</span>
                                <div style="margin-top: 5px; font-weight: 500;">Cleaning Service</div>
                            </label>
                        </div>
                    </div>

                    <!-- Project Type (shown only for solar_project) -->
                    <div class="form-group" id="project-type-group">
                        <label for="lead_project_type">Project Type</label>
                        <select id="lead_project_type" name="lead_project_type">
                            <option value="residential">🏠 Residential</option>
                            <option value="commercial">🏢 Commercial</option>
                            <option value="industrial">🏭 Industrial</option>
                            <option value="agricultural">🌾 Agricultural</option>
                            <option value="government">🏛️ Government</option>
                        </select>
                    </div>

                    <!-- System Size (shown for cleaning_service) -->
                    <div class="form-group" id="system-size-group" style="display: none;">
                        <label for="lead_system_size">System Size (kW) - for pricing</label>
                        <input type="number" id="lead_system_size" name="lead_system_size" min="1" max="500" placeholder="e.g., 5">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="lead_name">Name *</label>
                            <input type="text" id="lead_name" name="lead_name" required>
                        </div>
                        <div class="form-group">
                            <label for="lead_phone">Phone Number *</label>
                            <input type="text" id="lead_phone" name="lead_phone" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lead_email">Email</label>
                            <input type="email" id="lead_email" name="lead_email">
                        </div>
                        <div class="form-group">
                            <label for="lead_source">Lead Source</label>
                            <select id="lead_source" name="lead_source">
                                <option value="door_to_door">Door-to-Door</option>
                                <option value="referral">Referral</option>
                                <option value="event">Event/Exhibition</option>
                                <option value="phone_inquiry">Phone Inquiry</option>
                                <option value="social_media">Social Media</option>
                                <option value="website">Website</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="lead_address">Address</label>
                        <textarea id="lead_address" name="lead_address" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="lead_notes">Initial Notes</label>
                        <textarea id="lead_notes" name="lead_notes" rows="3" placeholder="Any initial observations..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">➕ Add Lead</button>
                </form>
            </div>
        </div>

        <!-- Lead Detail Modal -->
        <div id="lead-detail-modal" class="lead-modal" style="display:none;">
            <div class="lead-modal-content lead-detail-modal-content">
                <span class="close-lead-modal">&times;</span>
                <div id="lead-detail-header">
                    <h3 id="lead-detail-name">Lead Details</h3>
                    <span id="lead-detail-status" class="lead-status-badge"></span>
                </div>
                <div id="lead-detail-info">
                    <!-- Loaded dynamically -->
                </div>
                
                <!-- Follow-up Thread -->
                <div class="lead-followup-section">
                    <h4>📞 Follow-up History</h4>
                    <div id="lead-followup-thread">
                        <p>Loading follow-ups...</p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="lead-detail-actions">
                    <button class="btn btn-primary" id="btn-add-followup-detail">📞 Add Follow-up</button>
                    <a href="#" class="btn btn-whatsapp" id="btn-whatsapp-detail" target="_blank">💬 WhatsApp</a>
                    <a href="#" class="btn btn-call" id="btn-call-detail">📞 Call</a>
                    <?php if ($can_create_client): ?>
                        <button class="btn btn-success" id="btn-create-client-detail">👤 Create Client</button>
                    <?php endif; ?>
                </div>

                <!-- Status Update -->
                <div class="lead-status-update">
                    <label for="lead-status-select">Update Status:</label>
                    <select id="lead-status-select">
                        <?php foreach ($manual_statuses as $key => $status): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($status['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-secondary" id="btn-update-status">Update</button>
                </div>
            </div>
        </div>

        <!-- Add Follow-up Modal -->
        <div id="add-followup-modal" class="lead-modal" style="display:none;">
            <div class="lead-modal-content">
                <span class="close-lead-modal">&times;</span>
                <h3>📞 Add Follow-up</h3>
                <form id="add-followup-form">
                    <input type="hidden" id="followup_lead_id" name="lead_id">
                    <div class="form-group">
                        <label for="followup_type">Activity Type *</label>
                        <select id="followup_type" name="activity_type" required>
                            <option value="phone_call">📞 Phone Call</option>
                            <option value="whatsapp">💬 WhatsApp Message</option>
                            <option value="email">📧 Email</option>
                            <option value="meeting_offline">🤝 Offline Meeting</option>
                            <option value="meeting_online">💻 Online Meeting</option>
                            <option value="site_visit">🏠 Site Visit</option>
                            <option value="other">📝 Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="followup_date">Date & Time *</label>
                        <input type="datetime-local" id="followup_date" name="activity_date" required>
                    </div>
                    <div class="form-group">
                        <label for="followup_notes">Notes *</label>
                        <textarea id="followup_notes" name="notes" rows="4" required placeholder="What happened during this follow-up?"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Save Follow-up</button>
                </form>
            </div>
        </div>
        <?php
    }
}
