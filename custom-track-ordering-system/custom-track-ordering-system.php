<?php
/**
 * Custom Track Ordering System
 *
 * @package           CustomTrackOrderingSystem
 * @author            Your Name
 * @copyright         2023 Your Company
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Custom Track Ordering System
 * Plugin URI:        https://example.com/custom-track-ordering-system
 * Description:       A custom track ordering system for music producers.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Your Name
 * Author URI:        https://example.com
 * Text Domain:       custom-track-ordering-system
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('CTOS_VERSION', '1.0.0');
define('CTOS_PLUGIN_FILE', __FILE__);
define('CTOS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CTOS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once CTOS_PLUGIN_DIR . 'includes/class-ctos-loader.php';
require_once CTOS_PLUGIN_DIR . 'includes/class-ctos-activator.php';
require_once CTOS_PLUGIN_DIR . 'includes/class-ctos-post-types.php';
require_once CTOS_PLUGIN_DIR . 'includes/class-ctos-producer-settings.php';
require_once CTOS_PLUGIN_DIR . 'includes/class-ctos-order-form.php';
require_once CTOS_PLUGIN_DIR . 'includes/class-ctos-order-workflow.php';
require_once CTOS_PLUGIN_DIR . 'includes/class-ctos-file-handler.php';
require_once CTOS_PLUGIN_DIR . 'includes/class-ctos-notifications.php';
require_once CTOS_PLUGIN_DIR . 'includes/class-ctos-marketking-integration.php';
require_once CTOS_PLUGIN_DIR . 'includes/class-ctos-woocommerce-integration.php';
require_once CTOS_PLUGIN_DIR . 'includes/class-ctos-shortcodes.php';
require_once CTOS_PLUGIN_DIR . 'admin/class-ctos-admin.php';

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    $activator = new CTOS_Activator();
    $activator->activate();
});

/**
 * Initialize the plugin components
 */
function run_custom_track_ordering_system() {
    // Initialize the loader
    new CTOS_Loader();
    
    // Initialize other components
    new CTOS_Post_Types();
    new CTOS_Producer_Settings();
    new CTOS_Order_Form();
    new CTOS_Order_Workflow();
    new CTOS_File_Handler();
    new CTOS_Notifications();
    new CTOS_MarketKing_Integration();
    new CTOS_WooCommerce_Integration();
    
    // Initialize shortcodes
    new CTOS_Shortcodes();
    
    // Initialize admin
    if (is_admin()) {
        new CTOS_Admin();
    }
}
run_custom_track_ordering_system();
