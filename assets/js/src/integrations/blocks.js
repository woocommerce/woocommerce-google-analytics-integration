import { removeAction } from '@wordpress/hooks';
import { addUniqueAction } from '../utils';
import { tracker } from '../tracker';
import { ACTION_PREFIX, NAMESPACE } from '../constants';

addUniqueAction(
	`${ ACTION_PREFIX }-product-render`,
	NAMESPACE,
	tracker.eventHandler( 'view_item' )
);

addUniqueAction(
	`${ ACTION_PREFIX }-cart-remove-item`,
	NAMESPACE,
	tracker.eventHandler( 'remove_from_cart' )
);

addUniqueAction(
	`${ ACTION_PREFIX }-checkout-render-checkout-form`,
	NAMESPACE,
	tracker.eventHandler( 'begin_checkout' )
);

// These actions only works for All Products Block
addUniqueAction(
	`${ ACTION_PREFIX }-cart-add-item`,
	NAMESPACE,
	( { product } ) => {
		tracker.eventHandler( 'add_to_cart' )( { product } );
	}
);

addUniqueAction(
	`${ ACTION_PREFIX }-product-list-render`,
	NAMESPACE,
	tracker.eventHandler( 'view_item_list' )
);

addUniqueAction(
	`${ ACTION_PREFIX }-product-view-link`,
	NAMESPACE,
	tracker.eventHandler( 'select_content' )
);

/**
 * Remove additional actions added by WooCommerce Core which are either
 * not supported by Google Analytics for WooCommerce or are redundant
 * since Google retired Universal Analytics.
 */
removeAction( `${ ACTION_PREFIX }-checkout-submit`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-email-address`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-phone-number`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-billing-address`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-cart-set-item-quantity`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-product-search`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-store-notice-create`, NAMESPACE );
