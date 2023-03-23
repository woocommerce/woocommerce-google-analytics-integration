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
	listName = __( 'Product List', 'woo-gutenberg-products-block' ),
} ) => {
	trackEvent( 'view_item_list', {
		event_category: 'engagement',
		event_label: __( 'Viewing products', 'woo-gutenberg-products-block' ),
		items: products.map( ( product, index ) => ( {
			...getProductImpressionObject( product, listName ),
			list_position: index + 1,
		} ) ),
	} );
};

const trackAddToCart = ( product, quantity = 1 ) => {
	trackEvent( 'add_to_cart', {
		event_category: 'ecommerce',
		event_label: __( 'Add to Cart', 'woo-gutenberg-products-block' ),
		items: [ getProductFieldObject( product, quantity ) ],
	} );
};

const trackRemoveCartItem = ( { product, quantity = 1 } ) => {
	trackEvent( 'remove_from_cart', {
		event_category: 'ecommerce',
		event_label: __( 'Remove Cart Item', 'woo-gutenberg-products-block' ),
		items: [ getProductFieldObject( product, quantity ) ],
	} );
};

const trackChangeCartItemQuantity = ( { product, quantity = 1 } ) => {
	trackEvent( 'change_cart_quantity', {
		event_category: 'ecommerce',
		event_label: __(
			'Change Cart Item Quantity',
			'woo-gutenberg-products-block'
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
