<?php
/**
 * Plugin Name: Event Ticket Seller
 * Description: Sell event tickets with Stripe Checkout using an ACF Pro block.
 * Version: 4.3.0
 * Author: Pip
 * Text Domain: ets
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ETS_PLUGIN_FILE', __FILE__ );
define( 'ETS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ETS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ETS_OPTION_GROUP', 'ets_settings_group' );
define( 'ETS_OPTION_NAME', 'ets_settings' );


require_once ETS_PLUGIN_DIR . 'includes/helpers.php';
require_once ETS_PLUGIN_DIR . 'includes/class-ets-roles.php';
require_once ETS_PLUGIN_DIR . 'includes/class-ets-plugin.php';

register_activation_hook( ETS_PLUGIN_FILE, [ '\ETS\Roles', 'activate' ] );

ETS\Plugin::instance();
