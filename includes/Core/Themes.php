<?php
/**
 * Built-in colour theme presets.
 *
 * A theme is a named collection of CSS design tokens. The active theme
 * is selected via the 'theme' config key in SDK::init(). Individual
 * tokens can always be overridden by passing their specific config key
 * (e.g. 'primary_color' overrides the theme's primary token).
 *
 * Available themes:
 *   default  — Purple gradient (original theaminulai brand)
 *   ocean    — Deep blue & teal
 *   rose     — Pink & red warm tones
 *   forest   — Green & earthy
 *   midnight — Dark/charcoal background
 *
 * @package Feedback_SDK\Core
 */

namespace Feedback_SDK\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Themes
 */
class Themes {

	/**
	 * All built-in theme token sets.
	 *
	 * Keys map directly to CSS custom properties used in modal.css.
	 *
	 * @var array<string, array<string,string>>
	 */
	private static array $themes = array(
		// DEFAULT — Purple gradient, light background
		'default' => array(
			'primary'        => '#9b59e8',
			'gradient'       => 'linear-gradient(135deg, #c94cbf, #7b6ef6)',
			'font'           => 'DM Sans',
			'font_size'      => '13.5px',
			'modal_bg'       => '#ffffff',
			'modal_radius'   => '20px',
			'overlay'        => 'rgba(15, 15, 30, 0.55)',
			'shadow'         => '0 20px 60px rgba(130,60,220,.18), 0 4px 12px rgba(0,0,0,.08)',
			'header_bg'      => '#ffffff',
			'footer_bg'      => '#ffffff',
			'opt_bg'         => '#faf8ff',
			'opt_active_bg'  => '#f9f4ff',
			'text'           => '#1a1a2e',
			'muted'          => '#9ca3af',
			'border'         => '#ede8f5',
		),

		// OCEAN — Blue & teal, crisp and professional
		'ocean' => array(
			'primary'        => '#0ea5e9',
			'gradient'       => 'linear-gradient(135deg, #0ea5e9, #0284c7)',
			'font'           => 'DM Sans',
			'font_size'      => '13.5px',
			'modal_bg'       => '#ffffff',
			'modal_radius'   => '18px',
			'overlay'        => 'rgba(8, 28, 50, 0.60)',
			'shadow'         => '0 20px 60px rgba(14,165,233,.18), 0 4px 12px rgba(0,0,0,.08)',
			'header_bg'      => '#f0f9ff',
			'footer_bg'      => '#f0f9ff',
			'opt_bg'         => '#f0f9ff',
			'opt_active_bg'  => '#e0f2fe',
			'text'           => '#0c1a2e',
			'muted'          => '#64748b',
			'border'         => '#bae6fd',
		),

		// ROSE — Warm pink & red, friendly and energetic
		'rose' => array(
			'primary'        => '#f43f5e',
			'gradient'       => 'linear-gradient(135deg, #fb7185, #e11d48)',
			'font'           => 'DM Sans',
			'font_size'      => '13.5px',
			'modal_bg'       => '#ffffff',
			'modal_radius'   => '20px',
			'overlay'        => 'rgba(40, 5, 15, 0.55)',
			'shadow'         => '0 20px 60px rgba(244,63,94,.18), 0 4px 12px rgba(0,0,0,.08)',
			'header_bg'      => '#fff1f2',
			'footer_bg'      => '#fff1f2',
			'opt_bg'         => '#fff1f2',
			'opt_active_bg'  => '#ffe4e6',
			'text'           => '#1c0a0e',
			'muted'          => '#9f6670',
			'border'         => '#fecdd3',
		),

		// FOREST — Green & earthy, calm and trustworthy
		'forest' => array(
			'primary'        => '#16a34a',
			'gradient'       => 'linear-gradient(135deg, #22c55e, #15803d)',
			'font'           => 'DM Sans',
			'font_size'      => '13.5px',
			'modal_bg'       => '#ffffff',
			'modal_radius'   => '16px',
			'overlay'        => 'rgba(5, 25, 10, 0.55)',
			'shadow'         => '0 20px 60px rgba(22,163,74,.16), 0 4px 12px rgba(0,0,0,.08)',
			'header_bg'      => '#f0fdf4',
			'footer_bg'      => '#f0fdf4',
			'opt_bg'         => '#f0fdf4',
			'opt_active_bg'  => '#dcfce7',
			'text'           => '#052e16',
			'muted'          => '#4d7c5e',
			'border'         => '#bbf7d0',
		),

		// MIDNIGHT — Dark background, modern and bold
		'midnight' => array(
			'primary'        => '#818cf8',
			'gradient'       => 'linear-gradient(135deg, #818cf8, #6366f1)',
			'font'           => 'DM Sans',
			'font_size'      => '13.5px',
			'modal_bg'       => '#1e1e2e',
			'modal_radius'   => '20px',
			'overlay'        => 'rgba(0, 0, 0, 0.75)',
			'shadow'         => '0 24px 64px rgba(0,0,0,.60)',
			'header_bg'      => '#252535',
			'footer_bg'      => '#252535',
			'opt_bg'         => '#2a2a3e',
			'opt_active_bg'  => '#32324a',
			'text'           => '#e2e8f0',
			'muted'          => '#94a3b8',
			'border'         => '#3a3a52',
		),
	);

	/**
	 * Return the token set for a named theme.
	 *
	 * Falls back to 'default' if the requested theme does not exist.
	 *
	 * @param string $name Theme name (e.g. 'ocean', 'rose').
	 * @return array<string,string> Token set.
	 */
	public static function get( string $name ): array {
		return self::$themes[ $name ] ?? self::$themes['default'];
	}

	/**
	 * Return the names of all registered themes.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		return array_keys( self::$themes );
	}
}
