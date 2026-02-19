<?php
/**
 * Plugin Name: KURSO for WordPress
 * Plugin URI:  https://www.kurso.de
 * Description: Zeigt Kursdaten aus dem KURSO Kursverwaltungssystem via GraphQL auf WordPress-Seiten an.
 * Version:     0.0.1
 * Author:      KURSO
 * Author URI:  https://www.kurso.de
 * License:     GPL-2.0+
 * Text Domain: kurso-for-wordpress
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KURSO_VERSION', '0.0.1' );
define( 'KURSO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KURSO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KURSO_PLUGIN_FILE', __FILE__ );

// Autoloader für Twig (vendor/autoload.php)
$autoload = KURSO_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
}

require_once KURSO_PLUGIN_DIR . 'includes/class-kurso-settings.php';
require_once KURSO_PLUGIN_DIR . 'includes/class-kurso-graphql.php';
require_once KURSO_PLUGIN_DIR . 'includes/class-kurso-cron.php';
require_once KURSO_PLUGIN_DIR . 'includes/class-kurso-renderer.php';
require_once KURSO_PLUGIN_DIR . 'includes/class-kurso-shortcode.php';
require_once KURSO_PLUGIN_DIR . 'includes/class-kurso-block.php';
require_once KURSO_PLUGIN_DIR . 'admin/class-kurso-admin.php';

function kurso_init() {
    Kurso_Settings::instance();
    Kurso_Cron::instance();
    Kurso_Shortcode::instance();
    Kurso_Block::instance();

    if ( is_admin() ) {
        Kurso_Admin::instance();
    }
}
add_action( 'plugins_loaded', 'kurso_init' );

register_activation_hook( __FILE__, 'kurso_activate' );
function kurso_activate() {
    Kurso_Cron::schedule_all();
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'kurso_deactivate' );
function kurso_deactivate() {
    Kurso_Cron::unschedule_all();
    flush_rewrite_rules();
}
