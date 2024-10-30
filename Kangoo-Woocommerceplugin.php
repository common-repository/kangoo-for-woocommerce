<?php
/*
Plugin Name: Kangoo Shipping for Woocommerce
Description: This plugin adds a new shipping method in your WooCommerce checkout to communicate with our service
Version: 1.0.1
Author: Kangoo
Author URI: http://bykangoo.com/
Text Domain: Kangoo-Woocommerceplugin
Domain Path: /languages/
*/

# Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

KWCSM_is_this_plugin_active( 'Kangoo for WooCommerce', 'WooCommerce', 'woocommerce/woocommerce.php', 'Kangoo-Woocommerceplugin' ,'3.0.0' );

if ( !class_exists('kangooWoo\controller') ) {
    # -------------------------------------  Define Constants ON   -------------------------------------
    define( 'KWCSM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
    define( 'KWCSM_PLUGIN_DIRNAME', plugin_basename(dirname(__FILE__)));
    define( 'KWCSM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    define( 'KWCSM', 'Kangoo-Woocommerceplugin' );

    # Kangoo shipping method ID
    define( 'KWCSM_SHIPPING_METHOD_ID', 'kangoo_shipping_id' );

    # Kangoo notification status meta_key
    define( 'KWCSM_NOTIFICATION_STATUS_META_KEY', 'kangoo_notification_status' );

    # Kangoo 'authentication token' option ID
    define( 'KWCSM_AUTH_TOKEN_ID', 'kangoo_authentication_token' );

    # Kangoo settings option name
    define( 'KWCSM_SETTINGS_OPTION_NAME', 'woocommerce_'.KWCSM_SHIPPING_METHOD_ID.'_settings' );
        # Kangoo 'enabled' option ID
        define( 'KWCSM_SETTINGS_ENABLED_ID', 'kangoo_enabled' );
        # Kangoo 'email' option ID
        define( 'KWCSM_SETTINGS_EMAIL_ID', 'kangoo_email_address' );
        # Kangoo 'password' option ID
        define( 'KWCSM_SETTINGS_PASSWORD_ID', 'kangoo_password' );
        # Kangoo 'store location' option ID
        define( 'KWCSM_SETTINGS_STORE_LOCATION_ID', 'kangoo_store_location' );
        # Kangoo 'store location' latitude input ID
        define( 'KWCSM_SETTINGS_STORE_LAT', 'kangoo_store_lat' );
        # Kangoo 'store location' longitude input ID
        define( 'KWCSM_SETTINGS_STORE_LNG', 'kangoo_store_lng' );
    # Order postmeta with Kangoo Shipping method selected latitude
    define( 'KWCSM_ORDER_META_LAT', 'kangoo_order_lat' );
    # Order postmeta with Kangoo Shipping method selected longitude
    define( 'KWCSM_ORDER_META_LNG', 'kangoo_order_lng' );
    # -------------------------------------  Define Constants OFF   ------------------------------------

    /**
     * I18n
     * Load the text domain
     */
    add_action('plugins_loaded',function() {
        load_plugin_textdomain( KWCSM, false, KWCSM_PLUGIN_DIRNAME.'/languages' );
    });

    # plugin includes
    require_once(KWCSM_PLUGIN_DIR . '/includes/controller.php');
    # encryption checker
    require_once(KWCSM_PLUGIN_DIR . '/includes/encryption-decryption-checker.php');
}


#-----------------------------------------------

/**
 * Verify if a plugin is active, if not deactivate the actual plugin an show an error
 * @param  [string]  $my_plugin_name
 *                   The plugin name trying to activate. The name of this plugin
 *                   Ex:
 *                   WooCommerce new Shipping Method
 *
 * @param  [string]  $dependency_plugin_name
 *                   The dependency plugin name.
 *                   Ex:
 *                   WooCommerce.
 *
 * @param  [string]  $path_to_plugin
 *                   Path of the plugin to verify with the format 'dependency_plugin/dependency_plugin.php'
 *                   Ex:
 *                   woocommerce/woocommerce.php
 *
 * @param  [string] $textdomain
 *                  Text domain to looking the localization (the translated strings)
 *
 * @param  [string] $version_to_check
 *                  Optional, verify certain version of the dependent plugin
 */
function KWCSM_is_this_plugin_active($my_plugin_name, $dependency_plugin_name, $path_to_plugin, $textdomain = '', $version_to_check = null) {

    # Needed to the function "deactivate_plugins" works
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    if( !is_plugin_active( $path_to_plugin ) )
    {
        # Deactivate the current plugin
        deactivate_plugins( plugin_basename( __FILE__ ) );

        # Show an error alert on the admin area
        add_action( 'admin_notices', function() use($my_plugin_name, $dependency_plugin_name, $textdomain)
        {
            ?>
            <div class="updated error">
                <p>
                    <?php
                    echo sprintf(
                        __( 'The plugin <strong>"%s"</strong> needs the plugin <strong>"%s"</strong> active', $textdomain ),
                        $my_plugin_name, $dependency_plugin_name
                    );
                    echo '<br>';
                    echo sprintf(
                        __( '<strong>%s has been deactivated</strong>', $textdomain ),
                        $my_plugin_name
                    );
                    ?>
                </p>
            </div>
            <?php
            if ( isset( $_GET['activate'] ) )
                unset( $_GET['activate'] );
        } );
    }
    else {

        # If version to check is not defined do nothing
        if($version_to_check === null)
            return;

        # Get the plugin dependency info
        $depPlugin_data = get_plugin_data( WP_PLUGIN_DIR.'/'.$path_to_plugin);

        # Compare version
        $error = !version_compare ( $depPlugin_data['Version'], $version_to_check, '>=') ? true : false;

        if($error) {

            # Deactivate the current plugin
            deactivate_plugins( plugin_basename( __FILE__ ) );

            add_action( 'admin_notices', function() use($my_plugin_name, $dependency_plugin_name, $version_to_check, $textdomain)
            {
                ?>
                <div class="updated error">
                    <p>
                        <?php
                        echo sprintf(
                            __( 'The plugin <strong>"%s"</strong> needs the <strong>version %s</strong> or newer of <strong>"%s"</strong>', $textdomain ),
                            $my_plugin_name,
                            $version_to_check,
                            $dependency_plugin_name
                        );
                        echo '<br>';
                        echo sprintf(
                            __( '<strong>%s has been deactivated</strong>', $textdomain ),
                            $my_plugin_name
                        );
                        ?>
                    </p>
                </div>
                <?php
                if ( isset( $_GET['activate'] ) )
                    unset( $_GET['activate'] );
            } );
        }
    }# else
}