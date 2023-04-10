jQuery( document ).ready( function ( $ ) {
	const ecCheckbox = $(
		'#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled'
	);
	const uaCheckbox = $(
		'#woocommerce_google_analytics_ga_use_universal_analytics'
	);
	const gtagCheckbox = $( '#woocommerce_google_analytics_ga_gtag_enabled' );

	updateToggles();

	ecCheckbox.change( updateToggles );
	uaCheckbox.change( updateToggles );
	gtagCheckbox.change( updateToggles );

	function updateToggles() {
		const isEnhancedEcommerce = ecCheckbox.is( ':checked' );
		const isUniversalAnalytics = uaCheckbox.is( ':checked' );
		const isGtag = gtagCheckbox.is( ':checked' );

		// Legacy: gtag NO
		toggleCheckboxRow( $( '.legacy-setting' ), ! isGtag );

		// Enhanced settings: Enhanced YES + universal YES or gtag YES
		toggleCheckboxRow(
			$( '.enhanced-setting' ),
			isEnhancedEcommerce && ( isUniversalAnalytics || isGtag )
		);

		// Enhanced toggle: universal YES or gtag YES
		toggleCheckboxRow( ecCheckbox, isUniversalAnalytics || isGtag );

		// Universal toggle: gtag NO
		toggleCheckboxRow( uaCheckbox, ! isGtag );
	}

	function toggleCheckboxRow( checkbox, isVisible ) {
		if ( isVisible ) {
			checkbox.closest( 'tr' ).show();
		} else {
			checkbox.closest( 'tr' ).hide();
		}
	}
} );
