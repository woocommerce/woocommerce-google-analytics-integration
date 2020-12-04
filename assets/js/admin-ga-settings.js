jQuery(document).ready( function($) {

	var ecCheckbox        = $( '#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled' );
	var uaCheckbox        = $( '#woocommerce_google_analytics_ga_use_universal_analytics' );
	var gtagCheckbox      = $( '#woocommerce_google_analytics_ga_gtag_enabled' );

	updateToggles();

	ecCheckbox.change(updateToggles);
	uaCheckbox.change(updateToggles);
	gtagCheckbox.change(updateToggles);

	function updateToggles() {
		var isEnhancedEcommerce  = ecCheckbox.is( ':checked' );
		var isUniversalAnalytics = uaCheckbox.is( ':checked' );
		var isGtag               = gtagCheckbox.is( ':checked' );

		// Legacy: gtag NO
		toggleCheckboxRow( $( '.legacy-setting' ), ! isGtag );

		// Enhanced settings: Enhanced YES + universal YES or gtag YES
		toggleCheckboxRow( $( '.enhanced-setting' ), isEnhancedEcommerce && ( isUniversalAnalytics || isGtag ) );

		// Enhanced toggle: universal YES or gtag YES
		toggleCheckboxRow( ecCheckbox, isUniversalAnalytics || isGtag );

		// Universal toggle: gtag NO
		toggleCheckboxRow( uaCheckbox, ! isGtag );

	}

	function toggleCheckboxRow ( checkbox, isVisible ) {
		if ( isVisible ) {
			checkbox.closest('tr').show();
		} else {
			checkbox.closest('tr').hide();
		}
	}
} );


