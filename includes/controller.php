<?php

namespace kangooWoo;

if( !class_exists('controller') ) {

/**
 * Here is where the logic is coded
 * @since  1.0.0
 */
class controller {

    /**
     * Google Key needed to consume its API
     */
    const GOOGLE_KEY = 'AIzaSyB8CtnKqC_iSZ-SEF79KWOiBRNVnbEL2iI';

    function __construct() {

        add_action( 'woocommerce_shipping_init',
            array(&$this, 'kangoo_shipping_method'));

        add_filter( 'woocommerce_shipping_methods',
            array(&$this, 'add_shipping_method' ));

        add_action( 'woocommerce_review_order_before_cart_contents',
            array(&$this, 'validate_order'), 10 );

        add_filter( 'woocommerce_admin_order_actions',
            array(&$this, 'add_wooaction_kango_ready_to_pick_up'), 10 , 2 );

        add_action( 'wp_ajax_woocommerce_kangoo_notify_package_ready_to_pick_up',
            array( &$this, 'kangoo_notify_package_ready_to_pick_up' ) );

        add_action( 'admin_enqueue_scripts',
            array( &$this, 'load_kangoo_order_list_actions_customization'), 1 );

        add_action( 'woocommerce_thankyou',
            array( &$this, 'init_kangoo_notification_sent_on_new_order'), 10);

        add_action( 'manage_shop_order_posts_custom_column',
            array( &$this, 'add_kangoo_notification_sent_indicator' ), 20 );

        add_action( 'admin_enqueue_scripts',
            array( &$this, 'load_kangoo_authorization_ajax'), 1 );

        # Ajax listener, teacher crate a payment request
        add_action('wp_ajax_credentials_validation',
            array(&$this, 'ajax_validate_credentials') );

        # Enqueue the script to anable an input with google places (places library)
        add_action( 'admin_enqueue_scripts',
            array( &$this, 'google_places_integration_on_shipping_settings'), 1 );

        add_action( 'wp',
            array( &$this, 'google_places_integration_on_checkout') );

        add_action('woocommerce_checkout_update_order_meta',
                  array( &$this, 'store_latlng_on_oder_meta'), 10, 1);
    }

    /**
     * Load Kangoo Authorization JavaScript file to validate credentials using an ajax call
     * @since  1.0.0
     */
    public function load_kangoo_authorization_ajax()
    {
        global $current_screen;

        # Only when the user is viewing the woocommerce settings section
        if( !($current_screen->id === 'woocommerce_page_wc-settings' &&
            $_GET['tab'] === 'shipping' &&
            $_GET['section'] === 'kangoo_shipping_id') )
            return;

        # Script to add Kangoo image in the Kangoo notification action button
        wp_enqueue_script( 'authentication-ajax_script', # ID the the script to enqueue
           KWCSM_PLUGIN_URL.'assets/js/authentication-ajax.js', # Url
           array( 'jquery' ), # Dependencies
           '1.0', # version
           true ); # load on footer

        # Send the parameters to the script
        # Security stuff.
        $security_nonce = wp_create_nonce( 'kangoo_verification_credential_nonce' );
        wp_localize_script( 'authentication-ajax_script',
                            'security_nonce',
                            $security_nonce );

        # Send html element ID's for email and password inputs to the script
        $email_input_id = self::build_option_id_on_kangoo_shipping_settings_page(KWCSM_SETTINGS_EMAIL_ID);
        $password_input_id =  self::build_option_id_on_kangoo_shipping_settings_page(KWCSM_SETTINGS_PASSWORD_ID);
        $enabled_input_id =  self::build_option_id_on_kangoo_shipping_settings_page(KWCSM_SETTINGS_ENABLED_ID);
        $addres_input_id =  self::build_option_id_on_kangoo_shipping_settings_page(KWCSM_SETTINGS_STORE_LOCATION_ID);

        wp_localize_script( 'authentication-ajax_script',
                            'emailInputId',
                            $email_input_id );

        wp_localize_script( 'authentication-ajax_script',
                            'passwordInputId',
                            $password_input_id );

        wp_localize_script( 'authentication-ajax_script',
                            'enabledInputId',
                            $enabled_input_id);

        wp_localize_script( 'authentication-ajax_script',
                            'addressInputId',
                            $addres_input_id);

        wp_localize_script( 'authentication-ajax_script',
                            'spinnerUrl',
                            KWCSM_PLUGIN_URL.'assets/img/loading.gif' );
    }

    /**
     * Credential Validation process triggered via ajax
     * @since  1.0.0
     */
    public function ajax_validate_credentials()
    {
        # ------------------------- SECURITY VALIDATION ON -----------------------------
        /**
         * Checks if the nonce of the ajax is valid
         *
         * First parameters comes for the wp_nonce_field string
         * and the second one comes for the ajax parameter
         */
        if( !check_ajax_referer('kangoo_verification_credential_nonce', 'security_nonce') )
            return wp_send_json_error( 'Invalid Nonce'  );
        # ------------------------- SECURITY VALIDATION OFF -----------------------------
        # ------------------------ BUSINESS LOGIC ON ---------------------------------------
        #Create the request
        $email = $_POST['email'];
        $password = $_POST['password'];

        include_once('kangoo-api-management.php');
        global $kangoo_api_management;
        $pass_valid = $kangoo_api_management->authenticate_store_kangoo_service($email, $password);

        if ($pass_valid)
            wp_send_json_success('Success');
        else
            wp_send_json_error(__('Authentication failed. Check your credentials and try again.',KWCSM));

        # ------------------------ BUSINESS LOGIC OFF --------------------------------------
    }

    /**
     * Just declare the class, the instance come later
     * @since  1.0.0
     */
    public function kangoo_shipping_method() {
        require_once(dirname(__FILE__).'/kangoo-shipping-method.php');
    }

    /**
     * Add Kangoo shipping method to the current available methods
     * @since  1.0.0
     *
     * @param [array] $methods Existing methods array
     *
     * @return [array] $methods Existing methods array with the new shipping method added
     */
    public function add_shipping_method( $methods ) {
       $methods[KWCSM_SHIPPING_METHOD_ID] = 'kangooWoo\Kangoo_Shipping_Method';
        // print( '<pre>'.print_r($methods,true).'</pre><br>' );die;
        return $methods;
    }

    /**
     * Validate Order if the user chose kangoo shipping method.
     * Validate if the package needs to be more or less heavier, or just more than
     * 2 items are available. Business logic.
     * @since  1.0.0
     *
     * @param  [type] $posted [description]
     * @return [type]         [description]
     */
    public function validate_order( $posted ) { }

    /**
     * ADD in the order list a custom action to notiy to Kangoo that the package is ready to pickup
     * @since  1.0.0
     *
     * @param array $actions
     *        Array of actions with the formart:
     *        Example $actions['view'] = array(
     *                       'url'       => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
     *                       'name'      => __( 'View', 'woocommerce' ),
     *                       'action'    => "view",
     *                   );
     *
     * @param [WC_Order object] $order
     *                          Woocommerce order object
     */
    public function add_wooaction_kango_ready_to_pick_up($actions, $order){

        # If doesn't have kangoo shipping method return
        if( !$order->has_shipping_method(KWCSM_SHIPPING_METHOD_ID) )
            return $actions;

        # If the notification has already been sent return
        if(get_post_meta($order->get_id(), KWCSM_NOTIFICATION_STATUS_META_KEY, true) === 'true')
            return $actions;

        # Only show the action button with orders with these status
        switch ($order->get_status()) {
            case 'on-hold':
            case 'processing':
            case 'completed':
            case 'pending':
            break;

            default:
                return $actions;
            break;
        }

        // print( '<pre>'.print_r($order,true).'</pre><br>' );die;
        $actions['Kangoo'] = array(
            'url'  => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_kangoo_notify_package_ready_to_pick_up&order_id=' . $order->get_id() ), 'woocommerce-kangoo-notify-package-ready-to-pick-up' ),
            'name' => 'Kangoo Notification',
            'action' => 'kangoo_ready_to_pick_up'
        );

        return $actions;
    }

    /**
     * Notify Kangoo that a package is ready to pick up
     * @since  1.0.0
     */
    public function kangoo_notify_package_ready_to_pick_up()
    {
        if ( check_admin_referer( 'woocommerce-kangoo-notify-package-ready-to-pick-up' ) ) {
            $order  = wc_get_order( absint( $_GET['order_id'] ) );

            # Notify Kangoo throug the API that a new package ready to pick up
            include_once('kangoo-api-management.php');
            $kangoo_api_management->create_new_delivery_request($order);

            # Update notification status meta key to true
            update_post_meta( $order->get_id(), KWCSM_NOTIFICATION_STATUS_META_KEY, 'true' );
        }

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
        exit;
    }

    /**
     * Load the scripts and styles to customize the Kangoo notification button in the actions column from the orders list
     * and the indicator icon in the status column when the notification has been sent.
     * @since  1.0.0
     */
    public function load_kangoo_order_list_actions_customization()
    {
        # Script to add Kangoo image in the Kangoo notification action button
        wp_enqueue_script( 'new-order-list-action_script', # ID the the script to enqueue
           KWCSM_PLUGIN_URL.'assets/js/new-order-list-action.js', # Url
           array( 'jquery' ), # Dependencies
           '1.0', # version
           true ); # load on footer

        # Send the Kangoo icon path to insert a new image in the middle of the notification button
        wp_localize_script( 'new-order-list-action_script', 'KANGOO_ICON_SRC', KWCSM_PLUGIN_URL . 'assets/img/kangoo-icon.svg');

        # Styles for the Kangoo notification action button
        wp_enqueue_style( 'kangoo_order_list_styles_css', KWCSM_PLUGIN_URL.'assets/css/order-list-styles.css');
    }

    /**
     * When a new order with Kangoo shipping method selected has been placed, set a custom post meta to know when
     * the Kangoo notification has been sent.
     * @since  1.0.0
     *
     * @param  [int] $order_id  The new order id
     */
    function init_kangoo_notification_sent_on_new_order( $order_id ){
        $order = new \WC_Order( $order_id );

        # Check if the new package has Kangoo shipping method selected and create custom post meta
        if ( $order->has_shipping_method(KWCSM_SHIPPING_METHOD_ID) ) {
            update_post_meta( $order_id, KWCSM_NOTIFICATION_STATUS_META_KEY, 'false' );
        }
    }

    /**
     * When an orden that have the custom shippong method of Kangoo, was notified to
     * Kangoo that is ready to pickup, we put a custom status in the order.
     * @since  1.0.0
     *
     * @param [string] $column
     *                 The column ID to fill.
     */
    public function add_kangoo_notification_sent_indicator( $column )
    {
        # If current column is different that the status column return
        if ( $column !== 'order_status' )
            return;

        # Get current order information
        global $post, $the_order;

        if ( empty( $the_order ) || $the_order->get_id() !== $post->ID ) {
            $the_order = wc_get_order( $post->ID );
        }

        # If the notification has already been sent, indicate it in the status column
        if(get_post_meta($the_order->get_id(), KWCSM_NOTIFICATION_STATUS_META_KEY, true) === 'true')
            printf( '<img id="kangoo_notification_sent_indicator" src="'.KWCSM_PLUGIN_URL . 'assets/img/kangoo-notification-sent-indicator.svg'.'" />');
    }

    /**
     * Enqueue google places and enable address auto-complete in the Kangoo custom shipping method.
     *
     * @since  1.0.0
     */
    public function google_places_integration_on_shipping_settings()
    {
        global $current_screen;

        # Only when the user is viewing the woocommerce settings section
        if( $current_screen->id !== 'woocommerce_page_wc-settings' )
            return;

        $this->enable_input_with_googe_places(
            self::build_option_id_on_kangoo_shipping_settings_page(KWCSM_SETTINGS_STORE_LOCATION_ID),
            KWCSM_SETTINGS_STORE_LAT,
            KWCSM_SETTINGS_STORE_LNG
            );

        /**
         * Set value for latitude and longitude values in the input hidden fields in Kangoo settings page
         * by sending them via parameters to google-places-autocomplete script.
         */
        $this->set_latlng_values_on_kangoo_settings_page();
    }

    /**
     * Enqueue google places and enable address auto-complete in the checkout page.
     * This happend only if the shipping method is enabled.
     * 
     * @since  1.0.0
     */
    public function google_places_integration_on_checkout()
    {
        if ( !is_checkout() )
            return;

        # If the store manager set as UNABLE this shipping method
        # Do nothing
        include_once('kangoo-shipping-method.php');
        if ( !Kangoo_Shipping_Method::isEnabled_DB() )
            return;

        $this->enable_input_with_googe_places(
            array('billing_address_1', 'shipping_address_1'),
            KWCSM_ORDER_META_LAT,
            KWCSM_ORDER_META_LNG
            );

        # Script to validates that the shipping address is set using Google Places Auto-completion
        wp_enqueue_script( 'validate_address_on_checkout_script', # ID the the script to enqueue
            KWCSM_PLUGIN_URL.'assets/js/validate_address_on_checkout.js', # Url
            array( 'jquery', 'google-places', 'notify-library' ), # Dependencies
            '1.0', # version
            true ); # load on footer

        # Send via parameter to the script the checkbox ID that represents Kangoo Custom shipping method in the checkout page
        wp_localize_script( 'validate_address_on_checkout_script',
                            'kangooShippingCheckboxId',
                            'shipping_method_0_'.KWCSM_SHIPPING_METHOD_ID );
    }

    /**
     * When the user selects Kangoo shipping method in the checkout page, store as order meta data
     * the coordinates of the address chosen (latitude and longitude).
     *
     * @since  1.0.0
     *
     * @param  [int] $order_id
     *               The order ID
     */
    public function store_latlng_on_oder_meta($order_id)
    {
        # Gettig the order
        $order = new \WC_Order( $order_id );

        # Check if the new order has Kangoo shipping method selected
        if ( $order->has_shipping_method(KWCSM_SHIPPING_METHOD_ID) ) {
            # Update latitude and longitude as ordermeta with the order placed's address chosen
            update_post_meta( $order_id, KWCSM_ORDER_META_LAT, $_POST[KWCSM_ORDER_META_LAT] );
            update_post_meta( $order_id, KWCSM_ORDER_META_LNG, $_POST[KWCSM_ORDER_META_LNG] );
        }
    }

    /**
     * Get the latitude and longitude given and order id.
     * Two variables are needed to set the coordinates, these parameters passed 
     * by reference, so at the end of the execution the coordinates are set in those variables.
     *
     * @since  1.0.0
     * 
     * @param  [integer] $order_id 
     *                   The order ID to get the destination latitude and longitude.
     *                   
     * @param  [integer] &$lat     
     *                   A variable to set the the latitude. C++ style.
     *                   
     * @param  [integer] &$lng     
     *                   A variable to set the the longitude. C++ style.
     */
    public static function get_latlng_from_order($order_id, &$lat, &$lng){
        $lat = get_post_meta($order_id, KWCSM_ORDER_META_LAT, true);
        $lng = get_post_meta($order_id, KWCSM_ORDER_META_LNG, true);
    }

    /**
     * Get the store location address.
     *
     * @since  1.0.0
     * 
     * @return [string] The address of the store set in the shipping settings.
     */
    public static function get_store_location_address(){
        $kangoo_current_settings = get_option( KWCSM_SETTINGS_OPTION_NAME, array() );

        return $kangoo_current_settings[KWCSM_SETTINGS_STORE_LOCATION_ID];
    }


    /**
     * Enqueue the script to enable an input with google places autocompletion.
     * The script will create hidden inputs to set the lat and long
     *
     * @since 1.0.0
     *
     * @param  [string|Array] $option_ID
     *                        The option ID, the same used when was created.
     *
     * @param  [string] $lat_input_id
     *                  The id and name of the hidden input of latitude
     *
     * @param  [string] $lng_input_id
     *                  The id and name of the hidden input of longitude
     */
    protected function enable_input_with_googe_places($option_IDs, $lat_input_id, $lng_input_id)
    {
        # Convert into array
        $option_IDs = is_array($option_IDs) ? $option_IDs : array($option_IDs);

        # Add Notify.js librar to show warning message when store address has not been set correctly
        $this->enqueue_notifyjs_library();

        # Add google places library
        wp_enqueue_script( 'google-places', # ID the the script to enqueue
            ( is_ssl() ? 'https' : 'http' ) . '://maps.googleapis.com/maps/api/js?key='.self::GOOGLE_KEY.'&libraries=places', # Url
            array( 'jquery' ), # Dependencies
            '1.0', # version
            false ); # load on footer

        # Script to enable autocomplete in an input
        wp_enqueue_script( 'google-places-autocomplete_script', # ID the the script to enqueue
            KWCSM_PLUGIN_URL.'assets/js/google-places-autocomplete.js', # Url
            array( 'jquery', 'google-places', 'notify-library' ), # Dependencies
            '1.0', # version
            true ); # load on footer

        # Send via parameter to the script the input ID to set the autocompletion
        wp_localize_script( 'google-places-autocomplete_script',
                            'optionIDs',
                            $option_IDs );

        # Send via parameter to the script the input ID to set the autocompletion
        wp_localize_script( 'google-places-autocomplete_script',
                            'afterThisElementID',
                            $option_IDs[0] );

        # Send via parameter to the script the input ID to set the autocompletion
        wp_localize_script( 'google-places-autocomplete_script',
                            'latAndLngIDs',
                            array(
                                'lat' => $lat_input_id,
                                'lng' => $lng_input_id,
                            ));

        # Send via parameter to the script the error message to show when the address is NOT valid
        wp_localize_script( 'google-places-autocomplete_script',
                            'errorMessageInvalidAddress',
                            __( 'Start writing and select the address from the list', KWCSM ));
    }

    /**
     * Set value for latitude and longitude values in the input hidden fields in Kangoo settings page
     * by sending them via parameters to google-places-autocomplete script.
     * 
     * @since 1.0.0
     */
    public function set_latlng_values_on_kangoo_settings_page()
    {
        $lat_lng = array( 'lat' => '', 'lng' => '');
       
        # Search latitude and longitud values saved in Kangoo options (address in Kangoo shipping settings)
        $kangoo_current_settings = get_option( KWCSM_SETTINGS_OPTION_NAME, array() );

        if (isset($kangoo_current_settings[KWCSM_SETTINGS_STORE_LAT]) && isset($kangoo_current_settings[KWCSM_SETTINGS_STORE_LNG])) {
            $lat_lng['lat'] = $kangoo_current_settings[KWCSM_SETTINGS_STORE_LAT];
            $lat_lng['lng'] = $kangoo_current_settings[KWCSM_SETTINGS_STORE_LNG];
        }

        # Send via parameter to the script the input ID to set the autocompletion
        wp_localize_script( 'google-places-autocomplete_script',
                            'latAndLngValues',
                            $lat_lng);
    }

    /**
     * Enqueue Notify.js library to show a warning message when store address has NOT been set
     * using Google Places auto-completion service
     *
     * @since 1.0.0
     */
    public function enqueue_notifyjs_library(){
        # Enqueue Notify.js library
        wp_enqueue_script( 'notify-library', # ID the the script to enqueue
            KWCSM_PLUGIN_URL.'assets/js/lib/notifyjs/notify.min.js', # Url
            array( 'jquery' ), # Dependencies
            '1.0', # version
            false ); # load on footer
    }

    /**
     * Woocommerce builds in a strange way the IDs in the DOM of the options, this function will
     * build the ID of an option in the shipping method settings page.
     * Woocommerce build those IDs based on the shipping method id and the ID of the option
     *
     * @since 1.0.0
     *
     * @param  [string] $option_ID
     *                  The option id when was created.
     * @return [string]
     *         HTML element ID correspondent to the option ID pass via parameter
     */
    public static function build_option_id_on_kangoo_shipping_settings_page($option_ID) {
        return 'woocommerce_'.KWCSM_SHIPPING_METHOD_ID.'_'.$option_ID;
    }


}# End class

$controller = new controller();

}