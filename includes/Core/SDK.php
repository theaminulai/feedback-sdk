<?php
/**
 * SDK Core — singleton registry per plugin slug.
 *
 * @package Feedback_SDK\Core
 */

namespace Feedback_SDK\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SDK
 */
class SDK {

	/** @var array<string, self> Instances keyed by plugin_slug. */
	private static array $instances = array();

	/** @var array Plugin config. */
	private array $config = array();

	/**
	 * Initialise the SDK for a given plugin.
	 *
	 * @param array $config {
	 *     @type string $plugin_name    Human-readable plugin name.
	 *     @type string $plugin_slug    Plugin slug (e.g. elementskit-lite).
	 *     @type string $plugin_version Current version.
	 *     @type string $api_endpoint   Full REST URL of the collect endpoint.
	 *     @type string $api_key        API key for X-Feedback-API-Key header.
	 *     @type bool   $is_pro         Whether this is the Pro version.
	 * }
	 * @return self
	 */
	public static function init( array $config ): self {
		$slug = sanitize_key( $config['plugin_slug'] ?? '' );

		if ( isset( self::$instances[ $slug ] ) ) {
			return self::$instances[ $slug ];
		}

		$instance = new self();
		$instance->config = wp_parse_args(
			$config,
			array(
				'plugin_name'    => '',
				'plugin_slug'    => '',
				'plugin_version' => '1.0.0',
				'api_endpoint'   => '',
				'api_key'        => '',
				'is_pro'         => false,
				'gdpr'           => false,  // show GDPR consent checkbox.
				'debug'          => false,
			)
		);

		self::$instances[ $slug ] = $instance;

		// Wire up hooks.
		$hooks = new Hooks( $instance->config );
		$hooks->register();

		return $instance;
	}

	/** Get config value. */
	public function get( string $key, $default = null ) {
		return $this->config[ $key ] ?? $default;
	}

	/** Expose full config (read-only copy). */
	public function config(): array {
		return $this->config;
	}
}
