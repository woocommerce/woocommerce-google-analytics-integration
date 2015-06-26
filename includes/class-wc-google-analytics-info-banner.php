<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Google_Analytics_Info_Banner class
 *
 * Displays a message after install (if not dismissed and GA is not already configured) about how to configure the analytics plugin
 */
class WC_Google_Analytics_Info_Banner {

	/** @var object Class Instance */
	private static $instance;

	/** @var boolean If the banner has been dismissed */
	private $is_dismissed = false;

	/**
	 * Get the class instance
	 */
	public static function get_instance( $dismissed = false, $ga_id = '' ) {
		return null === self::$instance ? ( self::$instance = new self( $dismissed, $ga_id ) ) : self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct( $dismissed = false, $ga_id = '' ) {
		$this->is_dismissed = (bool) $dismissed;
		if ( ! empty( $ga_id ) ) {
			$this->is_dismissed = true;
		}

		// Don't bother setting anything else up if we are not going to show the notice
		if ( true === $this->is_dismissed ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'banner' ) );
		add_action( 'admin_init', array( $this, 'dismiss_banner' ) );
	}

	/**
	 * Displays a info banner on WooCommerce settings pages
	 */
	public function banner() {
		$screen = get_current_screen();

		if ( ! in_array( $screen->base, array( 'woocommerce_page_wc-settings' ) ) || $screen->is_network || $screen->action ) {
			return;
		}

		$integration_url = esc_url( admin_url('admin.php?page=wc-settings&tab=integration' ) );
		$dismiss_url = $this->dismiss_url();

		$heading = __( 'Google Analytics &amp; WooCommerce', 'woocommerce-google-analytics-integration' );
		$configure = sprintf( __( 'Make sure to configure this plugin under the WooCommerce <a href="%s">integration tab</a>.' ), $integration_url );
		$messages = array(
			$configure,
			__( 'After configuring the plugin, please give Google Analytics 24 hours to start displaying results.', 'woocommerce-google-analytics-integration' ),
			__( 'For transaction tracking to properly work, you will need to use a payment gateway that redirects the customer back to a WooCommerce order received/thank you page.', 'woocommerce-google-analytics-integration' ),
			__( 'You must enable Ecommerce reporting in Google Analytics. <a href="https://support.google.com/analytics/answer/1009612?hl=en#Enable">See here for more information</a>.')
		);

		// Display the message..
		echo '<div class="updated fade"><p><strong>' . $heading . '</strong> ';
		echo '<a href="' . esc_url( $dismiss_url ). '" title="' . __( 'Dismiss this notice.', 'woocommerce-google-analytics-integration' ) . '"> ' . __( '(Dismiss)', 'woocommerce-google-analytics-integration' ) . '</a>';
		echo '<ul>';
		foreach ( $messages as $message ) {
			echo '<li>' . $message . '</li>';
		}
		echo "</ul></p></div>\n";
	}

	/**
	 * Returns the url that the user clicks to remove the info banner
	 * @return (string)
	 */
	function dismiss_url() {
		$url = admin_url( 'admin.php' );

		$url = add_query_arg( array(
			'page'      => 'wc-settings',
			'tab'       => 'integration',
			'wc-notice' => 'dismiss-info-banner',
		), $url );

		return wp_nonce_url( $url, 'woocommerce_info_banner_dismiss' );
	}

	/**
	 * Handles the dismiss action so that the banner can be permanently hidden
	 */
	function dismiss_banner() {
		if ( ! isset( $_GET['wc-notice'] ) ) {
			return;
		}

		if ( 'dismiss-info-banner' !== $_GET['wc-notice'] ) {
			return;
		}

		if ( ! check_admin_referer( 'woocommerce_info_banner_dismiss' ) ) {
			return;
		}

		update_option( 'woocommerce_dismissed_info_banner', true );

		if ( wp_get_referer() ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=integration' ) );
		}
	}

}
