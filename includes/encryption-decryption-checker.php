<?
/**
 * File to check if the encryption is possible.
 *
 * If the user changes its wordpress site keys or migrates its site, the 
 * decryption of the password is not possible.
 *
 * This file will check that and if not possible, let him know how to fix it 
 * using an admin alert
 */

namespace kangooSecurity;

if( !class_exists('\kangooSecurity\encryption_checker') )
{
/**
 * We set a encrypted text in the database, we already know what does it mean
 *
 * @since  1.0.0
 */
class encryption_checker
{
    const TEXT = 'I love Kangoo';

    /**
     * Option in the DB to store the encrypted reference text
     */
    const KANGOO_ENCRYPTED_TEXT_OPTION_ID = '_KSEC_encrypted_reference_text';
    
    function __construct()
    {
        # Set encrypted reference text
        register_activation_hook( KWCSM_PLUGIN_DIR.KWCSM_PLUGIN_DIRNAME.'.php', 
            array(&$this, 'set_encrypted_reference_text_if_not_exists' ) );

        add_action('init', 
            array(&$this, 'check_encryption' ));
    }

    /**
     * When the plugin is activated set only one time a encrypted text on the database
     * to check if it can be decrpyted later, if don't something happen
     *
     * @since 1.0.0
     */
    public function set_encrypted_reference_text_if_not_exists()
    {
        # If no exists will return false
        $encrypted_ref_text = $this->get_encrypted_reference_text();

        # 1st time because it doesn't exists
        if($encrypted_ref_text === false) {
            # include the funtions
            include_once('encryption.php');

            # Set the encrypted reference text
            $this->set_encrypted_reference_text();
        }
    }

    /**
     * Set the encrypted reference text
     * 
     * @since  1.0.0
     */
    public static function set_encrypted_reference_text(){
        # include the funtions
        include_once('encryption.php');

        # Set the encrypted reference text
        update_option(
            self::KANGOO_ENCRYPTED_TEXT_OPTION_ID,
            Cryptor::Encrypt(self::TEXT, AUTH_KEY) );
    }

    /**
     * Check if the encryption still valid, if the user migrates the site 
     * or changes the wordpress security keys, the encryption is not possible.
     * If it is not, an admin alert is shown.
     *
     * @since 1.0.0
     *
     * @return [boolean]
     *         - True, everything OK
     *         - False, wrong, could not decrypt
     */
    public function check_encryption()
    {
        include_once('encryption.php');

        $encrypted = $this->get_encrypted_reference_text();
        $dencrypted = Cryptor::Decrypt($encrypted, AUTH_KEY);

        # They are not equal
        if( $dencrypted !== self::TEXT ) {

            # Set the token false
            include_once('kangoo-api-management.php');
            \kangooWoo\Kangoo_API_Management::set_auth_failure();
            
            add_action( 'admin_notices',
                array(&$this, 'admin_alert' ));
        }
    }

    /**
     * Show an admin alert inicating that encryotion is broken
     * 
     * @since  1.0.0
     */
    public function admin_alert() {

        include_once('kangoo-shipping-method.php');

        $class = 'notice notice-error';
        $message = __( "Did you recently migrate your site or change your WP Site's Keys?", KWCSM ).'<br>';
        $message = $message.'<b>'.
            sprintf( 
                __( 'You must authenticate again with your Kangoo Credentials at its <a href="%1$s">settings page</a>', KWCSM ), 
                \kangooWoo\Kangoo_Shipping_Method::get_settings_page()
                )
            .'</b>';

        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message ); 
    }

    /**
     * Get the encrypted reference text
     * 
     * @since 1.0.0
     * 
     * @return [string|false] 
     *         This returns the encrypted text or false if there is no option in the database
     */
    private function get_encrypted_reference_text()
    {
        return get_option( self::KANGOO_ENCRYPTED_TEXT_OPTION_ID, false );
    }
}# End class

$encryption_checker = new encryption_checker();
}

