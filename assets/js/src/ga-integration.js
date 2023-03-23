// eslint-disable-next-line camelcase
const google_analytics_integration_product_data = [];

// eslint-disable-next-line no-undef
jQuery( document ).ready( function ( $ ) {
	$( document ).on(
		'found_variation',
		'form.cart',
		function ( e, variation ) {
			google_analytics_integration_product_data[
				variation.variation_id
			] = variation.google_analytics_integration;
		}
	);
} );
