<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Google_Analytics_Integration as Plugin;

/**
 * WC_Google_Gtag_JS class
 *
 * JS for recording Google Gtag info
 */
class WC_Google_Gtag_JS extends WC_Abstract_Google_Analytics_JS {

	/** @var string $script_handle Handle for the front end JavaScript file */
	public $script_handle = 'woocommerce-google-analytics-integration';

	/** @var string $gtag_script_handle Handle for the gtag setup script */
	public $gtag_script_handle = 'woocommerce-google-analytics-integration-gtag';

	/** @var string $data_script_handle Handle for the event data inline script */
	public $data_script_handle = 'woocommerce-google-analytics-integration-data';

	/** @var string $script_data Data required for frontend event tracking */
	private $script_data = array();

	/** @var array $mappings A map of the GA4 events and the classic WooCommerce hooks that trigger them */
	private $mappings = array(
		'actions' => array(
			'begin_checkout'   => 'woocommerce_before_checkout_form',
			'purchase'         => 'woocommerce_thankyou',
			'add_to_cart'      => 'woocommerce_add_to_cart',
			'remove_from_cart' => 'woocommerce_cart_item_removed',
			'view_item'        => 'woocommerce_after_single_product',
		),
		'filters' => array(
			'view_item_list' => 'woocommerce_loop_add_to_cart_link',
		),
	);

	/**
	 * Constructor
	 * Takes our settings from the parent class so we can later use them in the JS snippets
	 *
	 * @param array $settings Settings
	 */
	public function __construct( $settings = array() ) {
		$this->settings = $settings;
		self::$instance = $this;

		parent::__construct();

		$this->map_hooks();

		$this->register_scripts();
		// Setup frontend scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker' ), 5 );
		add_action( 'wp_footer', array( $this, 'inline_script_data' ) );
	}

