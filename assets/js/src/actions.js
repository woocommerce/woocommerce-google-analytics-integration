import { addAction } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { NAMESPACE, ACTION_PREFIX } from './constants';
import {
	trackEvent,
	getProductFieldObject,
	getProductImpressionObject,
} from './utils';

const trackListProducts = ( {
	products,
	listName = __( 'Product List', 'woocommerce-google-analytics-integration' ),
} ) => {
	trackEvent( 'view_item_list', {
		event_category: 'engagement',
		event_label: __( 'Viewing products', 'woocommerce-google-analytics-integration' ),
		items: products.map( ( product, index ) => ( {
			...getProductImpressionObject( product, listName ),
			list_position: index + 1,
		} ) ),
	} );
};

const trackAddToCart = ( product, quantity = 1 ) => {
	trackEvent( 'add_to_cart', {
		event_category: 'ecommerce',
		event_label: __( 'Add to Cart', 'woocommerce-google-analytics-integration' ),
		items: [ getProductFieldObject( product, quantity ) ],
	} );
};

const trackRemoveCartItem = ( { product, quantity = 1 } ) => {
	trackEvent( 'remove_from_cart', {
		event_category: 'ecommerce',
		event_label: __( 'Remove Cart Item', 'woocommerce-google-analytics-integration' ),
		items: [ getProductFieldObject( product, quantity ) ],
	} );
};

const trackChangeCartItemQuantity = ( { product, quantity = 1 } ) => {
	trackEvent( 'change_cart_quantity', {
		event_category: 'ecommerce',
		event_label: __(
			'Change Cart Item Quantity',
			'woocommerce-google-analytics-integration'
		),
		items: [ getProductFieldObject( product, quantity ) ],
	} );
};

addAction( `${ ACTION_PREFIX }-list-products`, NAMESPACE, trackListProducts );
addAction( `${ ACTION_PREFIX }-add-cart-item`, NAMESPACE, trackAddToCart );
addAction(
	`${ ACTION_PREFIX }-set-cart-item-quantity`,
	NAMESPACE,
	trackChangeCartItemQuantity
);
addAction(
	`${ ACTION_PREFIX }-remove-cart-item`,
	NAMESPACE,
	trackRemoveCartItem
);
