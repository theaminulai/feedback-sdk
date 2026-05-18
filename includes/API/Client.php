<?php
/**
 * API Client — sends feedback to the central server.
 *
 * @package Feedback_SDK\API
 */

namespace Feedback_SDK\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 */
class Client {

	private string $endpoint;
	private string $api_key;

	public function __construct( string $endpoint, string $api_key ) {
		$this->endpoint = $endpoint;
		$this->api_key  = $api_key;
	}

	/**
	 * Send feedback payload; retries once on transient failure.
	 *
	 * @param array $payload Data to send.
	 * @return bool True on success.
	 */
	public function send( array $payload ): bool {
		$body    = wp_json_encode( $payload );
		$headers = array(
			'Content-Type'       => 'application/json',
			'X-Feedback-API-Key' => $this->api_key,
			'X-Feedback-Sig'     => hash_hmac( 'sha256', $body, $this->api_key ),
		);

		$args = array(
			'method'      => 'POST',
			'headers'     => $headers,
			'body'        => $body,
			'timeout'     => 8,
			'blocking'    => false, // Fire-and-forget; deactivation continues immediately.
			'sslverify'   => true,
			'data_format' => 'body',
		);

		$response = wp_remote_post( $this->endpoint, $args );

		if ( is_wp_error( $response ) ) {
			// Store in retry queue (transient) for 24 h.
			$queue   = get_transient( 'feedback_sdk_retry_queue' ) ?: array();
			$queue[] = array( 'payload' => $payload, 'queued_at' => time() );
			set_transient( 'feedback_sdk_retry_queue', $queue, DAY_IN_SECONDS );
			return false;
		}

		return true;
	}

	/**
	 * Flush the retry queue (call from a cron or admin-init hook).
	 */
	public function flush_retry_queue(): void {
		$queue = get_transient( 'feedback_sdk_retry_queue' );
		if ( empty( $queue ) ) {
			return;
		}

		$remaining = array();
		foreach ( $queue as $item ) {
			// Drop entries older than 24 h.
			if ( time() - $item['queued_at'] > DAY_IN_SECONDS ) {
				continue;
			}
			if ( ! $this->send( $item['payload'] ) ) {
				$remaining[] = $item;
			}
		}

		if ( empty( $remaining ) ) {
			delete_transient( 'feedback_sdk_retry_queue' );
		} else {
			set_transient( 'feedback_sdk_retry_queue', $remaining, DAY_IN_SECONDS );
		}
	}
}