	/**
	 * Register manager and tracker scripts.
	 * Call early so other extensions could add inline data to it.
	 *
	 * @return void
	 */
	private function register_scripts(): void {
		// Deregister first so this can be safely re-run when settings change after
		// construction (see get_instance() rehydration). The `ga_id` baked into the
		// GTM URL and the gtag `config` snippet must reflect the current settings.
		// These are no-ops when nothing has been registered yet.
		wp_deregister_script( 'google-tag-manager' );
		wp_deregister_script( $this->gtag_script_handle );
		wp_deregister_script( $this->script_handle );

		wp_register_script(
			'google-tag-manager',
			'https://www.googletagmanager.com/gtag/js?id=' . $this->get_setting( 'ga_id' ),
			array(),
			null,
			array(
				'strategy' => 'async',
			)
		);

		wp_register_script(
			$this->gtag_script_handle,
			'',
			array(),
			null,
			array(
				'in_footer' => false,
			)
		);

		// The redaction helper is registered outside the `woocommerce_gtag_snippet` filter, so a
		// snippet that replaces the default one can still call it. The lists come from PHP; the
		// URL and referrer can only be read in the browser.
		wp_add_inline_script(
			$this->gtag_script_handle,
			sprintf(
				'window.wcGoogleAnalyticsIntegration = window.wcGoogleAnalyticsIntegration || {};
				/* Returns the gtag config with page_location and page_referrer stripped of sensitive query parameters. */
				window.wcGoogleAnalyticsIntegration.redactGtagConfig = (function ( redaction ) {
					function lower( value ) {
						try {
							return decodeURIComponent( String( value ).replace( /\+/g, " " ) ).toLowerCase();
						} catch ( e ) {
							return String( value ).toLowerCase();
						}
					}
					function redact( href ) {
						try {
							const url = new URL( href );
							if ( ! url.search ) {
								return href;
							}
							const pairs = url.search.slice( 1 ).split( "&" );
							const names = pairs.map( function ( pair ) {
								const eq = pair.indexOf( "=" );
								return lower( eq === -1 ? pair : pair.slice( 0, eq ) );
							} );
							const segments = url.pathname.split( "/" ).map( lower );
							const isOrderPage = ( redaction.order_endpoints || [] ).some( function ( endpoint ) {
								return segments.includes( endpoint ) || names.includes( endpoint );
							} );
							const kept = [];
							let changed = false;
							pairs.forEach( function ( pair, index ) {
								const eq = pair.indexOf( "=" );
								const value = eq === -1 ? "" : pair.slice( eq + 1 );
								// An order key starts with wc_ (the rest of the prefix is filterable in
								// WooCommerce) and may sit inside a return URL carried by another parameter.
								const isOrderKey = /^wc_|wc_order_/i.test( value ) || /^wc_|wc_order_/i.test( lower( value ) );
								const drop = pair !== "" && ( isOrderKey || ( isOrderPage
									? ! ( redaction.order_params || [] ).includes( names[ index ] )
									: redaction.params.includes( names[ index ] ) ) );
								if ( drop ) {
									changed = true;
								} else {
									kept.push( pair );
								}
							} );
							if ( ! changed ) {
								return href;
							}
							// Reassembled from the original pairs so kept values keep their exact encoding.
							url.search = kept.join( "&" );
							return url.href;
						} catch ( e ) {
							// Unparsable or unfilterable: drop the query and fragment rather than risk leaking a key.
							return String( href ).split( /[?#]/ )[ 0 ];
						}
					}
					return function ( config ) {
						try {
							if ( ! redaction || ! Array.isArray( redaction.params ) ) {
								return config;
							}
							if ( ! ( "page_location" in config ) ) {
								const pageLocation = redact( document.location.href );
								if ( pageLocation !== document.location.href ) {
									config.page_location = pageLocation;
								}
							}
							if ( ! ( "page_referrer" in config ) && document.referrer ) {
								const pageReferrer = redact( document.referrer );
								if ( pageReferrer !== document.referrer ) {
									config.page_referrer = pageReferrer;
								}
							}
						} catch ( e ) {}
						return config;
					};
				})( %1$s );',
				wp_json_encode( $this->get_url_redaction_config(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES )
			),
			'before'
		);

		wp_add_inline_script(
			$this->gtag_script_handle,
			apply_filters(
				'woocommerce_gtag_snippet',
				sprintf(
					'/* Google Analytics for WooCommerce (gtag.js) */
					window.dataLayer = window.dataLayer || [];
					function %2$s(){dataLayer.push(arguments);}
					// Set up default consent state.
					for ( const mode of %4$s || [] ) {
						%2$s( "consent", "default", { "wait_for_update": 500, ...mode } );
					}
					%2$s("js", new Date());
					%2$s("set", "developer_id.%3$s", true);
					// Report the page URL and referrer without order keys and other sensitive query parameters.
					%2$s("config", "%1$s", ( window.wcGoogleAnalyticsIntegration && window.wcGoogleAnalyticsIntegration.redactGtagConfig || function ( config ) { return config; } )( %5$s ));',
					esc_js( $this->get_setting( 'ga_id' ) ),
					esc_js( $this->tracker_function_name() ),
					esc_js( static::DEVELOPER_ID ),
					wp_json_encode( $this->get_consent_modes(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ),
					wp_json_encode( $this->get_site_tag_config(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES )
				)
			)
		);

		wp_enqueue_script( $this->gtag_script_handle );

		wp_register_script(
			$this->script_handle,
			Plugin::get_instance()->get_js_asset_url( 'main.js' ),
			array(
				...Plugin::get_instance()->get_js_asset_dependencies( 'main' ),
				'google-tag-manager',
			),
			Plugin::get_instance()->get_js_asset_version( 'main' ),
			true
		);
	}

	/**
	 * Enqueue tracker scripts and its inline config.
	 * We need to execute tracker.js w/ `gtag` configuration before any trackable action may happen.
	 *
	 * @return void
	 */
	public function enqueue_tracker(): void {
		wp_enqueue_script( 'google-tag-manager' );
		// tracker.js needs to be executed ASAP, the remaining bits for main.js could be deferred,
		// but to reduce the traffic, we ship it all together.
		wp_enqueue_script( $this->script_handle );
	}

	/**
	 * @deprecated 2.2.0 Use enqueue_tracker() instead.
	 */
	public function enquque_tracker(): void {
		_deprecated_function( __METHOD__, '2.2.0', 'WC_Google_Gtag_JS::enqueue_tracker' );
		$this->enqueue_tracker();
	}

	/**
	 * Add all event data via an inline script in the footer to ensure all the data is collected in time.
	 *
	 * @return void
	 */
	public function inline_script_data(): void {
		wp_register_script(
			$this->data_script_handle,
			'',
			array( $this->script_handle ),
			null,
			array(
				'in_footer' => true,
			)
		);

		wp_add_inline_script(
			$this->data_script_handle,
			sprintf(
				'window.ga4w = { data: %1$s, settings: %2$s }; document.dispatchEvent(new Event("ga4w:ready"));',
				$this->get_script_data(),
				wp_json_encode(
					array(
						'tracker_function_name' => $this->tracker_function_name(),
						'events'                => $this->get_enabled_events_for_settings(),
						'identifier'            => $this->get_setting( 'ga_product_identifier' ),
						'currency'              => array(
							'decimalSeparator'  => wc_get_price_decimal_separator(),
							'thousandSeparator' => wc_get_price_thousand_separator(),
							'precision'         => wc_get_price_decimals(),
						),
					),
					JSON_HEX_TAG | JSON_UNESCAPED_SLASHES
				),
			)
		);

		wp_enqueue_script( $this->data_script_handle );
	}

	/**
	 * Hook into WooCommerce and add corresponding Blocks Actions to our event data
	 *
	 * @return void
	 */
	public function map_hooks(): void {
		array_walk(
			$this->mappings['actions'],
			function ( $hook, $gtag_event ) {
				add_action(
					$hook,
					function () use ( $gtag_event ) {
						$this->append_event( $gtag_event );
					}
				);
			}
		);

		array_walk(
			$this->mappings['filters'],
			function ( $hook, $gtag_event ) {
				add_action(
					$hook,
					function ( $filtered_value ) use ( $gtag_event ) {
						$this->append_event( $gtag_event );
						return $filtered_value;
					}
				);
			}
		);
	}

	/**
	 * Appends a specific event, if it's not included yet.
	 *
	 * @param string $gtag_event
	 * @return void
	 */
	private function append_event( string $gtag_event ) {
		if ( ! in_array( $gtag_event, $this->script_data['events'] ?? [], true ) ) {
			$this->append_script_data( 'events', $gtag_event );
		}
	}

	/**
	 * Set script data for a specific event
	 *
	 * @param string       $type The type of event this data is related to.
	 * @param string|array $data The event data to add.
	 *
	 * @return void
	 */
	public function set_script_data( string $type, $data ): void {
		$this->script_data[ $type ] = $data;
	}

	/**
	 * Append data to an existing script data array
	 *
	 * @param string       $type The type of event this data is related to.
	 * @param string|array $data The event data to add.
	 *
	 * @return void
	 */
	public function append_script_data( string $type, $data ): void {
		if ( ! isset( $this->script_data[ $type ] ) ) {
			$this->script_data[ $type ] = array();
		}
		$this->script_data[ $type ][] = $data;
	}

	/**
	 * Return a JSON encoded string of all script data for the current page load
	 *
	 * @return string
	 */
	public function get_script_data(): string {
		return wp_json_encode( $this->script_data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Returns the tracker variable this integration should use
	 *
	 * @return string
	 */
	public static function tracker_function_name(): string {
		return apply_filters( 'woocommerce_gtag_tracker_variable', 'gtag' );
	}

	/**
	 * Return Google Analytics configuration, for JS to read.
	 *
	 * @return array
	 */
	public function get_site_tag_config(): array {
		return apply_filters(
			'woocommerce_ga_gtag_config',
			array(
				'track_404'            => 'yes' === $this->get_setting( 'ga_404_tracking_enabled' ),
				'allow_google_signals' => 'yes' === $this->get_setting( 'ga_support_display_advertising' ),
				'logged_in'            => is_user_logged_in(),
				'linker'               => array(
					'domains'        => $this->get_linker_domains(),
					'allow_incoming' => 'yes' === $this->get_setting( 'ga_linker_allow_incoming_enabled' ),
				),
				'custom_map'           => array(
					'dimension1' => 'logged_in',
				),
			),
		);
	}

	/**
	 * Get validated cross-domain linker domains.
	 *
	 * @return array
	 */
	private function get_linker_domains(): array {
		if ( empty( $this->get_setting( 'ga_linker_cross_domains' ) ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					function ( string $domain ) {
						$domain = trim( $domain );

						// Validate the domain as ASCII RFC 1035 / RFC 1123 style. The lookahead caps the total length
						// at the 253-character DNS limit; each label is 1-63 chars and starts/ends with alphanumerics;
						// the TLD is 2-63 ASCII chars to allow punycode domains. Invalid entries are silently dropped.
						if ( preg_match( '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/i', $domain ) ) {
							return esc_js( $domain );
						}

						return null;
					},
					explode( ',', $this->get_setting( 'ga_linker_cross_domains' ) )
				)
			)
		);
	}

	/**
	 * Query parameters stripped from the page URL and referrer reported to Google Analytics on every page.
	 *
	 * Names are matched case-insensitively. Independently of this list, any parameter whose value
	 * looks like a WooCommerce order key, or carries one inside a nested URL, is stripped as well.
	 *
	 * @return string[]
	 */
	public function get_redacted_url_params(): array {
		$params = [
			'key',
			'login',
			'session',
			'email',
			'uid',
			'_wpnonce',
			'_wp_http_referer',
			'woo-share',
			'moderation-hash',
			'unapproved',
			'email_link_action_key',
			'consumer_key',
			'consumer_secret',
			// Nested return URLs: WooCommerce and WordPress put checkout/order URLs in them,
			// where a `key=wc_order_...` would escape the value check above.
			'redirect',
			'redirect_to',
		];

		/**
		 * Filters the query parameters stripped from the page URL and referrer reported to Google Analytics.
		 *
		 * @param string[] $params Parameter names, matched case-insensitively.
		 */
		return self::normalize_url_param_names( apply_filters( 'woocommerce_ga_redacted_url_params', $params ) );
	}

	/**
	 * Slugs of the WooCommerce endpoints whose URLs carry an order key: order-received and order-pay.
	 *
	 * Read from WooCommerce's configured query vars (which honour the endpoint settings and the
	 * `woocommerce_get_query_vars` filter), falling back to the stock slugs.
	 *
	 * @return string[]
	 */
	public function get_order_page_endpoints(): array {
		$query_vars = [];
		if ( function_exists( 'WC' ) && WC()->query instanceof WC_Query ) {
			$query_vars = WC()->query->get_query_vars();
		}

		$endpoints = [];
		foreach ( [ 'order-received', 'order-pay' ] as $endpoint ) {
			$endpoints[] = ! empty( $query_vars[ $endpoint ] ) && is_string( $query_vars[ $endpoint ] ) ? $query_vars[ $endpoint ] : $endpoint;
		}

		return self::normalize_url_param_names( $endpoints );
	}

	/**
	 * Query parameters kept in the page URL reported to Google Analytics on order-received and
	 * order-pay pages. Every other parameter is stripped there.
	 *
	 * @return string[]
	 */
	public function get_order_page_allowed_url_params(): array {
		$endpoints = $this->get_order_page_endpoints();
		$params    = array_merge(
			// The endpoint query vars identify the page on plain permalinks (`?order-received=123`).
			$endpoints,
			[
				'pay_for_order',
				// Page identity on plain permalinks, and the language/currency of the page.
				'page_id',
				'p',
				'pagename',
				'lang',
				'currency',
				// Campaign attribution.
				'utm_source',
				'utm_medium',
				'utm_campaign',
				'utm_term',
				'utm_content',
				'utm_id',
				'utm_source_platform',
				'utm_creative_format',
				'utm_marketing_tactic',
				'utm_nooverride',
				'srsltid',
				'gclid',
				'gbraid',
				'wbraid',
				'dclid',
				'fbclid',
				'msclkid',
				'_gl',
			]
		);

		/**
		 * Filters the query parameters kept in the page URL reported to Google Analytics on the
		 * order-received and order-pay pages.
		 *
		 * @param string[] $params    Parameter names, matched case-insensitively.
		 * @param string[] $endpoints The order-received and order-pay endpoint slugs.
		 */
		return self::normalize_url_param_names( apply_filters( 'woocommerce_ga_order_page_allowed_url_params', $params, $endpoints ) );
	}

	/**
	 * Configuration for the client-side URL redaction, passed to the `redactGtagConfig` helper.
	 *
	 * Returns an empty array when redaction is disabled, which the helper treats as "do nothing".
	 *
	 * @return array
	 */
	public function get_url_redaction_config(): array {
		/**
		 * Filters whether the gtag snippet strips sensitive query parameters, such as order keys,
		 * from the page URL and referrer it reports to Google Analytics.
		 *
		 * @param bool $enabled Whether redaction is enabled. Default true.
		 */
		if ( ! apply_filters( 'woocommerce_ga_url_redaction_enabled', true ) ) {
			return [];
		}

		return [
			'params'          => $this->get_redacted_url_params(),
			'order_endpoints' => $this->get_order_page_endpoints(),
			'order_params'    => $this->get_order_page_allowed_url_params(),
		];
	}

	/**
	 * Lower-case, de-duplicate and reindex a list of query parameter names, dropping anything
	 * that is not a non-empty string.
	 *
	 * @param mixed $names Parameter names, typically the output of a filter.
	 * @return string[]
	 */
	private static function normalize_url_param_names( $names ): array {
		$names = array_filter( (array) $names, 'is_string' );
		$names = array_map(
			function ( string $name ): string {
				// Translated endpoint slugs may be non-ASCII, which strtolower() leaves untouched
				// while the JS side lowercases by Unicode rules.
				return function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
			},
			$names
		);
		$names = array_filter( $names, 'strlen' );

		return array_values( array_unique( $names ) );
	}

	/**
	 * Get an array containing the names of all enabled events
	 *
	 * @return array
	 */
	public function get_enabled_events_for_settings(): array {
		$events   = array();
		$settings = array(
			'purchase'          => 'ga_ecommerce_tracking_enabled',
			'add_to_cart'       => 'ga_event_tracking_enabled',
			'remove_from_cart'  => 'ga_enhanced_remove_from_cart_enabled',
			'view_item_list'    => 'ga_enhanced_product_impression_enabled',
			'select_content'    => 'ga_enhanced_product_click_enabled',
			'view_item'         => 'ga_enhanced_product_detail_view_enabled',
			'begin_checkout'    => 'ga_enhanced_checkout_process_enabled',
			'add_shipping_info' => 'ga_enhanced_checkout_process_enabled',
			'add_payment_info'  => 'ga_enhanced_checkout_process_enabled',
		);

		foreach ( $settings as $event => $setting_name ) {
			if ( 'yes' === $this->get_setting( $setting_name ) ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Compatibility wrapper for the formerly static enabled-events formatter.
	 *
	 * @return array
	 */
	public static function get_enabled_events(): array {
		return static::get_compatibility_instance()->get_enabled_events_for_settings();
	}

	/**
	 * Get the default state configuration of consent mode.
	 */
	protected static function get_consent_modes(): array {
		$consent_modes = array(
			array(
				'analytics_storage'  => 'denied',
				'ad_storage'         => 'denied',
				'ad_user_data'       => 'denied',
				'ad_personalization' => 'denied',
				'region'             => array(
					'AT',
					'BE',
					'BG',
					'HR',
					'CY',
					'CZ',
					'DK',
					'EE',
					'FI',
					'FR',
					'DE',
					'GR',
					'HU',
					'IS',
					'IE',
					'IT',
					'LV',
					'LI',
					'LT',
					'LU',
					'MT',
					'NL',
					'NO',
					'PL',
					'PT',
					'RO',
					'SK',
					'SI',
					'ES',
					'SE',
					'GB',
					'CH',
				),
			),
		);

		/**
		 * Filters the default gtag consent mode configuration.
		 *
		 * @param array $consent_modes Array of default state configuration of consent mode.
		 */
		return apply_filters( 'woocommerce_ga_gtag_consent_modes', $consent_modes );
	}

	/**
	 * Get the class instance
	 *
	 * @param array $settings Settings
	 * @return WC_Abstract_Google_Analytics_JS
	 */
	public static function get_instance( $settings = array() ): WC_Abstract_Google_Analytics_JS {
		if ( null === self::$instance ) {
			self::$instance = new self( $settings );
		} elseif ( ! empty( $settings ) ) {
			// A direct get_instance() call may have bootstrapped the instance with empty
			// settings before the integration supplied the real ones. Rehydrate so later
			// reads (e.g. enabled events, product identifier) reflect the current settings,
			// and re-register the scripts so the baked-in ga_id / config match them too.
			self::$instance->settings = $settings;
			self::$instance->register_scripts();
		}

		return self::$instance;
	}
}
