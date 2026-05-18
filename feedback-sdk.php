<?php
/**
 * Plugin Name:       Feedback SDK
 * Plugin URI:        https://theaminul.com
 * Description:       Client SDK for deactivation feedback — integrates into any WordPress plugin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            theaminul
 * Text Domain:       feedback-sdk
 *
 * @package Feedback_SDK
 */

namespace Feedback_SDK;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// PSR-4 autoloader.
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'Feedback_SDK\\';
		$base_dir = __DIR__ . '/includes/';
		$len      = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class, $len ) );
		$file     = $base_dir . $relative . '.php';
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Initialise the SDK for a plugin. for expm1le:
 *
 * Usage:
 *  \Feedback_SDK\SDK::init([
 *      'plugin_name'    => 'ElementsKit',
 *      'plugin_slug'    => 'elementskit-lite',
 *      'plugin_version' => '3.5.2',
 *      'api_endpoint'   => 'https://api.wpmet.com/wp-json/feedback/v1/collect',
 *      'api_key'        => 'your-api-key',
 *      'is_pro'         => false,
 *  ]);
 */
