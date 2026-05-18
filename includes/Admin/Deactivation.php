<?php
/**
 * Deactivation handler — renders modal, handles AJAX.
 *
 * @package Feedback_SDK\Admin
 */

namespace Feedback_SDK\Admin;

use Feedback_SDK\API\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivation
 */
class Deactivation {

	private array $config;

	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Output modal placeholder in admin footer.
	 * The actual DOM is built by modal.js using the localized config.
	 */
	public function render_modal(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		$slug = esc_js( $this->config['plugin_slug'] );
		echo '<div id="feedback-sdk-modal-' . esc_attr( $slug ) . '" class="feedback-sdk-overlay" style="display:none;" aria-hidden="true"></div>';
	}

	/**
	 * AJAX — submit feedback then return deactivation URL.
	 */
	public function handle_ajax(): void {
		$slug = $this->config['plugin_slug'];
		check_ajax_referer( "feedback_sdk_{$slug}", 'nonce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'feedback-sdk' ) ), 403 );
		}

		$reason    = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$message   = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		$competitor = sanitize_text_field( wp_unslash( $_POST['competitor'] ?? '' ) );
		$gdpr       = ! empty( $_POST['gdpr'] );

		$payload = array(
			'plugin_name'    => $this->config['plugin_name'],
			'plugin_slug'    => $slug,
			'plugin_version' => $this->config['plugin_version'],
			'reason'         => $reason,
			'message'        => $message,
			'competitor'     => $competitor,
			'site_url'       => get_site_url(),
			'admin_email'    => $gdpr ? get_option( 'admin_email' ) : '',
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'locale'         => get_locale(),
			'is_pro'         => (bool) $this->config['is_pro'],
			'timestamp'      => current_time( 'mysql' ),
		);

		$client = new Client( $this->config['api_endpoint'], $this->config['api_key'] );
		$client->send( $payload );

		wp_send_json_success(
			array(
				'deactivate_url' => $this->get_deactivate_url(),
			)
		);
	}

	/**
	 * AJAX — skip feedback, return deactivation URL immediately.
	 */
	public function handle_skip(): void {
		$slug = $this->config['plugin_slug'];
		check_ajax_referer( "feedback_sdk_{$slug}", 'nonce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'feedback-sdk' ) ), 403 );
		}

		wp_send_json_success( array( 'deactivate_url' => $this->get_deactivate_url() ) );
	}

	/** Build the actual WP deactivation URL for this plugin. */
	private function get_deactivate_url(): string {
		return wp_nonce_url(
			admin_url( 'plugins.php?action=deactivate&plugin=' . rawurlencode( $this->config['plugin_slug'] . '/' . $this->config['plugin_slug'] . '.php' ) ),
			'deactivate-plugin_' . $this->config['plugin_slug'] . '/' . $this->config['plugin_slug'] . '.php'
		);
	}
}
