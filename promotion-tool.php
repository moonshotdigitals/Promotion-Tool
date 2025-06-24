<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://themoonshot.digital
 * @since             1.0.9
 * @package           Promotion_Tool
 *
 * @wordpress-plugin
 * Plugin Name:       Promotion Tool
 * Plugin URI:        https://themoonshot.digital
 * Description:       Incentive higher quantity purchases. Increasing sales.
 * Version:           1.0.9
 * Author:            Moonshot
 * Author URI:        https://themoonshot.digital
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       promotion-tool
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.9 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PROMOTION_TOOL_VERSION', '1.0.9' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-promotion-tool-activator.php
 */
function activate_promotion_tool() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-promotion-tool-activator.php';
	Promotion_Tool_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-promotion-tool-deactivator.php
 */
function deactivate_promotion_tool() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-promotion-tool-deactivator.php';
	Promotion_Tool_Deactivator::deactivate();
}

require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/moonshotdigitals/Promotion-Tool',
    __FILE__,
    'promotion-tool'
);

register_activation_hook( __FILE__, 'activate_promotion_tool' );
register_deactivation_hook( __FILE__, 'deactivate_promotion_tool' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-promotion-tool.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.9
 */
function run_promotion_tool() {

	$plugin = new Promotion_Tool();
	$plugin->run();

}
run_promotion_tool();
