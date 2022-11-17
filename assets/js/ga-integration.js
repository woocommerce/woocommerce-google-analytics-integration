var google_analytics_integration_product_data = [];
jQuery(document).ready( function($) {
    $(document).on( "found_variation", "form.cart", function( e, variation ) {
        google_analytics_integration_product_data[ variation.variation_id ] = variation.google_analytics_integration;
    });
});
