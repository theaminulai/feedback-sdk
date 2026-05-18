<?php
/**
 * WordPress hooks for the SDK.
 *
 * @package Feedback_SDK\Core
 */

namespace Feedback_SDK\Core;

use Feedback_SDK\Admin\Deactivation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hooks
 */
class Hooks {

	/** @var array Plugin config. */
	private array $config;

	public function __construct( array $config ) {
		$this->config = $config;
	}

	/** Register all hooks. */
	public function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		$deact = new Deactivation( $this->config );

		add_action( 'admin_enqueue_scripts', array( new Assets( $this->config ), 'enqueue' ) );
		add_action( 'admin_footer', array( $deact, 'render_modal' ) );

		// AJAX handler — send feedback then deactivate.
		$slug = $this->config['plugin_slug'];
		add_action( "wp_ajax_feedback_sdk_submit_{$slug}", array( $deact, 'handle_ajax' ) );
		add_action( "wp_ajax_feedback_sdk_skip_{$slug}",   array( $deact, 'handle_skip' ) );
	}
}
