jQuery(document).ready( function($) {
    $(document).on( "found_variation", "form.cart", function( e, variation ) {
        google_analytics_integration = variation.google_analytics_integration;
    });
});