<?php

namespace kangooWoo;

if( !class_exists('kangooWoo\Kangoo_API_Management') ) {

/**
 * Class to manage the API requests and responses.
 * To know if the user has valid credentials, we set the wp_option KWCSM_AUTH_TOKEN_ID to "false"
 * so if that option doesn't not exists or is "false", the user has not valid credentials
 *
 * @since  1.0.0
 */
class Kangoo_API_Management {

    /**
     * Kangoo API base URL
     * @var [string]
     */
    public $base_url;

    /**
     * Enpoint to request the price of a delivery
     */
    const EP_REQUEST__ESTIMATE_PRICE_DELIVERY = '/api/requests/estimates';

    /**
     * Enpoint to request the price of a delivery
     */
    const EP_REQUEST__MY_DEFAULT_CARD = '/api/users/me/cards/default';

    /**
     * Enpoint to request the price of a delivery
     */
    const EP_REQUEST__NEW_DELIVERY_REQUEST = '/api/requests';

    /**
     * Enpoint to request the price of a delivery
     */
    const EP_REQUEST__AUTHENTICATION = '/auth/local';

    /**
     * The base url to make the request
     */
    const BASE_URL = 'https://devapi.bykangoo.com';

    /**
     * Is the store authenticated correctly?
     *     true     If the store has verified its credentials (It's authenticated correctly)
     *     false    If the store has NOT verified its credentials yet (It is NOT authenticated)
     *
     * @var [boolean]
     */
    public $is_authenticated;

    function __construct() {
        # On init, verify if the store is authenticated correctly (has an authorization token)
        $this->is_authenticated = $this->is_store_authenticated();
    }

    /**
     * Check if the store is authenticated correctly (token properly set in wp options)
     * @since 1.0.0
     *
     * @return boolean
     *         true     If the store has a token saved (It's authenticated correctly)
     *         false    If the store has NOT a valid token (It is NOT authenticated)
     */
    public function is_store_authenticated()
    {
        # Getting the token saved in options
        $token =  get_option( KWCSM_AUTH_TOKEN_ID, false );

        # Check the if the token saved in options is correct (different from 'false' or false)
        if( $token !== 'false' || $token !== false)
            return true;

        return false;
    }

    /**
     * Authenticate the store with Kangoo service through the API using the email and password saved in the
     * Kangoo settings array (options). If succeed, update the token in Kangoo options.
     * @since  1.0.0
     *
     * @param  [string] $email     Email address to use in the authentication process
     * @param  [string] $password  Password to use in the authentication process
     *
     * @return [boolean]
     *         true  - on authentication SUCEED
     *         false - on authentication FAILED
     */
    public function authenticate_store_kangoo_service($email = false,
                                                      $password = false ){

        # Set default values to email and password if they aren't passed via parameter
        if ($email === false && $password === false) {
            $email = get_option(KWCSM_SETTINGS_OPTION_NAME)[KWCSM_SETTINGS_EMAIL_ID];

            include_once('encryption.php');
            $encrypted_password = get_option(KWCSM_SETTINGS_OPTION_NAME)[KWCSM_SETTINGS_PASSWORD_ID];
            $password = \kangooSecurity\Cryptor::Decrypt($encrypted_password, AUTH_KEY);
        }

        $complete_auth_URL = self::generate_enpoint_URL( self::EP_REQUEST__AUTHENTICATION );

        # Set args and make the POST request to the API
        $args = array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'body' => array( 'email' => $email, 'password' => $password ),
                'cookies' => array()
            );

        $response = wp_remote_post( $complete_auth_URL, $args );

        # If the response contains a wp_error or a code different that (200 - Success) then authentication failed
        if ( wp_remote_retrieve_response_code( $response ) !== 200){
            # When the user put wrong credentials we set this option to 'false'.
            $this->set_auth_failure();
            return false;
        }
        # auth success
        else{
            # get response body and transform it from JSON to php array
            $response_body_json = json_decode(wp_remote_retrieve_body( $response ));
            $auth_token = $response_body_json->token;

            update_option( KWCSM_AUTH_TOKEN_ID, $auth_token);
            $this->is_authenticated = true;

            # Updates the encrypted reference text.
            # Very usefull when the site is migrated and the user introduce its credentials again
            include_once('encryption-decryption-checker.php');
            \kangooSecurity\encryption_checker::set_encrypted_reference_text();

            return true;
        }
    }

    /**
     * Request to Kangoo API to estimates the price for a new delivery based on the package size,
     * the latitude and longitude from origin and destination points.
     * For now, the only package size is 'm'
     *
     * @since  1.0.0
     *
     * @param  [object] $lat_lng_destination
     *                  The latitude and longitude of the destination point,
     *                  when the buyer will recibe it
     *                  Ex:
     *                  {
     *                    lat => [number] "latitude"
     *                    lng => [number] "longitude"
     *                  }
     *
     * @return [object]
     *         Ex:
     *         {
     *           "estimate": 32.92,
     *           "subtotal": 27.92,
     *           "discounts": {
     *             "referrer": null,
     *             "promotions": [ 3439204932049 ],
     *             "total": 500
     *           }
     *         }
     *         You can see more detail information at https://autanastudio.github.io/Kangoo-Backend-Admin/api
     */
    public function get_delivery_estimate($lat_lng)
    {
        # Origin coordinates are the store location, previously saved in Kangoo shipping method settings page
        $kangoo_current_settings = get_option( KWCSM_SETTINGS_OPTION_NAME, array() );
        $store_lat = $kangoo_current_settings[KWCSM_SETTINGS_STORE_LAT];
        $store_lng = $kangoo_current_settings[KWCSM_SETTINGS_STORE_LNG];

        $body = array( 'package_size' => 'm', # Currently, all the request are made with 'm' as package size
                       'origin_lat' => $store_lat,
                       'origin_lng' => $store_lng,
                       'destination_lat' => $lat_lng->lat,
                       'destination_lng' => $lat_lng->lng,
                    );

        # Generate headers array with OAuth
        $headers = $this->generate_headers(true);

        $delivery_estimation = $this->send_request(self::EP_REQUEST__ESTIMATE_PRICE_DELIVERY, 'POST', $body, $headers);

        return $delivery_estimation;
    }

    /**
     * Get the default card of the current user
     * @since  1.0.0
     * @return [string] The credit card ID of the current user.
     */
    public function get_default_card()
    {
        # Generate headers array with OAuth
        $headers = $this->generate_headers(true);

        # Make the request
        $default_card = $this->send_request(self::EP_REQUEST__MY_DEFAULT_CARD, 'GET', array(), $headers);

        return $default_card->id;
    }

    /**
     * Create a delivery request of a placed order when the shop manager pulse "Request Delivery"
     * 
     * @param  [WC order Object] $order 
     *                           The order that needs to be picked up
     */
    public function create_new_delivery_request($order)
    {
        # Get store location coordinates
        $kangoo_current_settings = get_option( KWCSM_SETTINGS_OPTION_NAME, array() );
        $store_lat = $kangoo_current_settings[KWCSM_SETTINGS_STORE_LAT];
        $store_lng = $kangoo_current_settings[KWCSM_SETTINGS_STORE_LNG];

        # Get destination location info
        $client_lat = 0;
        $client_lng = 0;
        Controller::get_latlng_from_order($order->get_id(), $client_lat, $client_lng);

        $destination_address = $order->get_shipping_address_1();
        $client_name = $order->get_shipping_last_name().', '.$order->get_shipping_first_name();
        $client_phone = $order->get_billing_phone();
        $client_email = $order->get_billing_email();

        /* # Testing
        $arrayName = array(
                            'store_lat' => $store_lat,
                            'store_lng' => $store_lng,
                            'client_lat' => $client_lat,
                            'client_lng' => $client_lng,
                            'order' => $order->get_id(),
                            'destination_address' => $destination_address,
                            'client_name' => $client_name,
                            'client_phone' => $client_phone,
                            'client_email' => $client_email,
                            '$this->get_default_card()' => $this->get_default_card(),
                        );
        wp_send_json_error($arrayName);
        return;*/

        $body = array( 'delivery_type' => 'pictureDropOff',
                       'package_size' => 'm', # Currently, all the request are made with 'm' as package size
                       'origin_lat' => $store_lat,
                       'origin_lng' => $store_lng,
                       'origin_address' => Controller::get_store_location_address(),
                       'destination_lat' => $client_lat,
                       'destination_lng' => $client_lng,
                       'destination_address' => $destination_address,
                       'recipient_name' => $client_name,
                       'recipient_phone' => $client_phone,
                       'recipient_email' => $client_email,
                       'source' => $this->get_default_card()
                    );

        # Generate headers array with OAuth
        $headers = $this->generate_headers(true);

        $new_delivery_response = $this->send_request(self::EP_REQUEST__NEW_DELIVERY_REQUEST, 'POST', $body, $headers);
    }

    /**
     * Funtion to send request to Kangoo API, here we will handle the authentication
     * tokens, retryes, network errors, etc.
     *
     * @since 1.0.0
     *
     * @param  [string] $endpoint
     *                  The enpoint to make the request, in the function the base URL will be added
     *
     * @param  [string] $method
     *                  HTTP method request, [POST, GET, PUT]
     *
     * @param  [array]  $body
     *                  HTTP body to send in the request args.
     *                  Ex:
     *                  array( 'email' => $email, 'password' => $password )
     *
     * @return [object]
     *         Response body
     */
    public function send_request($endpoint, $method, $body, $headers)
    {
        $valid_auth_token = false;

        # Concat the string with the enpoint
        $complete_auth_URL = self::generate_enpoint_URL( $endpoint );

        # Set args and make the POST request to the API
        $args = array(
                'method' => $method,
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => $headers,
                'body' => json_encode($body), # Convert php array to Json object in string format
                'cookies' => array()
            );

        do {
            # Getting the token
            $token =  get_option( KWCSM_AUTH_TOKEN_ID, false );

            # At this point this shouldn't happen but we are paranoic
            if( $token == 'false' || $token == false)
                return;

            # make the request depending on the method
            switch ($method) {
                case 'POST':
                    $response = wp_remote_post( $complete_auth_URL, $args );
                break;
                case 'GET':
                    $response = wp_remote_get( $complete_auth_URL, $args );
                break;
            }

            # Refresh token
            if ( wp_remote_retrieve_response_code( $response ) == 401)
                $this->authenticate_store_kangoo_service();
            else
                $valid_auth_token = true;
            
        }while($valid_auth_token == false);

        # Return the response body transformed from Json to php Object
        return json_decode(wp_remote_retrieve_body( $response ));
    }


    /**
     * Generate the complete URL for a request
     * @since 1.0.0
     *
     * @param  [string] $enpoint
     *                  The endpoint to make the request
     *
     * @return [string]
     *         the complete url devapi
     *         Ex.
     *         https://devapi.bykangoo.com/auth/local
     */
    public static function generate_enpoint_URL($endpoint)
    {
        return self::BASE_URL . $endpoint;
    }

    /**
     * Generate the authorization token needed to consume the API on endpoints that require OAuth
     * @since 1.0.0
     *
     * @param [boolean] $required_auth
     *                  Does the request need OAuth header?
     *
     * @return [string]
     *                  The authorization token ready to make requests in Kangoo API
     */
    function generate_headers($required_auth)
    {
        /**
         * If OAuth is required and the store is authenticated correctly, then set 'authorization' with
         * the correspondent token
         */
        if ($required_auth && $this->is_authenticated) {
            # Set header to make a request with authentication
            $headers = array( 'authorization' => self::generate_authorization_token(),
                              'content-type' => 'application/json',
                        );
        }
        else{
            # Set header to make a request without authentication
            $headers = array( 'content-type' => 'application/json' );
        }

        return $headers;
    }

    /**
     * Generate the authorization token needed to consume the API on endpoints that require OAuth
     * Note: The token must be set this way: Bearer [token]
     * @since 1.0.0
     *
     * @return [string]
     *                  The authorization token ready to make requests in Kangoo API
     */
    public static function generate_authorization_token()
    {
        # Getting the token
        $token =  get_option( KWCSM_AUTH_TOKEN_ID, false );

        # Append extra word 'Bearer' and return authorization token completed
        return 'Bearer ' . $token;
    }

    /**
     * Set the toking option as false
     */
    public static function set_auth_failure(){
        update_option( KWCSM_AUTH_TOKEN_ID, 'false');
    }

}# End class

global $kangoo_api_management;
$kangoo_api_management = new Kangoo_API_Management();

}