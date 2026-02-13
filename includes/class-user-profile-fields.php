<?php

class SP_User_Profile_Fields {

    public function __construct() {
        add_action( 'show_user_profile', [ $this, 'add_custom_user_profile_fields' ] );
        add_action( 'edit_user_profile', [ $this, 'add_custom_user_profile_fields' ] );
        add_action( 'personal_options_update', [ $this, 'save_custom_user_profile_fields' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_custom_user_profile_fields' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_filter( 'get_avatar', [ $this, 'custom_user_avatar' ], 10, 5 );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script( 'sp-admin-profile', plugin_dir_url( __DIR__ ) . 'assets/js/admin-profile.js', [ 'jquery' ], '1.0.0', true );
    }

    /**
     * Filter avatar to use uploaded photo
     */
    public function custom_user_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
        $user = false;

        if ( is_numeric( $id_or_email ) ) {
            $id = (int) $id_or_email;
            $user = get_user_by( 'id', $id );
        } elseif ( is_object( $id_or_email ) ) {
            if ( ! empty( $id_or_email->user_id ) ) {
                $id = (int) $id_or_email->user_id;
                $user = get_user_by( 'id', $id );
            }
        } else {
            $user = get_user_by( 'email', $id_or_email );
        }

        if ( $user && is_object( $user ) ) {
            $photo_url = get_user_meta( $user->ID, '_photo_url', true );
            if ( $photo_url ) {
                $avatar = "<img src='{$photo_url}' alt='{$alt}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
            }
        }

        return $avatar;
    }

    private function get_location_data() {
        $locations = [];
        $file_path = plugin_dir_path( __DIR__ ) . 'assets/data/indian-states-cities.json';

        if (file_exists($file_path)) {
            $json_data = file_get_contents($file_path);
            $data = json_decode($json_data, true);

            if (isset($data['states'])) {
                foreach ($data['states'] as $state) {
                    $locations[$state['state']] = $state['districts'];
                }
            }
        }

        return $locations;
    }

    public function add_custom_user_profile_fields( $user ) {
        $current_user = wp_get_current_user();
        $can_edit = in_array( 'administrator', (array) $current_user->roles ) || in_array( 'manager', (array) $current_user->roles );
        
        // Sales Manager fields
        if ( in_array( 'sales_manager', (array) $user->roles ) ) {
            $this->render_sales_manager_fields( $user, $can_edit );
            return;
        }
        
        // Area Manager fields
        if ( in_array( 'area_manager', (array) $user->roles ) ) {
            $this->render_area_manager_fields( $user, $can_edit );
            return;
        }

        // Cleaner fields
        if ( in_array( 'solar_cleaner', (array) $user->roles ) ) {
            $this->render_cleaner_fields( $user, $can_edit );
            return;
        }
    }

    /**
     * Render Cleaner fields
     */
    private function render_cleaner_fields( $user, $can_edit ) {
        $phone = get_user_meta($user->ID, 'phone', true);
        $aadhaar = get_user_meta($user->ID, '_aadhaar_number', true);
        $address = get_user_meta($user->ID, '_cleaner_address', true);
        $supervisor_id = get_user_meta($user->ID, '_supervised_by_area_manager', true);
        
        $photo_url = get_user_meta($user->ID, '_photo_url', true);
        $photo_id = get_user_meta($user->ID, '_photo_id', true);
        
        $aadhaar_url = get_user_meta($user->ID, '_aadhaar_image_url', true);
        $aadhaar_id = get_user_meta($user->ID, '_aadhaar_image_id', true);

        // Get Area Managers
        $area_managers = get_users([
            'role' => 'area_manager',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
        ?>
        <h3><?php _e( 'Cleaner Profile Information', 'krtrim-solar-core' ); ?></h3>
        <table class="form-table">
            <!-- Cleaner Photo -->
            <tr>
                <th><label><?php _e( 'Profile Photo', 'krtrim-solar-core' ); ?></label></th>
                <td>
                    <div class="image-preview-wrapper" style="margin-bottom: 10px;">
                        <img id="cleaner_photo_preview" src="<?php echo esc_url($photo_url); ?>" style="max-width: 150px; border-radius: 5px; display: <?php echo $photo_url ? 'block' : 'none'; ?>;">
                    </div>
                    <input type="hidden" name="cleaner_photo_id" id="cleaner_photo_id" value="<?php echo esc_attr($photo_id); ?>">
                    <input type="text" name="cleaner_photo_url" id="cleaner_photo_url" class="regular-text" value="<?php echo esc_url($photo_url); ?>" readonly>
                    <br><br>
                    <?php if ($can_edit): ?>
                        <button class="button upload-cleaner-photo"><?php echo $photo_id ? 'Change Photo' : 'Upload Photo'; ?></button>
                        <button class="button remove-cleaner-photo" style="color: #a00; border-color: #a00; display: <?php echo $photo_id ? 'inline-block' : 'none'; ?>;">Remove Photo</button>
                    <?php endif; ?>
                    <p class="description"><?php _e( 'Upload a profile photo. This will act as the user avatar.', 'krtrim-solar-core' ); ?></p>
                </td>
            </tr>

            <!-- Personal Details -->
            <tr>
                <th><label for="cleaner_phone"><?php _e( 'Phone Number', 'krtrim-solar-core' ); ?></label></th>
                <td>
                    <input type="text" name="cleaner_phone" id="cleaner_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" <?php if ( ! $can_edit ) echo 'readonly'; ?> />
                </td>
            </tr>
            <tr>
                <th><label for="cleaner_aadhaar"><?php _e( 'Aadhaar Number', 'krtrim-solar-core' ); ?></label></th>
                <td>
                    <input type="text" name="cleaner_aadhaar" id="cleaner_aadhaar" value="<?php echo esc_attr( $aadhaar ); ?>" class="regular-text" <?php if ( ! $can_edit ) echo 'readonly'; ?> />
                </td>
            </tr>
            <tr>
                <th><label for="cleaner_address"><?php _e( 'Address', 'krtrim-solar-core' ); ?></label></th>
                <td>
                    <textarea name="cleaner_address" id="cleaner_address" rows="3" cols="30" class="large-text" <?php if ( ! $can_edit ) echo 'readonly'; ?>><?php echo esc_textarea( $address ); ?></textarea>
                </td>
            </tr>
            
            <!-- Supervisor -->
            <tr>
                <th><label for="supervised_by_area_manager"><?php _e( 'Assigned Area Manager', 'krtrim-solar-core' ); ?></label></th>
                <td>
                    <select name="supervised_by_area_manager" id="supervised_by_area_manager" class="regular-text" <?php if ( ! $can_edit ) echo 'disabled'; ?>>
                        <option value="">-- Select Area Manager --</option>
                        <?php foreach ( $area_managers as $am ) : 
                            $am_city = get_user_meta($am->ID, 'city', true);
                            $am_state = get_user_meta($am->ID, 'state', true);
                            $loc = $am_city ? " ($am_city, $am_state)" : "";
                        ?>
                            <option value="<?php echo esc_attr( $am->ID ); ?>" <?php selected( $supervisor_id, $am->ID ); ?>>
                                <?php echo esc_html( $am->display_name . $loc ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <!-- Aadhaar Image -->
            <tr>
                <th><label><?php _e( 'Aadhaar Card Image', 'krtrim-solar-core' ); ?></label></th>
                <td>
                    <div class="image-preview-wrapper" style="margin-bottom: 10px;">
                        <img id="aadhaar_image_preview" src="<?php echo esc_url($aadhaar_url); ?>" style="max-width: 300px; border: 1px solid #ddd; padding: 4px; display: <?php echo $aadhaar_url ? 'block' : 'none'; ?>;">
                    </div>
                    <input type="hidden" name="aadhaar_image_id" id="aadhaar_image_id" value="<?php echo esc_attr($aadhaar_id); ?>">
                    <input type="text" name="aadhaar_image_url" id="aadhaar_image_url" class="regular-text" value="<?php echo esc_url($aadhaar_url); ?>" readonly>
                    <br><br>
                    <?php if ($can_edit): ?>
                        <button class="button upload-aadhaar-image"><?php echo $aadhaar_id ? 'Change Image' : 'Upload Aadhaar Image'; ?></button>
                        <button class="button remove-aadhaar-image" style="color: #a00; border-color: #a00; display: <?php echo $aadhaar_id ? 'inline-block' : 'none'; ?>;">Remove Image</button>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Area Manager location fields
     */
    private function render_area_manager_fields( $user, $can_edit ) {
        $selected_state = get_user_meta( $user->ID, 'state', true );
        $selected_city  = get_user_meta( $user->ID, 'city', true );
        $selected_city  = get_user_meta( $user->ID, 'city', true );
        $locations = $this->get_location_data();
        
        // Get Managers for supervisor selection
        $managers = get_users(['role' => 'manager', 'orderby' => 'display_name']);
        $supervisor_id = get_user_meta( $user->ID, '_supervised_by_manager', true );
        ?>
        <h3><?php _e( 'Area Manager Information', 'krtrim-solar-core' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="supervised_by_manager"><?php _e( 'Supervised By (Manager)', 'krtrim-solar-core' ); ?></label></th>
                <td>
                    <select name="supervised_by_manager" id="supervised_by_manager" class="regular-text" <?php if ( ! $can_edit ) echo 'disabled'; ?>>
                        <option value="">-- Independent / Unassigned --</option>
                        <?php foreach ( $managers as $mgr ) : ?>
                            <option value="<?php echo esc_attr( $mgr->ID ); ?>" <?php selected( $supervisor_id, $mgr->ID ); ?>>
                                <?php echo esc_html( $mgr->display_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e( 'Assign this Area Manager to a top-level Manager.', 'krtrim-solar-core' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="state"><?php _e( 'State', 'krtrim-solar-core' ); ?></label></th>
                <td>
                    <select name="state" id="sp-state-select" class="regular-text" <?php if ( ! $can_edit ) echo 'disabled'; ?>>
                        <option value="">Select State</option>
                        <?php foreach ( $locations as $state => $cities ) : ?>
                            <option value="<?php echo esc_attr( $state ); ?>" <?php selected( $selected_state, $state ); ?>><?php echo esc_html( $state ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="city"><?php _e( 'City', 'krtrim-solar-core' ); ?></label></th>
                <td>
                    <select name="city" id="sp-city-select" class="regular-text" <?php if ( ! $can_edit ) echo 'disabled'; ?>>
                        <option value="">Select City</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Sales Manager supervisor field
     */
    private function render_sales_manager_fields( $user, $can_edit ) {
        $supervisor_id = get_user_meta( $user->ID, '_supervised_by_area_manager', true );
        
        // Get all Area Managers
        $area_managers = get_users([
            'role' => 'area_manager',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
        
        // Get inherited location from supervisor
        $inherited_state = '';
        $inherited_city = '';
        if ( $supervisor_id ) {
            $inherited_state = get_user_meta( $supervisor_id, 'state', true );
            $inherited_city = get_user_meta( $supervisor_id, 'city', true );
        }
        ?>
        <h3><?php _e( 'Sales Manager Information', 'krtrim-solar-core' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="supervised_by"><?php _e( 'Supervised By (Area Manager)', 'krtrim-solar-core' ); ?></label></th>
                <td>
                    <select name="supervised_by_area_manager" id="supervised_by" class="regular-text" <?php if ( ! $can_edit ) echo 'disabled'; ?>>
                        <option value="">-- Select Supervisor --</option>
                        <?php foreach ( $area_managers as $am ) : 
                            $am_location = get_user_meta( $am->ID, 'city', true );
                            $am_state = get_user_meta( $am->ID, 'state', true );
                            $location_label = $am_location ? " ({$am_location}, {$am_state})" : '';
                        ?>
                            <option value="<?php echo esc_attr( $am->ID ); ?>" <?php selected( $supervisor_id, $am->ID ); ?>>
                                <?php echo esc_html( $am->display_name . $location_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e( 'The Area Manager who supervises this Sales Manager.', 'krtrim-solar-core' ); ?></p>
                </td>
            </tr>
            <?php if ( $inherited_state || $inherited_city ) : ?>
            <tr>
                <th><?php _e( 'Assigned Location', 'krtrim-solar-core' ); ?></th>
                <td>
                    <p><strong><?php echo esc_html( "$inherited_city, $inherited_state" ); ?></strong></p>
                    <p class="description"><?php _e( 'Location inherited from supervisor. Sales Manager will work in this area.', 'krtrim-solar-core' ); ?></p>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    public function save_custom_user_profile_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return false;
        }

        $current_user = wp_get_current_user();
        if ( ! ( in_array( 'administrator', (array) $current_user->roles ) || in_array( 'manager', (array) $current_user->roles ) ) ) {
            return false;
        }

        // Area Manager location fields
        if ( isset( $_POST['state'] ) ) {
            update_user_meta( $user_id, 'state', sanitize_text_field( $_POST['state'] ) );
        }

        if ( isset( $_POST['city'] ) ) {
            update_user_meta( $user_id, 'city', sanitize_text_field( $_POST['city'] ) );
        }
        
        // Area Manager supervisor field
        if ( isset( $_POST['supervised_by_manager'] ) ) {
            update_user_meta( $user_id, '_supervised_by_manager', intval( $_POST['supervised_by_manager'] ) );
        }

        // Sales Manager supervisor field
        if ( isset( $_POST['supervised_by_area_manager'] ) ) {
            $supervisor_id = intval( $_POST['supervised_by_area_manager'] );
            update_user_meta( $user_id, '_supervised_by_area_manager', $supervisor_id );
            
            // Inherit location from supervisor if SM
            if ( user_can( $user_id, 'sales_manager' ) && $supervisor_id ) {
                $sup_state = get_user_meta( $supervisor_id, 'state', true );
                $sup_city = get_user_meta( $supervisor_id, 'city', true );
                if ( $sup_state ) update_user_meta( $user_id, 'state', $sup_state );
                if ( $sup_city ) update_user_meta( $user_id, 'city', $sup_city );
            }
        }
        
        // Cleaner Fields Saving
        if ( isset( $_POST['cleaner_phone'] ) ) {
            update_user_meta( $user_id, 'phone', sanitize_text_field( $_POST['cleaner_phone'] ) );
        }
        if ( isset( $_POST['cleaner_aadhaar'] ) ) {
            update_user_meta( $user_id, '_aadhaar_number', sanitize_text_field( $_POST['cleaner_aadhaar'] ) );
        }
        if ( isset( $_POST['cleaner_address'] ) ) {
            update_user_meta( $user_id, '_cleaner_address', sanitize_textarea_field( $_POST['cleaner_address'] ) );
        }
        
        // Cleaner: Photo
        if ( isset( $_POST['cleaner_photo_id'] ) ) {
             update_user_meta( $user_id, '_photo_id', intval( $_POST['cleaner_photo_id'] ) );
             // Also save URL for easier access
             if ( isset( $_POST['cleaner_photo_url'] ) ) {
                 update_user_meta( $user_id, '_photo_url', esc_url_raw( $_POST['cleaner_photo_url'] ) );
             }
        }
        
        // Cleaner: Aadhaar Image
        if ( isset( $_POST['aadhaar_image_id'] ) ) {
             update_user_meta( $user_id, '_aadhaar_image_id', intval( $_POST['aadhaar_image_id'] ) );
             if ( isset( $_POST['aadhaar_image_url'] ) ) {
                 update_user_meta( $user_id, '_aadhaar_image_url', esc_url_raw( $_POST['aadhaar_image_url'] ) );
             }
        }
    }
}
