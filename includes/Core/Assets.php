<?php
/**
 * SDK Assets — enqueue CSS and JS with correct URL resolution.
 *
 * URL FIX: plugin_dir_url() is called on FEEDBACK_SDK_FILE (the entry
 * feedback-sdk.php) rather than on __FILE__ or __DIR__. This means the
 * URL always points to the SDK root, whether the package lives at:
 *
 *   wp-content/plugins/myplugin/feedback-sdk/          (manual)
 *   wp-content/plugins/myplugin/vendor/theaminulai/feedback-sdk/  (Composer)
 *
 * @package Feedback_SDK\Core
 */

namespace Feedback_SDK\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 */
class Assets {

	/** @var array<string,mixed> Plugin config. */
	private array $config;

	/**
	 * @param array<string,mixed> $config SDK configuration array.
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Enqueue modal CSS and JS on the WordPress plugins screen only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( 'plugins.php' !== $hook ) {
			return;
		}

		$slug    = $this->config['plugin_slug'];
		$sdk_url = $this->sdk_url();

		$font_family = $this->config['font_family'] ?? 'DM Sans';
		$font_url    = $this->config['font_url']
			?? "https://fonts.googleapis.com/css2?family={$font_family}:wght@400;500;600&display=swap";

		if ( $font_url ) {
			wp_enqueue_style( 'feedback-sdk-font', $font_url, array(), null );
		}

		wp_enqueue_style(
			'feedback-sdk-tabler',
			'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css',
			array(),
			null
		);

		wp_enqueue_style(
			"feedback-sdk-{$slug}",
			$sdk_url . 'assets/css/modal.css',
			array( 'feedback-sdk-tabler' ),
			'1.0.0'
		);

		// Inline CSS variables (theme tokens scoped to this slug).
		$inline = $this->build_theme_css( $slug );
		if ( $inline ) {
			wp_add_inline_style( "feedback-sdk-{$slug}", $inline );
		}

		wp_enqueue_script(
			"feedback-sdk-{$slug}",
			$sdk_url . 'assets/js/modal.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			"feedback-sdk-{$slug}",
			'feedbackSdk_' . str_replace( '-', '_', $slug ),
			array(
				'slug'         => $slug,
				'pluginName'   => $this->config['plugin_name'],
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( "feedback_sdk_{$slug}" ),
				'gdpr'         => (bool) ( $this->config['gdpr'] ?? false ),
				'debug'        => (bool) ( $this->config['debug'] ?? false ),
				'showIcons'    => (bool) ( $this->config['show_icons'] ?? true ),
				'theme'        => sanitize_key( $this->config['theme'] ?? 'default' ),
				'design'       => sanitize_key( $this->config['design'] ?? 'card' ),
				'brandIcon'    => $this->config['brand_icon']     ?? 'ti-bolt',
				'brandIconUrl' => $this->config['brand_icon_url'] ?? '',
				'brandName'    => $this->config['brand_name']     ?? 'Quick Feedback',
				'i18n'         => array(
					'title'      => sprintf(
						$this->config['modal_title'] ?? __( 'Why are you deactivating %s?', 'feedback-sdk' ),
						$this->config['plugin_name']
					),
					'submit'     => $this->config['i18n']['submit']     ?? __( 'Submit & Deactivate', 'feedback-sdk' ),
					'skip'       => $this->config['i18n']['skip']       ?? __( 'Skip & Deactivate', 'feedback-sdk' ),
					'cancel'     => $this->config['i18n']['cancel']     ?? __( 'Cancel', 'feedback-sdk' ),
					'gdpr_label' => $this->config['i18n']['gdpr_label'] ?? __( 'I agree to share this feedback anonymously.', 'feedback-sdk' ),
				),
				'reasons'      => $this->get_reasons(),
			)
		);
	}

	/**
	 * Resolve the public URL of the SDK root directory.
	 *
	 * Always uses FEEDBACK_SDK_FILE (defined in feedback-sdk.php) so the URL
	 * is correct for both manual installs and Composer vendor installs.
	 *
	 * Manual:   plugins/myplugin/feedback-sdk/
	 * Composer: plugins/myplugin/vendor/theaminulai/feedback-sdk/
	 *
	 * @return string Trailing-slashed URL.
	 */
	private function sdk_url(): string {
		// plugin_dir_url() needs a file path, not a directory.
		// FEEDBACK_SDK_FILE is the absolute path to feedback-sdk.php.
		return plugin_dir_url( FEEDBACK_SDK_FILE );
	}

