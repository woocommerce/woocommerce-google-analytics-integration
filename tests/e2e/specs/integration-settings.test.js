/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

test.use( { storageState: process.env.ADMINSTATE } );

test( 'Able to setup in WooCommerce > Settings > Integration', async ( {
	page,
} ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=integration&section=google_analytics'
	);

	await expect(
		page.getByRole( 'heading', { name: 'Google Analytics' } )
	).toBeVisible();
} );
