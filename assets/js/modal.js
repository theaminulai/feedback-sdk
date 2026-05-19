/**
 * Feedback SDK — Deactivation Modal jQuery Plugin
 *
 * Key fixes in this version:
 *  1. DEACTIVATION URL: The original href from the "Deactivate" link is
 *     stored in JS and sent back to the server as `deactivate_url`. The
 *     server echoes it straight back. This avoids the "link has expired"
 *     error caused by rebuilding the URL server-side with a new nonce.
 *
 *  2. ASSET URL: The PHP entry point (feedback-sdk.php) defines
 *     FEEDBACK_SDK_FILE so plugin_dir_url() always resolves to the SDK
 *     root regardless of whether it is installed manually or via Composer.
 *
 *  3. ICON SHOW/HIDE: Controlled by the `show_icons` config key.
 *
 *  4. THEMES: 5 colour themes applied via inline CSS custom properties
 *     (default | ocean | rose | forest | midnight).
 *
 *  5. DESIGNS: 5 layout designs applied via data-design attribute
 *     (card | flat | minimal | bold | glass).
 *
 * @file    modal.js
 * @package Feedback_SDK
 * @since   1.0.0
 *
 * @requires jQuery
 */

/* global jQuery */
( function ( $ ) {
	'use strict';

	/**
	 * jQuery plugin: $.fn.feedbackSdkModal
	 *
	 * Attaches the deactivation modal to the given overlay element.
	 *
	 * @param {object} cfg   Localized config from wp_localize_script.
	 * @returns {jQuery}
	 *
	 * @example
	 *   $( '#feedback-sdk-modal-myplugin' ).feedbackSdkModal( window.feedbackSdk_myplugin );
	 */
	$.fn.feedbackSdkModal = function ( cfg ) {
		return this.each( function () {
			var inst = new FeedbackSdkModal( $( this ), cfg );
			$( this ).data( 'feedbackSdkModal', inst );
		} );
	};

	/**
	 * Deactivation modal controller.
	 *
	 * @constructor
	 * @param {jQuery} $overlay  The empty overlay <div> injected by PHP.
	 * @param {object} cfg       Localized configuration object.
	 *
	 * cfg properties:
	 *   {string}  slug          — plugin slug (kebab-case)
	 *   {string}  pluginName    — human-readable plugin name
	 *   {string}  ajaxUrl       — wp-admin/admin-ajax.php URL
	 *   {string}  nonce         — WP nonce for feedback_sdk_{slug}
	 *   {boolean} gdpr          — show GDPR checkbox
	 *   {boolean} debug         — log to console
	 *   {boolean} showIcons     — show/hide reason icons
	 *   {string}  theme         — colour theme name (default|ocean|rose|forest|midnight)
	 *   {string}  design        — layout design (card|flat|minimal|bold|glass)
	 *   {string}  brandIcon     — Tabler icon class
	 *   {string}  brandIconUrl  — custom image URL (overrides brandIcon)
	 *   {string}  brandName     — modal header title
	 *   {object}  i18n          — translatable strings { title, submit, skip, cancel, gdpr_label }
	 *   {Array}   reasons       — array of { key, icon, label, ph }
	 */
	function FeedbackSdkModal( $overlay, cfg ) {
		/** @type {jQuery} */
		this.$overlay = $overlay;

		/** @type {object} */
		this.cfg = cfg;

		/** @type {string|null} Selected reason key. */
		this.selectedReason = null;

		/**
		 * The original href of the intercepted "Deactivate" link.
		 * Sent back to the AJAX handler as `deactivate_url` so the
		 * server never needs to rebuild the URL (fixes nonce expiry).
		 *
		 * @type {string}
		 */
		this.originalDeactivateUrl = '';

		this._init();
	}

	/**
	 * Find the plugin's "Deactivate" link on the plugins page and intercept it.
	 *
	 * @private
	 * @returns {void}
	 */
	FeedbackSdkModal.prototype._init = function () {
		var self = this;
		var slug = this.cfg.slug;

		$( document ).ready( function () {
			// WordPress renders deactivate links as:
			// <a href="plugins.php?action=deactivate&plugin=slug/slug.php&_wpnonce=...">
			var $link = $( 'a[href*="action=deactivate"]' ).filter( function () {
				var href = $( this ).attr( 'href' ) || '';
				return (
					href.indexOf( encodeURIComponent( slug ) ) !== -1 ||
					href.indexOf( slug ) !== -1
				);
			} ).first();

			if ( ! $link.length ) {
				if ( self.cfg.debug ) {
					window.console.warn( '[FeedbackSDK] Deactivate link not found for slug:', slug );
				}
				return;
			}

			$link.on( 'click.feedbackSdk', function ( e ) {
				e.preventDefault();
				// Store the ORIGINAL href — nonce included.
				self.originalDeactivateUrl = $( this ).attr( 'href' ) || '';
				self._open();
			} );

			if ( self.cfg.debug ) {
				window.console.log( '[FeedbackSDK] Intercepting deactivate for:', slug );
			}
		} );
	};

	/**
	 * Build the modal HTML, inject it into the overlay, and animate it open.
	 *
	 * @returns {void}
	 */
	FeedbackSdkModal.prototype._open = function () {
		this.selectedReason = null;
		this.$overlay.html( this._buildHtml() ).show();

		var self = this;
		requestAnimationFrame( function () {
			self.$overlay.addClass( 'fbk-sdk-visible' );
		} );

		this._bindEvents();
		$( 'body' ).css( 'overflow', 'hidden' );
	};

	/**
	 * Animate the modal out and clean up.
	 *
	 * @returns {void}
	 */
	FeedbackSdkModal.prototype._close = function () {
		var self = this;
		this.$overlay.removeClass( 'fbk-sdk-visible' );
		setTimeout( function () {
			self.$overlay.hide().empty();
		}, 240 );
		$( 'body' ).css( 'overflow', '' );
		$( document ).off( 'keydown.feedbackSdk_' + this.cfg.slug );
	};

	/**
	 * Wire up all interactions inside the rendered modal.
	 *
	 * @private
	 * @returns {void}
	 */
	FeedbackSdkModal.prototype._bindEvents = function () {
		var self = this;

		// Reason selection — expand textarea, mark active.
		this.$overlay.on( 'click', '.fbk-option-row', function () {
			var $opt = $( this ).closest( '.fbk-option' );
			self.selectedReason = $opt.data( 'reason' );

			self.$overlay.find( '.fbk-option' )
				.removeClass( 'fbk-active' )
				.find( '.fbk-textarea-wrap' )
				.removeClass( 'fbk-show' );

			$opt.addClass( 'fbk-active' )
				.find( '.fbk-textarea-wrap' )
				.addClass( 'fbk-show' );

			setTimeout( function () {
				$opt.find( '.fbk-option-ta' ).trigger( 'focus' );
			}, 320 );
		} );

		// Cancel / X button.
		this.$overlay.on( 'click', '.fbk-js-cancel, .fbk-modal-close', function () {
			self._close();
		} );

		// Click on backdrop.
		this.$overlay.on( 'click', function ( e ) {
			if ( $( e.target ).is( self.$overlay ) ) {
				self._close();
			}
		} );

		// ESC key.
		$( document ).on( 'keydown.feedbackSdk_' + this.cfg.slug, function ( e ) {
			if ( 27 === e.which ) {
				self._close();
			}
		} );

		// Skip button — proceed without submitting feedback.
		this.$overlay.on( 'click', '.fbk-js-skip', function () {
			self._doAjax( 'skip', null, null, false );
		} );

		// Submit button.
		this.$overlay.on( 'click', '.fbk-js-submit', function () {
			if ( ! self.selectedReason ) {
				self.$overlay.find( '.fbk-options' ).addClass( 'fbk-shake' );
				setTimeout( function () {
					self.$overlay.find( '.fbk-options' ).removeClass( 'fbk-shake' );
				}, 500 );
				return;
			}

			var $active  = self.$overlay.find( '.fbk-option.fbk-active' );
			var message  = $active.find( '.fbk-option-ta' ).val() || '';
			var gdprOk   = ! self.cfg.gdpr || self.$overlay.find( '.fbk-gdpr-check' ).is( ':checked' );

			self._doAjax( 'submit', self.selectedReason, message, gdprOk );
		} );
	};

	/**
	 * Send feedback to the server (or skip) and redirect to the deactivation URL.
	 *
	 * The original deactivation URL is sent as `deactivate_url` in the POST body
	 * so the server can echo it back. The nonce in that URL was generated by
	 * WordPress for the exact plugin file path — we never touch it.
	 *
	 * On any error (network failure, etc.) the browser is redirected to the
	 * stored originalDeactivateUrl so the user is never stuck.
	 *
	 * @param {string}      action    'submit' or 'skip'.
	 * @param {string|null} reason    Selected reason key.
	 * @param {string|null} message   User's typed message.
	 * @param {boolean}     gdprOk    Whether GDPR consent was given.
	 * @returns {void}
	 */
	FeedbackSdkModal.prototype._doAjax = function ( action, reason, message, gdprOk ) {
		var self       = this;
		var slug       = this.cfg.slug;
		var $btn       = this.$overlay.find( '.fbk-js-submit' );
		var ajaxAction = 'submit' === action
			? 'feedback_sdk_submit_' + slug
			: 'feedback_sdk_skip_'   + slug;

		// Disable submit button while in flight.
		$btn.prop( 'disabled', true )
			.find( 'i.fbk-btn-icon' )
			.removeClass( 'ti-send' )
			.addClass( 'ti-loader-2' );

		$.post(
			self.cfg.ajaxUrl,
			{
				action:         ajaxAction,
				nonce:          self.cfg.nonce,
				reason:         reason  || '',
				message:        message || '',
				competitor:     '',
				gdpr:           gdprOk ? 1 : 0,
				deactivate_url: self.originalDeactivateUrl,  // ← original URL with valid nonce
			}
		).always( function ( res ) {
			var url = ( res && res.success && res.data && res.data.deactivate_url )
				? res.data.deactivate_url
				: self.originalDeactivateUrl;

			if ( self.cfg.debug ) {
				window.console.log( '[FeedbackSDK] Redirecting to:', url );
			}

			if ( url ) {
				window.location.href = url;
			} else {
				self._close();
			}
		} );
	};

	/**
	 * Build the complete modal HTML string.
	 *
	 * Applies:
	 *  - data-design attribute for CSS design variants
	 *  - fbk-no-icons class on option rows when show_icons = false
	 *
	 * @private
	 * @returns {string} Safe HTML string.
	 */
	FeedbackSdkModal.prototype._buildHtml = function () {
		var cfg       = this.cfg;
		var design    = cfg.design    || 'card';
		var showIcons = !! cfg.showIcons;  // 0 → false, 1 → true

		// Brand icon — image takes priority over icon class.
		var brandIconHtml = cfg.brandIconUrl
			? '<img src="' + this._esc( cfg.brandIconUrl ) + '" alt="" loading="lazy">'
			: '<i class="ti ' + this._esc( cfg.brandIcon || 'ti-bolt' ) + '" aria-hidden="true"></i>';

		// Reasons list.
		var reasonsHtml = ( cfg.reasons || [] ).map( function ( r ) {
			var iconHtml = showIcons
				? '<span class="fbk-opt-icon"><i class="ti ' + $( '<span>' ).text( r.icon ).html() + '" aria-hidden="true"></i></span>'
				: '';

			return '<div class="fbk-option" data-reason="' + $( '<span>' ).text( r.key ).html() + '">' +
				'<div class="fbk-option-row' + ( showIcons ? '' : ' fbk-no-icons' ) + '">' +
				'<span class="fbk-radio"><span class="fbk-dot"></span></span>' +
				iconHtml +
				'<span class="fbk-opt-label">' + $( '<span>' ).text( r.label ).html() + '</span>' +
				'</div>' +
				'<div class="fbk-textarea-wrap">' +
				'<textarea class="fbk-option-ta" rows="2" placeholder="' + $( '<span>' ).text( r.ph ).html() + '"></textarea>' +
				'</div>' +
				'</div>';
		} ).join( '' );

		// GDPR checkbox.
		var gdprHtml = cfg.gdpr
			? '<label class="fbk-gdpr">' +
			  '<input type="checkbox" class="fbk-gdpr-check" checked aria-label="' + this._esc( cfg.i18n.gdpr_label ) + '"> ' +
			  $( '<span>' ).text( cfg.i18n.gdpr_label ).html() +
			  '</label>'
			: '';

		return (
			'<div class="feedback-sdk-modal" role="dialog" aria-modal="true"' +
			' aria-labelledby="fbk-modal-title-' + cfg.slug + '"' +
			' data-design="' + this._esc( design ) + '">' +

			// Header
			'<div class="fbk-modal-header">' +
			'<div class="fbk-modal-brand">' +
			'<div class="fbk-modal-brand-icon">' + brandIconHtml + '</div>' +
			'<span class="fbk-modal-brand-name" id="fbk-modal-title-' + cfg.slug + '">' +
			$( '<span>' ).text( cfg.brandName || 'Quick Feedback' ).html() +
			'</span>' +
			'</div>' +
			'<button class="fbk-modal-close fbk-js-cancel" aria-label="' + this._esc( cfg.i18n.cancel ) + '">' +
			'<i class="ti ti-x" aria-hidden="true"></i>' +
			'</button>' +
			'</div>' +

			// Body
			'<div class="fbk-modal-body">' +
			'<p class="fbk-modal-question">' + $( '<span>' ).text( cfg.i18n.title ).html() + '</p>' +
			'<div class="fbk-options">' + reasonsHtml + '</div>' +
			gdprHtml +
			'</div>' +

			// Footer
			'<div class="fbk-modal-footer">' +
			'<button class="fbk-skip-btn fbk-js-skip">' + $( '<span>' ).text( cfg.i18n.skip ).html() + '</button>' +
			'<div class="fbk-footer-right">' +
			'<button class="fbk-cancel-btn fbk-js-cancel">' + $( '<span>' ).text( cfg.i18n.cancel ).html() + '</button>' +
			'<button class="fbk-submit-btn fbk-js-submit" aria-label="' + this._esc( cfg.i18n.submit ) + '">' +
			'<i class="ti ti-send fbk-btn-icon" aria-hidden="true"></i> ' +
			$( '<span>' ).text( cfg.i18n.submit ).html() +
			'</button>' +
			'</div>' +
			'</div>' +

			'</div>'
		);
	};

	/**
	 * Escape a string for safe insertion into an HTML attribute.
	 *
	 * @param {string} val Raw string value.
	 * @returns {string} HTML-escaped string.
	 */
	FeedbackSdkModal.prototype._esc = function ( val ) {
		return $( '<span>' ).text( String( val || '' ) ).html()
			.replace( /"/g, '&quot;' );
	};

	/**
	 * On DOM ready, find every SDK overlay element and initialise it.
	 *
	 * Overlay IDs follow the pattern `feedback-sdk-modal-{slug}`.
	 * Config objects are stored in `window.feedbackSdk_{slug_underscored}`.
	 */
	$( function () {
		$( '[id^="feedback-sdk-modal-"]' ).each( function () {
			var slug    = $( this ).attr( 'id' ).replace( 'feedback-sdk-modal-', '' );
			var cfgKey  = 'feedbackSdk_' + slug.replace( /-/g, '_' );
			var cfg     = window[ cfgKey ];

			if ( cfg ) {
				$( this ).feedbackSdkModal( cfg );
			}
		} );
	} );

} )( jQuery );
