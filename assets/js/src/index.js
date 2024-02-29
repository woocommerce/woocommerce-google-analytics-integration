// Initialize tracking for classic WooCommerce pages
import { trackClassicPages } from './integrations/classic';
window.wcgai.trackClassicPages = trackClassicPages;

// Initialize tracking for Block based WooCommerce pages
import './integrations/blocks';
