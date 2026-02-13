<?php
/**
 * Shared Cleaner Component
 * 
 * Reusable cleaner management component for Area Manager and Manager dashboards.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.4.7
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class KSC_Cleaner_Component {

    /**
     * Check if user role can create cleaners
     */
    public static function can_create_cleaner($user_roles) {
        $allowed = ['administrator', 'manager', 'area_manager'];
        foreach ($allowed as $role) {
            if (in_array($role, (array) $user_roles)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all Area Managers (for assignment dropdown)
     */
    public static function get_area_managers() {
        return get_users([
            'role' => 'area_manager',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
    }

    /**
     * Render the cleaner management section
     * 
     * @param array $user_roles Current user roles
     * @param string $dashboard_type 'manager' or 'area_manager'
     */
    public static function render_cleaner_section($user_roles, $dashboard_type = 'area_manager') {
        $can_create = self::can_create_cleaner($user_roles);
        $is_manager_or_admin = in_array('administrator', (array)$user_roles) || in_array('manager', (array)$user_roles);
        ?>
        <div class="cleaner-component" data-dashboard="<?php echo esc_attr($dashboard_type); ?>" data-can-create="<?php echo $can_create ? 'true' : 'false'; ?>">
            
            <!-- Header with Add Cleaner Button -->
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div class="header-left">
                    <h3 style="margin: 0;">🧹 Manage Cleaners</h3>
                    <p style="color: #666; margin: 5px 0 0; font-size: 14px;">
                        <?php echo $dashboard_type === 'manager' ? 'Overview of all cleaning staff across regions' : 'Manage your assigned cleaning team'; ?>
                    </p>
                </div>
                <?php if ($can_create): ?>
                <button class="btn btn-primary" id="btn-open-add-cleaner">
                    ➕ Add New Cleaner
                </button>
                <?php endif; ?>
            </div>

            <!-- Cleaners Grid Container -->
            <div id="cleaners-list-container">
                <p>Loading cleaners...</p>
            </div>
        </div>

        <!-- Add/Edit Cleaner Modal -->
        <div id="add-cleaner-modal" class="cleaner-component-modal-overlay">
            <div class="cleaner-component-modal-content">
                <div class="cleaner-modal-header">
                    <h3 id="modal-cleaner-title" style="margin: 0;">➕ Add New Cleaner</h3>
                    <button class="close-cleaner-modal" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                </div>
                
                <div class="cleaner-modal-body">
                    <form id="create-cleaner-form" enctype="multipart/form-data">
                        <?php wp_nonce_field('ksc_cleaner_nonce', 'cleaner_nonce'); ?>
                        <input type="hidden" id="cleaner_id" name="cleaner_id" value="">
                        <input type="hidden" id="cleaner_action_type" name="action_type" value="create">

                        <!-- Personal Info -->
                        <div class="form-row" style="display: flex; gap: 15px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="cleaner_name" style="display: block; margin-bottom: 5px; font-weight: 500;">Full Name *</label>
                                <input type="text" id="cleaner_name" name="cleaner_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="cleaner_phone" style="display: block; margin-bottom: 5px; font-weight: 500;">Phone Number *</label>
                                <input type="text" id="cleaner_phone" name="cleaner_phone" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            </div>
                        </div>

                        <div class="form-row" style="display: flex; gap: 15px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="cleaner_email" style="display: block; margin-bottom: 5px; font-weight: 500;">Email</label>
                                <input type="email" id="cleaner_email" name="cleaner_email" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="cleaner_aadhaar" style="display: block; margin-bottom: 5px; font-weight: 500;">Aadhaar Number *</label>
                                <input type="text" id="cleaner_aadhaar" name="cleaner_aadhaar" maxlength="12" pattern="[0-9]{12}" required placeholder="12-digit Aadhaar" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="cleaner_address" style="display: block; margin-bottom: 5px; font-weight: 500;">Address</label>
                            <textarea id="cleaner_address" name="cleaner_address" rows="2" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;"></textarea>
                        </div>

                        <!-- Documents -->
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="cleaner_photo" style="display: block; margin-bottom: 5px; font-weight: 500;">Photo *</label>
                            <input type="file" id="cleaner_photo" name="cleaner_photo" accept="image/*" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            <p class="description" style="font-size: 12px; color: #666; margin-top: 5px;">Passport size photo for customer identification</p>
                            <div id="preview-cleaner-photo" style="margin-top: 10px; display: none;">
                                <img src="" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="cleaner_aadhaar_image" style="display: block; margin-bottom: 5px; font-weight: 500;">Aadhaar Card Image *</label>
                            <input type="file" id="cleaner_aadhaar_image" name="cleaner_aadhaar_image" accept="image/*" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            <p class="description" style="font-size: 12px; color: #666; margin-top: 5px;">Upload front side of Aadhaar card</p>
                             <div id="preview-cleaner-aadhaar" style="margin-top: 10px; display: none;">
                                <a href="#" target="_blank">View Current Document</a>
                            </div>
                        </div>

                        <!-- Manager Only: Assign AM -->
                        <?php if ($is_manager_or_admin): ?>
                        <div class="form-group" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <label for="assigned_area_manager" style="display: block; margin-bottom: 5px; font-weight: 500;">Assign to Area Manager <span style="color:#666; font-weight:normal;">(Optional)</span></label>
                            <select id="assigned_area_manager" name="assigned_area_manager" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                                <option value="">-- Select Area Manager --</option>
                                <?php
                                $all_ams = self::get_area_managers();
                                foreach ($all_ams as $am_user) {
                                    $am_city = get_user_meta($am_user->ID, 'city', true);
                                    $am_state = get_user_meta($am_user->ID, 'state', true);
                                    $loc = $am_city ? " ($am_city, $am_state)" : "";
                                    echo '<option value="' . esc_attr($am_user->ID) . '">' . esc_html($am_user->display_name . $loc) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description" style="font-size: 12px; color: #666; margin-top: 5px;">If selected, this cleaner will appear in this Area Manager's team.</p>
                        </div>
                        <?php endif; ?>

                        <div id="create-cleaner-feedback" style="margin-bottom: 15px;"></div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">➕ Save Cleaner</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Cleaner Detail Modal (Profile) -->
        <div id="cleaner-detail-modal" class="cleaner-component-modal-overlay">
            <div class="cleaner-component-modal-content">
                
                <div class="cleaner-profile-header">
                    <!-- Close Icon (Absolute Top Right) -->
                    <button class="close-cleaner-modal btn-close-header btn-close-absolute">&times;</button>

                    <div class="cleaner-profile-content">
                        <!-- Left: Image -->
                        <div class="profile-image-container">
                             <img id="modal-cleaner-photo" src="" alt="Cleaner">
                        </div>

                        <!-- Right: Details -->
                        <div class="profile-details-container">
                            <h2 id="modal-cleaner-name"></h2>
                            <div class="profile-meta-row">
                                <p id="modal-cleaner-phone">📞 </p>
                                <span class="role-badge">Solar Cleaner</span>
                            </div>
                            <button id="btn-edit-cleaner-profile" class="btn-edit-header" style="display: none;">✏️ Edit Profile</button>
                        </div>
                    </div>
                </div>

                <div style="padding: 30px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                        <!-- Left Column: Info -->
                        <div>
                            <h3 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0;">📋 Personal Details</h3>
                            
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; color: #666; font-size: 12px; font-weight: 600; text-transform: uppercase;">Email</label>
                                <p id="modal-cleaner-email" style="font-size: 16px; margin: 5px 0 0;"></p>
                            </div>
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; color: #666; font-size: 12px; font-weight: 600; text-transform: uppercase;">Address</label>
                                <p id="modal-cleaner-address" style="font-size: 16px; margin: 5px 0 0; color: #444; line-height: 1.5;"></p>
                            </div>
                            <div>
                                <label style="display: block; color: #666; font-size: 12px; font-weight: 600; text-transform: uppercase;">Aadhaar Number</label>
                                <p id="modal-cleaner-aadhaar" style="font-size: 16px; margin: 5px 0 0; letter-spacing: 1px;"></p>
                            </div>
                            <?php if ($is_manager_or_admin): ?>
                            <div style="margin-top: 20px;">
                                <label style="display: block; color: #666; font-size: 12px; font-weight: 600; text-transform: uppercase;">Assigned Supervisor</label>
                                <p id="modal-cleaner-supervisor" style="font-size: 16px; margin: 5px 0 0;"></p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right Column: Documents -->
                        <div>
                            <h3 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0;">🆔 Documents</h3>
                            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                                <p style="margin: 0 0 10px; font-weight: 600; color: #555;">Aadhaar Card Preview</p>
                                <img id="modal-cleaner-aadhaar-img" src="" alt="Aadhaar Card" style="max-width: 100%; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); cursor: pointer;" onclick="window.open(this.src, '_blank')">
                                <p style="margin-top: 10px; font-size: 12px; color: #888;">Click image to view full size</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer removed as per request -->
                    <div style="margin-top: 30px; pt: 20px; border-top: 1px solid #eee;"></div>
                </div>
            </div>
        </div>
        <?php
    }
}