	/**
	 * Build scoped CSS custom-property overrides for this slug.
	 *
	 * The returned string is injected as an inline <style> after modal.css.
	 * Every design token in modal.css is driven by a CSS variable so even
	 * a single changed value propagates everywhere correctly.
	 *
	 * @param string $slug Plugin slug (used as CSS scope selector).
	 * @return string Inline CSS string.
	 */
	private function build_theme_css( string $slug ): string {
		$c = $this->config;

		// Resolve theme preset first, then allow individual overrides on top.
		$preset  = Themes::get( $c['theme'] ?? 'default' );

		$primary  = sanitize_hex_color( $c['primary_color']   ?? '' ) ?: $preset['primary'];
		$gradient = $c['primary_gradient'] ?? $preset['gradient'];
		$font     = $c['font_family']      ?? $preset['font'];
		$fs       = isset( $c['font_size_base'] ) ? absint( $c['font_size_base'] ) . 'px' : $preset['font_size'];
		$mbg      = sanitize_hex_color( $c['modal_bg']               ?? '' ) ?: $preset['modal_bg'];
		$mrad     = isset( $c['modal_radius'] ) ? absint( $c['modal_radius'] ) . 'px' : $preset['modal_radius'];
		$overlay  = $c['bg_overlay']            ?? $preset['overlay'];
		$optbg    = sanitize_hex_color( $c['option_bg']              ?? '' ) ?: $preset['opt_bg'];
		$optabg   = sanitize_hex_color( $c['option_active_bg']       ?? '' ) ?: $preset['opt_active_bg'];
		$optabdr  = sanitize_hex_color( $c['option_active_border']   ?? '' ) ?: $primary;
		$text     = sanitize_hex_color( $c['text_primary']           ?? '' ) ?: $preset['text'];
		$muted    = sanitize_hex_color( $c['text_muted']             ?? '' ) ?: $preset['muted'];
		$border   = sanitize_hex_color( $c['border_color']           ?? '' ) ?: $preset['border'];
		$hdbg     = sanitize_hex_color( $c['header_bg']              ?? '' ) ?: $preset['header_bg'];
		$footbg   = sanitize_hex_color( $c['footer_bg']              ?? '' ) ?: $preset['footer_bg'];
		$shadow   = $c['modal_shadow']          ?? $preset['shadow'];

		return "
		#feedback-sdk-modal-{$slug} {
			--fbk-primary:       {$primary};
			--fbk-gradient:      {$gradient};
			--fbk-overlay:       {$overlay};
			--fbk-modal-bg:      {$mbg};
			--fbk-modal-radius:  {$mrad};
			--fbk-modal-shadow:  {$shadow};
			--fbk-header-bg:     {$hdbg};
			--fbk-footer-bg:     {$footbg};
			--fbk-opt-bg:        {$optbg};
			--fbk-opt-active-bg: {$optabg};
			--fbk-opt-active-bdr:{$optabdr};
			--fbk-text:          {$text};
			--fbk-muted:         {$muted};
			--fbk-border:        {$border};
			--fbk-font:          '{$font}', -apple-system, BlinkMacSystemFont, sans-serif;
			--fbk-font-size:     {$fs};
		}";
	}

	/**
	 * Build the reasons array respecting hide_reasons / extra_reasons / reasons overrides.
	 *
	 * @return array<array{key:string,icon:string,label:string,ph:string}>
	 */
	private function get_reasons(): array {
		$defaults = array(
			array( 'key' => 'no-longer-needed', 'icon' => 'ti-plug-off',      'label' => __( 'I no longer need the plugin',       'feedback-sdk' ), 'ph' => __( 'What did you use it for?',          'feedback-sdk' ) ),
			array( 'key' => 'found-better',     'icon' => 'ti-star',           'label' => __( 'I found a better plugin',            'feedback-sdk' ), 'ph' => __( 'Which plugin?',                     'feedback-sdk' ) ),
			array( 'key' => 'not-working',      'icon' => 'ti-tool',           'label' => __( "I couldn't get the plugin to work",  'feedback-sdk' ), 'ph' => __( 'What issue did you face?',          'feedback-sdk' ) ),
			array( 'key' => 'temp-disabled',    'icon' => 'ti-clock-pause',    'label' => __( "It's temporarily disabled",          'feedback-sdk' ), 'ph' => __( 'When do you plan to reactivate?',   'feedback-sdk' ) ),
			array( 'key' => 'missing-feature',  'icon' => 'ti-puzzle',         'label' => __( 'Missing feature',                   'feedback-sdk' ), 'ph' => __( 'Which feature?',                    'feedback-sdk' ) ),
			array( 'key' => 'too-expensive',    'icon' => 'ti-currency-dollar','label' => __( 'Too expensive',                     'feedback-sdk' ), 'ph' => __( 'What price would work for you?',    'feedback-sdk' ) ),
			array( 'key' => 'bug-issue',        'icon' => 'ti-bug',            'label' => __( 'Bug or issue',                      'feedback-sdk' ), 'ph' => __( 'Please describe the issue',         'feedback-sdk' ) ),
			array( 'key' => 'other',            'icon' => 'ti-message-dots',   'label' => __( 'Other',                             'feedback-sdk' ), 'ph' => __( 'Please share your thoughts',        'feedback-sdk' ) ),
		);

		// Full replacement.
		if ( ! empty( $this->config['reasons'] ) && is_array( $this->config['reasons'] ) ) {
			return array_values( $this->config['reasons'] );
		}

		// Append extras.
		if ( ! empty( $this->config['extra_reasons'] ) && is_array( $this->config['extra_reasons'] ) ) {
			return array_values( array_merge( $defaults, $this->config['extra_reasons'] ) );
		}

		// Hide specific keys.
		if ( ! empty( $this->config['hide_reasons'] ) && is_array( $this->config['hide_reasons'] ) ) {
			$defaults = array_values(
				array_filter( $defaults, fn( $r ) => ! in_array( $r['key'], $this->config['hide_reasons'], true ) )
			);
		}

		return $defaults;
	}
}
