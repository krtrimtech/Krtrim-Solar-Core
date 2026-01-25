<?php

class SP_User_Profile_Fields {

    public function __construct() {
        add_action( 'show_user_profile', [ $this, 'add_custom_user_profile_fields' ] );
        add_action( 'edit_user_profile', [ $this, 'add_custom_user_profile_fields' ] );
        add_action( 'personal_options_update', [ $this, 'save_custom_user_profile_fields' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_custom_user_profile_fields' ] );
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
            
            // Inherit location from supervisor
            if ( $supervisor_id ) {
                $sup_state = get_user_meta( $supervisor_id, 'state', true );
                $sup_city = get_user_meta( $supervisor_id, 'city', true );
                if ( $sup_state ) update_user_meta( $user_id, 'state', $sup_state );
                if ( $sup_city ) update_user_meta( $user_id, 'city', $sup_city );
            }
        }
    }
}
