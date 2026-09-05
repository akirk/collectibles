<?php
/**
 * Plugin Name: Collectibles
 * Plugin URI: https://github.com/akirk/collectibles
 * Description: Catalog what you collect — coins, stamps, banknotes, cards, records, books — each kind with its own fields, grading scale and totals.
 * Version: 1.0.0+e93463349122
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * Author: Alex Kirk
 * Author URI: https://alex.kirk.at/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: collectibles
 * Domain Path: /languages
 *
 * @package Collectibles
 */

namespace Collectibles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Autoloader for plugin classes, using the WordPress class-name.php file convention.
spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'Collectibles\\';
		$len    = strlen( $prefix );

		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		$relative   = str_replace( '\\', '/', substr( $class_name, $len ) );
		$segments   = explode( '/', $relative );
		$basename   = array_pop( $segments );
		$basename   = strtolower( preg_replace( '/(?<!^)([A-Z])/', '-$1', $basename ) );
		$segments[] = 'class-' . $basename . '.php';

		$file = __DIR__ . '/src/' . implode( '/', $segments );

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'collectibles', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

add_action(
	'plugins_loaded',
	function () {
		$app = new App();
		$app->init();
	}
);

register_activation_hook(
	__FILE__,
	function () {
		$app = new App();
		$app->activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		$app = new App();
		$app->deactivate();
	}
);
