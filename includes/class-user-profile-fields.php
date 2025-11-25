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
        if ( ! in_array( 'area_manager', (array) $user->roles ) ) {
            return;
        }

        $current_user = wp_get_current_user();
        $can_edit = in_array( 'administrator', (array) $current_user->roles ) || in_array( 'manager', (array) $current_user->roles );
        
        $selected_state = get_user_meta( $user->ID, 'state', true );
        $selected_city  = get_user_meta( $user->ID, 'city', true );

        $locations = $this->get_location_data();
        ?>
        <h3><?php _e( 'Location Information', 'krtrim-solar-core' ); ?></h3>
        <table class="form-table">
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

    public function save_custom_user_profile_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return false;
        }

        $current_user = wp_get_current_user();
        if ( ! ( in_array( 'administrator', (array) $current_user->roles ) || in_array( 'manager', (array) $current_user->roles ) ) ) {
            return false;
        }

        if ( isset( $_POST['state'] ) ) {
            update_user_meta( $user_id, 'state', sanitize_text_field( $_POST['state'] ) );
        }

        if ( isset( $_POST['city'] ) ) {
            update_user_meta( $user_id, 'city', sanitize_text_field( $_POST['city'] ) );
        }
    }
}
