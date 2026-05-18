<?php
/**
 * SDK Assets — enqueue modal CSS & JS with full theming support.
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

	private array $config;

	public function __construct( array $config ) {
		$this->config = $config;
	}

	/** Enqueue assets only on the WP plugins screen. */
	public function enqueue( string $hook ): void {
		if ( 'plugins.php' !== $hook ) {
			return;
		}

		$slug    = $this->config['plugin_slug'];
		$sdk_url = plugin_dir_url( dirname( __DIR__, 1 ) . '/feedback-sdk.php' );

		// Google Fonts — allow override via config.
		$font_family = $this->config['font_family'] ?? 'DM Sans';
		$font_url    = $this->config['font_url'] ?? "https://fonts.googleapis.com/css2?family={$font_family}:wght@400;500;600&display=swap";

		if ( $font_url ) {
			wp_enqueue_style( 'feedback-sdk-font', $font_url, array(), null );
		}

		// Tabler Icons.
		wp_enqueue_style(
			'feedback-sdk-tabler',
			'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css',
			array(),
			null
		);

		// Modal base CSS.
		wp_enqueue_style(
			"feedback-sdk-modal-{$slug}",
			$sdk_url . 'assets/css/modal.css',
			array( 'feedback-sdk-tabler' ),
			'1.0.0'
		);

		// Inline theme CSS variables — override via config.
		$theme_css = $this->build_theme_css( $slug );
		if ( $theme_css ) {
			wp_add_inline_style( "feedback-sdk-modal-{$slug}", $theme_css );
		}

		// Modal JS.
		wp_enqueue_script(
			"feedback-sdk-modal-{$slug}",
			$sdk_url . 'assets/js/modal.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		// Pass full config (including theming) to JS.
		wp_localize_script(
			"feedback-sdk-modal-{$slug}",
			"feedbackSdk_{$slug}",
			array(
				'slug'        => $slug,
				'pluginName'  => $this->config['plugin_name'],
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( "feedback_sdk_{$slug}" ),
				'gdpr'        => $this->config['gdpr'] ?? false,
				'debug'       => $this->config['debug'] ?? false,
				// Branding.
				'brandIcon'   => $this->config['brand_icon'] ?? 'ti-bolt',       // Tabler icon class.
				'brandIconUrl'=> $this->config['brand_icon_url'] ?? '',          // Custom image URL (overrides icon).
				'brandName'   => $this->config['brand_name'] ?? 'Quick Feedback',
				// i18n.
				'i18n' => array(
					'title'      => sprintf(
						$this->config['modal_title'] ?? __( 'Why are you deactivating %s?', 'feedback-sdk' ),
						$this->config['plugin_name']
					),
					'submit'     => $this->config['i18n']['submit']     ?? __( 'Submit & Deactivate', 'feedback-sdk' ),
					'skip'       => $this->config['i18n']['skip']       ?? __( 'Skip & Deactivate', 'feedback-sdk' ),
					'cancel'     => $this->config['i18n']['cancel']     ?? __( 'Cancel', 'feedback-sdk' ),
					'gdpr_label' => $this->config['i18n']['gdpr_label'] ?? __( 'I agree to share this feedback anonymously.', 'feedback-sdk' ),
				),
				// Reasons — allow full override or extend.
				'reasons' => $this->get_reasons(),
			)
		);
	}

	/**
	 * Build inline CSS for custom theme variables.
	 * SDK consumers can override any design token via config.
	 *
	 * Supported config keys:
	 *   primary_color       — main accent (default #9b59e8)
	 *   primary_gradient    — gradient for submit btn (default 'linear-gradient(135deg,#c94cbf,#7b6ef6)')
	 *   bg_overlay          — overlay backdrop color
	 *   modal_bg            — modal background
	 *   modal_radius        — modal border radius
	 *   option_bg           — unselected option background
	 *   option_active_bg    — selected option background
	 *   option_active_border— selected option border color
	 *   font_family         — modal font family
	 *   font_size_base      — base font size (px)
	 *   text_primary        — primary text color
	 *   text_muted          — muted/placeholder text color
	 *   border_color        — default border color
	 *
	 * @param string $slug Plugin slug (CSS selector scope).
	 * @return string Inline CSS.
	 */
	private function build_theme_css( string $slug ): string {
		$c = $this->config;

		$primary    = sanitize_hex_color( $c['primary_color'] ?? '' ) ?: '#9b59e8';
		$gradient   = $c['primary_gradient'] ?? 'linear-gradient(135deg,#c94cbf,#7b6ef6)';
		$font       = $c['font_family'] ?? 'DM Sans';
		$font_size  = isset( $c['font_size_base'] ) ? absint( $c['font_size_base'] ) . 'px' : '13.5px';
		$modal_bg   = sanitize_hex_color( $c['modal_bg'] ?? '' ) ?: '#ffffff';
		$modal_radius = isset( $c['modal_radius'] ) ? absint( $c['modal_radius'] ) . 'px' : '20px';
		$overlay_bg = $c['bg_overlay'] ?? 'rgba(15,15,30,.55)';
		$opt_bg     = sanitize_hex_color( $c['option_bg'] ?? '' ) ?: '#faf8ff';
		$opt_a_bg   = sanitize_hex_color( $c['option_active_bg'] ?? '' ) ?: '#f9f4ff';
		$opt_a_bdr  = sanitize_hex_color( $c['option_active_border'] ?? '' ) ?: $primary;
		$text       = sanitize_hex_color( $c['text_primary'] ?? '' ) ?: '#1a1a2e';
		$muted      = sanitize_hex_color( $c['text_muted'] ?? '' ) ?: '#9ca3af';
		$border     = sanitize_hex_color( $c['border_color'] ?? '' ) ?: '#ede8f5';

		return "
#feedback-sdk-modal-{$slug} {
	--fbk-primary:       {$primary};
	--fbk-gradient:      {$gradient};
	--fbk-overlay:       {$overlay_bg};
	--fbk-modal-bg:      {$modal_bg};
	--fbk-modal-radius:  {$modal_radius};
	--fbk-opt-bg:        {$opt_bg};
	--fbk-opt-active-bg: {$opt_a_bg};
	--fbk-opt-active-bdr:{$opt_a_bdr};
	--fbk-text:          {$text};
	--fbk-muted:         {$muted};
	--fbk-border:        {$border};
	--fbk-font:          '{$font}', -apple-system, BlinkMacSystemFont, sans-serif;
	--fbk-font-size:     {$font_size};
}";
	}

	/**
	 * Build reasons array — can be fully overridden or extended via config.
	 *
	 * @return array
	 */
	private function get_reasons(): array {
		$defaults = array(
			array( 'key'=>'no-longer-needed','icon'=>'ti-plug-off',     'label'=> __('I no longer need the plugin','feedback-sdk'),     'ph'=>__('What did you use it for?','feedback-sdk') ),
			array( 'key'=>'found-better',    'icon'=>'ti-star',         'label'=> __('I found a better plugin','feedback-sdk'),          'ph'=>__('Which plugin?','feedback-sdk') ),
			array( 'key'=>'not-working',     'icon'=>'ti-tool',         'label'=> __("I couldn't get the plugin to work",'feedback-sdk'),'ph'=>__('What issue did you face?','feedback-sdk') ),
			array( 'key'=>'temp-disabled',   'icon'=>'ti-clock-pause',  'label'=> __("It's temporarily disabled",'feedback-sdk'),       'ph'=>__('When do you plan to reactivate?','feedback-sdk') ),
			array( 'key'=>'missing-feature', 'icon'=>'ti-puzzle',       'label'=> __('Missing feature','feedback-sdk'),                  'ph'=>__('Which feature?','feedback-sdk') ),
			array( 'key'=>'too-expensive',   'icon'=>'ti-currency-dollar','label'=>__('Too expensive','feedback-sdk'),                   'ph'=>__('What price would work for you?','feedback-sdk') ),
			array( 'key'=>'bug-issue',       'icon'=>'ti-bug',          'label'=> __('Bug or issue','feedback-sdk'),                     'ph'=>__('Please describe the issue','feedback-sdk') ),
			array( 'key'=>'other',           'icon'=>'ti-message-dots', 'label'=> __('Other','feedback-sdk'),                            'ph'=>__('Please share your thoughts','feedback-sdk') ),
		);

		// Full override.
		if ( ! empty( $this->config['reasons'] ) && is_array( $this->config['reasons'] ) ) {
			return $this->config['reasons'];
		}

		// Append extra reasons.
		if ( ! empty( $this->config['extra_reasons'] ) && is_array( $this->config['extra_reasons'] ) ) {
			return array_merge( $defaults, $this->config['extra_reasons'] );
		}

		// Remove specific reasons by key.
		if ( ! empty( $this->config['hide_reasons'] ) && is_array( $this->config['hide_reasons'] ) ) {
			$defaults = array_filter( $defaults, fn( $r ) => ! in_array( $r['key'], $this->config['hide_reasons'], true ) );
		}

		return array_values( $defaults );
	}
}
