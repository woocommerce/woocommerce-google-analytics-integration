import { eventData } from './config.js';
import { trackClassicIntegration } from './integrations/classic'

// Initialize tracking for classic WooCommerce pages
document.addEventListener( 'DOMContentLoaded', () => {
    trackClassicIntegration({
        ...eventData()
    });
});

// Initialize tracking for Block based WooCommerce pages
import './integrations/blocks';
