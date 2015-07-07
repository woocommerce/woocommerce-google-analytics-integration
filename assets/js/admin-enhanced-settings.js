jQuery(document).ready( function($) {

	if ( false === $( '#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled' ).is( ':checked' ) ) {
		$( '.enhanced-setting' ).parent().parent().parent().parent().hide();
	}

	$( '#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled' ).on( 'click', function() {
		var row = $( '.enhanced-setting' ).parent().parent().parent().parent();
		if ( false === $( '#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled' ).is( ':checked' ) ) {
			row.hide();
		} else {
			row.show();
		}
	} );

} );