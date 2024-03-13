import { config } from './config';
import { classicTracking } from './integrations/classic';
import { blocksTracking } from './integrations/blocks';

document.addEventListener( 'DOMContentLoaded', () => {
    if ( ! config() ) {
        throw new Error( 'Google Analytics for WooCommerce: Configuration and tracking data not found.' );
    }

    classicTracking();
    blocksTracking();
});