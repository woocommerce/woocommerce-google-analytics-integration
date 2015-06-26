<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Google_Analytics_JS class
 *
 * JS for recording Google Analytics info
 */
class WC_Google_Analytics_JS {

	/** @var object Class Instance */
	private static $instance;

	/** @var array Inherited Analytics options */
	private static $options;

	/**
	 * Get the class instance
	 */
	public static function get_instance( $options = array() ) {
		return null === self::$instance ? ( self::$instance = new self( $options ) ) : self::$instance;
	}

	/**
	 * Constructor
	 * Takes our options from the parent class so we can later use them in the JS snippets
	 */
	public function __construct( $options = array() ) {
		self::$options = $options;
	}

	/**
	 * Return one of our options
	 * @param  string $option Key/name for the option
	 * @return string         Value of the option
	 */
	public static function get( $option ) {
		return self::$options[$option];
	}

	/**
	 * Generic GA / header snippet
	 */
	public static function header() {
		return "<script type='text/javascript'>
			var gaProperty = '" . esc_js( self::get( 'ga_id' ) ) . "';
			var disableStr = 'ga-disable-' + gaProperty;
			if ( document.cookie.indexOf( disableStr + '=true' ) > -1 ) {
				window[disableStr] = true;
			}
			function gaOptout() {
				document.cookie = disableStr + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';
				window[disableStr] = true;
			}
		</script>";
	}

	function addTransaction( $transaction ) {

	}

	function addItem( $transaction ) {

	}



}
