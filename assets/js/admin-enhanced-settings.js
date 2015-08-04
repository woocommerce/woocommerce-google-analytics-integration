jQuery(document).ready( function($) {

	var enhancedSettingParentRow = $( '.enhanced-setting' ).parent().parent().parent().parent();
	var enhancedToggle = $( '#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled' ).parent().parent().parent().parent();

	if ( false === $( '#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled' ).is( ':checked' ) ) {
		enhancedSettingParentRow.hide();
	}

	if ( false === $( '#woocommerce_google_analytics_ga_use_universal_analytics' ).is( ':checked' ) ) {
		enhancedSettingParentRow.hide();
		enhancedToggle.hide();
	}

	$( '#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled' ).on( 'click', function() {
		if ( false === $( '#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled' ).is( ':checked' ) ) {
			enhancedSettingParentRow.hide();
		} else {
			enhancedSettingParentRow.show();
		}
	} );

	$( '#woocommerce_google_analytics_ga_use_universal_analytics' ).on( 'click', function() {
		if ( false === $( '#woocommerce_google_analytics_ga_use_universal_analytics' ).is( ':checked' ) ) {
			enhancedSettingParentRow.hide();
			enhancedToggle.hide();
		} else {
			if ( true === $( '#woocommerce_google_analytics_ga_enhanced_ecommerce_tracking_enabled' ).is( ':checked' ) ) {
				enhancedSettingParentRow.show();
			}
			enhancedToggle.show();
		}
	} );

} );
