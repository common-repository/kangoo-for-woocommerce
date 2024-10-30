<?php

namespace kangooWoo;

if ( ! class_exists( 'kangooWoo\Kangoo_Shipping_Method' ) ) {

    /**
     * @since  1.0.0
     */
    class Kangoo_Shipping_Method extends \WC_Shipping_Method {

        /**
         * Authentication token associated with the store credentials (user and password)
         * @since  1.0.0
         *
         * @var [string]
         */
        private $token;

        /**
         * Store location coordinates (latitude and longitude) set in Kangoo settings page
         * @since  1.0.0
         *
         * @var [array]
         *      Ex.
         *      (
         *          'lat' => 26.011569,
         *          'lng' => -80.142910,
         *      )
         */
        private $store_location;

        /**
         * Constructor for your shipping class
         * @since  1.0.0
         *
         * @param int $instance_id   New instance approach to work with custom shipping methods
         *
         * @access public
         * @return void
         */
        public function __construct( $instance_id = 0 ) {
            $this->id                 = KWCSM_SHIPPING_METHOD_ID;
            $this->method_title       = __( 'Kangoo Shipping', KWCSM );
            $this->method_description = __( 'Custom Shipping Method for Kangoo', KWCSM );

            $this->instance_id = absint( $instance_id );

            /**
             * Features this method supports. Possible features used by core:
             * - 'shipping-zones'           Shipping zone functionality + instances
             * - 'instance-settings'        Instance settings screens.
             * - 'settings'                 Non-instance settings screens. Enabled by default for BW compatibility with methods before instances existed.
             * - 'instance-settings-modal'  Allows the instance settings to be loaded within a modal in the zones UI.
             * @var array
             */
            $this->supports = array(
                        // 'shipping-zones',
                        // 'instance-settings',
                        'settings',
                        // 'instance-settings-modal'
                );

            $this->init();

            $this->enabled = isset( $this->settings[KWCSM_SETTINGS_ENABLED_ID] ) ? $this->settings[KWCSM_SETTINGS_ENABLED_ID] : 'yes';
            $this->title = __( 'Same Day Delivery by Kangoo', KWCSM );

            # Set token with the option saven in wp options
            $this->token = get_option( KWCSM_AUTH_TOKEN_ID, false );

            # Set store location coordinates with the options saven in Kangoo settings
            $kangoo_current_settings = get_option( KWCSM_SETTINGS_OPTION_NAME, array() );
            $this->store_location = array(
                                'lat' => isset($kangoo_current_settings[KWCSM_SETTINGS_STORE_LAT]) ? $kangoo_current_settings[KWCSM_SETTINGS_STORE_LAT]: null,
                                'lng' => isset($kangoo_current_settings[KWCSM_SETTINGS_STORE_LNG]) ? $kangoo_current_settings[KWCSM_SETTINGS_STORE_LNG]: null
                            );
        }

        /**
         * Verify is this shipping method is enabled or not
         * @return boolean 
         *         True  - if it is
         *         False - if it is NOT
         */
        public function isEnabled()
        {
            return $this->enabled == 'yes' ? true:false;
        }

        /**
         * Verify is this shipping method is enabled or not. Checking this time
         * consulting to the DB
         * @return boolean 
         *         True  - if it is
         *         False - if it is NOT
         */
        public static function isEnabled_DB()
        {   
            # Getting the options
            $kangoo_current_settings = get_option( KWCSM_SETTINGS_OPTION_NAME, array() );
            $enabled = $kangoo_current_settings[KWCSM_SETTINGS_ENABLED_ID];

            return $enabled == 'yes' ? true:false;
        }

        /**
         * Init your settings
         * @since  1.0.0
         *
         * @access public
         * @return void
         */
        function init() {
            // Load the settings API
            $this->init_form_fields();
            $this->init_settings();

            // Save settings in admin if you have any defined
            add_action( 'woocommerce_update_options_shipping_' . $this->id,
                array( &$this, 'save_kangoo_settings' ) );
        }

        /**
         * Define settings field for this shipping
         * @since  1.0.0
         *
         * @return void
         */
        function init_form_fields() {

            $this->form_fields = array(
                KWCSM_SETTINGS_ENABLED_ID => array(
                    'title' => __( 'Enable', KWCSM ),
                    'type' => 'checkbox',
                    'description' => __( 'Enable this shipping.', KWCSM ),
                    'default' => 'yes'
                ),
                KWCSM_SETTINGS_EMAIL_ID => array(
                    'title' => __( 'Kangoo email account', KWCSM ),
                    'type' => 'text',
                    'description' => __( "Kangoo email address", KWCSM ),
                ),
                KWCSM_SETTINGS_PASSWORD_ID => array(
                    'title' => __( 'Kangoo password account', KWCSM ),
                    'type' => 'password',
                    'description' => __( "Kangoo password", KWCSM ),
                ),
                KWCSM_SETTINGS_STORE_LOCATION_ID => array(
                    'title' => __( "Enter your store's location", KWCSM ),
                    'type' => 'text',
                    'description' => __( "Store Location", KWCSM ),
                ),
            );

        }

        /**
         * This method was overwritten to show the password without encryption in Kangoo settings section
         *
         * Generate Text Input HTML.
         * @since  1.0.0
         *
         * @override
         * @param  mixed $key
         * @param  mixed $data
         * @return string
         */
        public function generate_text_html( $key, $data ) {

            $field_key = $this->get_field_key( $key );
            $defaults  = array(
                'title'             => '',
                'disabled'          => false,
                'class'             => '',
                'css'               => '',
                'placeholder'       => '',
                'type'              => 'text',
                'desc_tip'          => false,
                'description'       => '',
                'custom_attributes' => array(),
            );

            $data = wp_parse_args( $data, $defaults );

            /**
             * Default input value
             * @var [string]
             */
            $input_value = esc_attr( $this->get_option( $key ));
            # If the input to generate correspond to the password, decrypt it to fill the input
            if ($key === KWCSM_SETTINGS_PASSWORD_ID ) {
                # Do no decrypt if the string is empty
                if($input_value !== '') {    
                    include_once('encryption.php');
                    # AUTH_KEY is unique on every WordPress installation, is used to encrypt the cookies en the client browsers
                    $input_value = \kangooSecurity\Cryptor::Decrypt($input_value, AUTH_KEY);
                }
            }

            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <?php echo $this->get_tooltip_html( $data ); ?>
                    <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                        <input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="<?php echo esc_attr( $data['type'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo $input_value; ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); ?> />
                        <?php echo $this->get_description_html( $data ); ?>
                    </fieldset>
                </td>
            </tr>
            <?php

            return ob_get_clean();
        }

        /**
         * Save Kangoo setting in wordpress options
         * @since  1.0.0
         */
        function save_kangoo_settings()
        {
            # Obtain Kangoo Shipping Settings from $_POST (enabled and email) and always save them in the options
            $enabled = isset( $_POST[ controller::build_option_id_on_kangoo_shipping_settings_page(KWCSM_SETTINGS_ENABLED_ID)] ) ?
                            'yes' : 'no';
            $email = $_POST[ controller::build_option_id_on_kangoo_shipping_settings_page(KWCSM_SETTINGS_EMAIL_ID)];
            $password = $_POST[ controller::build_option_id_on_kangoo_shipping_settings_page(KWCSM_SETTINGS_PASSWORD_ID)];
            $store_location = $_POST[ controller::build_option_id_on_kangoo_shipping_settings_page(KWCSM_SETTINGS_STORE_LOCATION_ID)];
            $store_lat = $_POST[ KWCSM_SETTINGS_STORE_LAT ];
            $store_lng = $_POST[ KWCSM_SETTINGS_STORE_LNG ];


            # Get Kangoo options array to update 'enabled' and 'email' properties
            $kangoo_current_settings = get_option( KWCSM_SETTINGS_OPTION_NAME, array() );
            $kangoo_current_settings[KWCSM_SETTINGS_ENABLED_ID] = $enabled;
            $kangoo_current_settings[KWCSM_SETTINGS_EMAIL_ID] = $email;
            $kangoo_current_settings[KWCSM_SETTINGS_STORE_LOCATION_ID] = $store_location;
            $kangoo_current_settings[KWCSM_SETTINGS_STORE_LAT] = $store_lat;
            $kangoo_current_settings[KWCSM_SETTINGS_STORE_LNG] = $store_lng;

            # If the password if valid, store it.
            if ( $this->validate_credentials($email, $password) ) {
                # Encrypt the password
                include_once('encryption.php');

                # AUTH_KEY is unique on every WordPress installation, is used to encrypt the cookies en the client browsers
                $password = \kangooSecurity\Cryptor::Encrypt($password, AUTH_KEY);

                $kangoo_current_settings[KWCSM_SETTINGS_PASSWORD_ID] = $password;
            }
            else {
                unset( $kangoo_current_settings[KWCSM_SETTINGS_PASSWORD_ID] );
            }

            update_option( KWCSM_SETTINGS_OPTION_NAME ,$kangoo_current_settings);
        }

        /**
         * Validate if the user and password is valid and show admin notice.
         * @since  1.0.0
         *
         * @param  [string] $email     Email address to use in the authentication process
         * @param  [string] $password  Password to use in the authentication process
         *
         * @return [boolean]
         *         true  - on authentication SUCEED
         *         false - on authentication FAILED
         */
        protected function validate_credentials( $email, $password )
        {
            include_once('kangoo-api-management.php');
            global $kangoo_api_management;
            $pass_valid = $kangoo_api_management->authenticate_store_kangoo_service($email, $password);

            return $pass_valid;
        }

        /**
         * This function is used to calculate the shipping cost. Within this function we can check for weights, dimensions and other parameters.
         * @since  1.0.0
         *
         * @access public
         * @param mixed $package
         * @return void
         */
        public function calculate_shipping( $package=array() ) {

            $destination_lat_lng = $this->transform_address_to_cordinates($package["destination"]["address"]);

            # Calculate the delivery estimation using Kangoo API
            include_once('kangoo-api-management.php');
            $delivery_estimation = $kangoo_api_management->get_delivery_estimate($destination_lat_lng);

            # Register the rate
            $rate = array(
                'id' => $this->id,
                'label' => $this->title,
                'cost' => $delivery_estimation->subtotal
            );

            $this->add_rate( $rate );

        } # function calculate_shipping

         /**
         * Make a request to google places API to transform address string to cordinates
         *
         * @since 1.0.0
         *
         * @param  [string] $address
         *                  The address to transform
         *
         * @return [object] A simple object with the coordinates
         *         {
         *             lat => [number] "latitude"
         *             lng => [number] "longitude"
         *         }
         */
        protected function transform_address_to_cordinates($address)
        {
            $retrys = 5;
            $inten = 0;

            do
            {
                $args = array(
                    'headers' => array( "Content-type" => "application/json" )
                );

                // $address = 'Miami Gardens Drive, Opa-locka, FL, United States';
                $query = '?address='.$address.'&key='.controller::GOOGLE_KEY;
                $response = wp_remote_retrieve_body( wp_remote_get('https://maps.googleapis.com/maps/api/geocode/json'.$query), $args );
                $response = json_decode($response);

                $inten++;

            }while( $response==NULL && $inten <= $retrys );

            return $response->results[0]->geometry->location;
        }

        /**
         * Is Kangoo Shipping method available for an order based on its business rules?
         *
         * Check if the store info introduced in settings is valid (user, password, store location)
         * Check if the destination zip code matches with the Kangoo zip codes available
         * @since  1.0.0
         *
         *
         * @param array $package  Package of the current order
         * @return bool
         */
        public function is_available( $package = array() ){

            # If not enable is not available
            if(!$this->isEnabled())
                return false;

            # Check if the token is NOT valid or does NOT exist
            if( $this->token == 'false' || $this->token == false)
                return false;

            # Check that the location store has been set correctly
            if ((!$this->store_location['lat'] || $this->store_location['lat'] === '') ||
                (!$this->store_location['lng'] && $this->store_location['lng'] === ''))
                return false;

            # Initialize the $available_zip_codes array in order to prevent php notices and warnings
            $available_zip_codes = [];

            # Include the Kangoo available zip_codes on the variable $available_zip_codes
            # Is a simple array of integers
            include_once( 'zipcodes.php' );

            $destination_zip_code = $package["destination"]["postcode"];

            if (in_array($destination_zip_code, $available_zip_codes))
                return true;
            else
                return false;

        } # function is_available

        /**
         * Get the settings page of the custom shipping method
         *
         * @since  1.0.0
         */
        public static function get_settings_page() {
            return admin_url('admin.php?page=wc-settings&tab=shipping&section='.KWCSM_SHIPPING_METHOD_ID);
        }

    } # class Kangoo_Shipping_Method
} # if !class_exists
