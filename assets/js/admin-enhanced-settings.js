jQuery(document).ready( function($) {

	var enhancedSettingParentRow = $( '.enhanced-setting' ).parent().parent().parent().parent();

	if ( false === $( '#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled' ).is( ':checked' ) ) {
		enhancedSettingParentRow.hide();
	}

	$( '#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled' ).on( 'click', function() {
		if ( false === $( '#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled' ).is( ':checked' ) ) {
			enhancedSettingParentRow.hide();
		} else {
			enhancedSettingParentRow.show();
		}
	} );

} );
