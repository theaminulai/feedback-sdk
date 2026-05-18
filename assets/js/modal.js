/**
 * Feedback SDK — Deactivation Modal jQuery plugin.
 *
 * Intercepts the WordPress plugin deactivation link, opens a branded
 * feedback modal, submits the response via AJAX, and then continues
 * with the original deactivation URL.
 *
 * Supports full theming via CSS custom properties set from PHP.
 *
 * @file    modal.js
 * @package Feedback_SDK
 * @since   1.0.0
 *
 * @requires jQuery
 *
 * Config is passed via wp_localize_script as `feedbackSdk_{slug}`.
 */

/* global jQuery */
( function ( $ ) {
	'use strict';

	// ── Plugin definition ────────────────────────────────────────────────────

	/**
	 * jQuery plugin: $.fn.feedbackSdkModal
	 *
	 * Attaches the deactivation modal behaviour to a given overlay element.
	 *
	 * @param {object} cfg - Configuration from wp_localize_script.
	 * @returns {jQuery} Chainable jQuery object.
	 *
	 * @example
	 *   $( '#feedback-sdk-modal-elementskit-lite' ).feedbackSdkModal( window.feedbackSdk_elementskit_lite );
	 */
	$.fn.feedbackSdkModal = function ( cfg ) {
		return this.each( function () {
			new FeedbackSdkModal( $( this ), cfg );
		} );
	};

	// ── FeedbackSdkModal constructor ─────────────────────────────────────────

	/**
	 * Feedback SDK modal controller.
	 *
	 * @constructor
	 * @param {jQuery} $overlay - The empty overlay <div> injected by PHP.
	 * @param {object} cfg      - Localized config object.
	 * @param {string} cfg.slug           - Plugin slug (kebab-case).
	 * @param {string} cfg.pluginName     - Human-readable plugin name.
	 * @param {string} cfg.ajaxUrl        - WordPress admin-ajax.php URL.
	 * @param {string} cfg.nonce          - WP nonce for AJAX requests.
	 * @param {boolean} cfg.gdpr          - Show GDPR consent checkbox.
	 * @param {string} cfg.brandIcon      - Tabler icon class (e.g. 'ti-bolt').
	 * @param {string} cfg.brandIconUrl   - Optional image URL; overrides brandIcon.
	 * @param {string} cfg.brandName      - Modal header title.
	 * @param {object} cfg.i18n           - Translatable strings.
	 * @param {Array}  cfg.reasons        - Array of {key, icon, label, ph} objects.
	 */
	function FeedbackSdkModal( $overlay, cfg ) {
		/** @type {jQuery} */
		this.$overlay = $overlay;

		/** @type {object} */
		this.cfg = cfg;

		/** @type {string|null} Currently selected reason key. */
		this.selectedReason = null;

		/** @type {string|null} Original WP deactivation URL. */
		this.deactivateUrl = null;

		this._init();
	}

	/**
	 * Initialise: find the deactivate link and intercept it.
	 *
	 * @private
	 * @returns {void}
	 */
	FeedbackSdkModal.prototype._init = function () {
		var self = this;
		var slug = this.cfg.slug;

		$( document ).ready( function () {
			// WP renders deactivate links as:
			// a[href*="action=deactivate"][href*="plugin=SLUG"]
			var $link = $( 'a[href*="action=deactivate"]' ).filter( function () {
				var href = $( this ).attr( 'href' ) || '';
				return href.indexOf( encodeURIComponent( slug ) ) !== -1 ||
				       href.indexOf( slug ) !== -1;
			} ).first();

			if ( ! $link.length ) {
				if ( self.cfg.debug ) {
					window.console.log( '[FeedbackSDK] Deactivate link not found for slug:', slug );
				}
				return;
			}

			$link.on( 'click.feedbackSdk', function ( e ) {
				e.preventDefault();
				self.deactivateUrl = $( this ).attr( 'href' );
				self._open();
			} );

			if ( self.cfg.debug ) {
				window.console.log( '[FeedbackSDK] Intercepting deactivate for:', slug );
			}
		} );
	};

	// ── Modal lifecycle ──────────────────────────────────────────────────────

	/**
	 * Build modal HTML, inject into the overlay, and animate it open.
	 *
	 * @returns {void}
	 */
	FeedbackSdkModal.prototype._open = function () {
		this.selectedReason = null;
		this.$overlay.html( this._buildHtml() ).show();

		// Animate in on next tick so CSS transition applies.
		var self = this;
		requestAnimationFrame( function () {
			self.$overlay.addClass( 'fbk-sdk-visible' );
		} );

		this._bindEvents();
		$( 'body' ).css( 'overflow', 'hidden' );
	};

	/**
	 * Animate the modal out and remove it from the DOM.
	 *
	 * @returns {void}
	 */
	FeedbackSdkModal.prototype._close = function () {
		var self = this;
		this.$overlay.removeClass( 'fbk-sdk-visible' );
		setTimeout( function () {
			self.$overlay.hide().empty();
		}, 220 );
		$( 'body' ).css( 'overflow', '' );
		$( document ).off( 'keydown.feedbackSdk' );
	};

	/**
	 * Wire up all events inside the rendered modal.
	 *
	 * @private
	 * @returns {void}
	 */
	FeedbackSdkModal.prototype._bindEvents = function () {
		var self = this;

		// Option select.
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

		// Cancel / close.
		this.$overlay.on( 'click', '.fbk-js-cancel, .fbk-modal-close', function () {
			self._close();
		} );

		// Click outside.
		this.$overlay.on( 'click', function ( e ) {
			if ( $( e.target ).is( self.$overlay ) ) {
				self._close();
			}
		} );

		// ESC key.
		$( document ).on( 'keydown.feedbackSdk', function ( e ) {
			if ( 27 === e.which ) {
				self._close();
			}
		} );

		// Skip — proceed to deactivation without submitting.
		this.$overlay.on( 'click', '.fbk-js-skip', function () {
			self._doAjax( 'skip', null, null, false );
		} );

		// Submit.
		this.$overlay.on( 'click', '.fbk-js-submit', function () {
			if ( ! self.selectedReason ) {
				self.$overlay.find( '.fbk-options' ).addClass( 'fbk-shake' );
				setTimeout( function () {
					self.$overlay.find( '.fbk-options' ).removeClass( 'fbk-shake' );
				}, 500 );
				return;
			}

			var $active  = self.$overlay.find( '.fbk-option.fbk-active' );
			var message  = $active.find( '.fbk-option-ta' ).val();
			var gdprOk   = ! self.cfg.gdpr || self.$overlay.find( '.fbk-gdpr-check' ).is( ':checked' );

			self._doAjax( 'submit', self.selectedReason, message, gdprOk );
		} );
	};

	// ── AJAX ─────────────────────────────────────────────────────────────────

	/**
	 * Send feedback via wp_ajax (or skip) then redirect to the deactivation URL.
	 *
	 * On any network failure the deactivation still proceeds so the user is
	 * never blocked.
	 *
	 * @param {string}      action   - 'submit' or 'skip'.
	 * @param {string|null} reason   - Selected reason key.
	 * @param {string|null} message  - Optional textarea content.
	 * @param {boolean}     gdprOk   - Whether GDPR consent was given.
	 * @returns {void}
	 */
	FeedbackSdkModal.prototype._doAjax = function ( action, reason, message, gdprOk ) {
		var self      = this;
		var slug      = this.cfg.slug;
		var $btn      = this.$overlay.find( '.fbk-js-submit' );
		var ajaxAction = 'submit' === action
			? 'feedback_sdk_submit_' + slug
			: 'feedback_sdk_skip_'   + slug;

		// Disable submit and show sending state.
		$btn.prop( 'disabled', true )
			.find( 'i' )
			.removeClass( 'ti-send' )
			.addClass( 'ti-loader-2' );

		$.post( self.cfg.ajaxUrl, {
			action:     ajaxAction,
			nonce:      self.cfg.nonce,
			reason:     reason  || '',
			message:    message || '',
			competitor: '',
			gdpr:       gdprOk ? 1 : 0,
		} )
		.always( function ( res ) {
			// Proceed with deactivation regardless of outcome.
			var url = ( res && res.success && res.data && res.data.deactivate_url )
				? res.data.deactivate_url
				: self.deactivateUrl;

			if ( url ) {
				window.location.href = url;
			} else {
				self._close();
			}
		} );
	};

	// ── HTML builder ─────────────────────────────────────────────────────────

	/**
	 * Build and return the complete modal HTML string.
	 *
	 * @private
	 * @returns {string} Full modal HTML.
	 */
	FeedbackSdkModal.prototype._buildHtml = function () {
		var cfg = this.cfg;

		var brandIconHtml = cfg.brandIconUrl
			? '<img src="' + $( '<span>' ).text( cfg.brandIconUrl ).html() + '" alt="" class="fbk-modal-brand-img">'
			: '<i class="ti ' + $( '<span>' ).text( cfg.brandIcon || 'ti-bolt' ).html() + '" aria-hidden="true"></i>';

		var reasonsHtml = cfg.reasons.map( function ( r ) {
			return '<div class="fbk-option" data-reason="' + $( '<span>' ).text( r.key ).html() + '">' +
				'<div class="fbk-option-row">' +
				'<span class="fbk-radio"><span class="fbk-dot"></span></span>' +
				'<span class="fbk-opt-icon"><i class="ti ' + $( '<span>' ).text( r.icon ).html() + '" aria-hidden="true"></i></span>' +
				'<span class="fbk-opt-label">' + $( '<span>' ).text( r.label ).html() + '</span>' +
				'</div>' +
				'<div class="fbk-textarea-wrap">' +
				'<textarea class="fbk-option-ta" rows="2" placeholder="' + $( '<span>' ).text( r.ph ).html() + '"></textarea>' +
				'</div>' +
				'</div>';
		} ).join( '' );

		var gdprHtml = cfg.gdpr
			? '<label class="fbk-gdpr">' +
			  '<input type="checkbox" class="fbk-gdpr-check" checked> ' +
			  $( '<span>' ).text( cfg.i18n.gdpr_label ).html() +
			  '</label>'
			: '';

		return '<div class="feedback-sdk-modal" role="dialog" aria-modal="true" aria-labelledby="fbk-modal-title-' + cfg.slug + '">' +

			'<div class="fbk-modal-header">' +
			'<div class="fbk-modal-brand">' +
			'<div class="fbk-modal-brand-icon">' + brandIconHtml + '</div>' +
			'<span class="fbk-modal-brand-name" id="fbk-modal-title-' + cfg.slug + '">' +
			$( '<span>' ).text( cfg.brandName || 'Quick Feedback' ).html() +
			'</span>' +
			'</div>' +
			'<button class="fbk-modal-close fbk-js-cancel" aria-label="' + $( '<span>' ).text( cfg.i18n.cancel ).html() + '">' +
			'<i class="ti ti-x" aria-hidden="true"></i></button>' +
			'</div>' +

			'<div class="fbk-modal-body">' +
			'<p class="fbk-modal-question">' + $( '<span>' ).text( cfg.i18n.title ).html() + '</p>' +
			'<div class="fbk-options">' + reasonsHtml + '</div>' +
			gdprHtml +
			'</div>' +

			'<div class="fbk-modal-footer">' +
			'<button class="fbk-skip-btn fbk-js-skip">' + $( '<span>' ).text( cfg.i18n.skip ).html() + '</button>' +
			'<div class="fbk-footer-right">' +
			'<button class="fbk-cancel-btn fbk-js-cancel">' + $( '<span>' ).text( cfg.i18n.cancel ).html() + '</button>' +
			'<button class="fbk-submit-btn fbk-js-submit">' +
			'<i class="ti ti-send" aria-hidden="true"></i> ' + $( '<span>' ).text( cfg.i18n.submit ).html() +
			'</button>' +
			'</div>' +
			'</div>' +

			'</div>';
	};

	// ── Boot ─────────────────────────────────────────────────────────────────

	/**
	 * Auto-initialise every SDK overlay found on the page.
	 *
	 * The overlay IDs follow the pattern `feedback-sdk-modal-{slug}`.
	 * The matching config is stored in `window.feedbackSdk_{slug_underscored}`.
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
