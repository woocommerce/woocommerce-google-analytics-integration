/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import { apiWP } from '../utils/api';

test.use( { storageState: process.env.ADMINSTATE } );

const PLUGIN_SLUG = 'woocommerce-google-analytics-integration';
const PLUGIN_REST_ROUTE = `plugins/${ PLUGIN_SLUG }/${ PLUGIN_SLUG }`;

// A fatal during (de)activation surfaces as an error notice on the plugins
// screen, so asserting no error notice after each action is the observable
// signal. The suite runs with a single worker, so no other test runs while the
// plugin is briefly deactivated; the REST calls pin the starting state (an
// earlier failed run may have left the plugin off) and restore it on the way
// out even when an assertion fails.
test( 'Plugin deactivates and reactivates without errors', async ( {
	page,
} ) => {
	await apiWP().put( PLUGIN_REST_ROUTE, { status: 'active' } );

	try {
		await page.goto( '/wp-admin/plugins.php' );

		// The row's data-plugin attribute is always the plugin file path. The
		// action links' id attributes are avoided because their slug half
		// comes from the wp.org updates API and falls back to a different
		// value when the environment is offline.
		const pluginRow = page.locator(
			`tr[data-plugin="${ PLUGIN_SLUG }/${ PLUGIN_SLUG }.php"]`
		);
		const deactivateLink = pluginRow.getByRole( 'link', {
			name: /^Deactivate/,
		} );
		await expect( deactivateLink ).toBeVisible();
		await deactivateLink.click();

		await expect( page.getByText( 'Plugin deactivated.' ) ).toBeVisible();
		// Hidden notice templates ship with the admin markup, so only a
		// visible error notice counts.
		await expect( page.locator( '.notice-error:visible' ) ).toHaveCount(
			0
		);

		const activateLink = pluginRow.getByRole( 'link', {
			name: /^Activate/,
		} );
		await expect( activateLink ).toBeVisible();
		// Async admin notices keep shifting the layout after the deactivation
		// reload, which can keep the click's stability check from settling.
		// Navigating to the link's URL performs the same nonce-carrying
		// activation request without depending on layout stability.
		await page.goto( await activateLink.evaluate( ( el ) => el.href ) );

		await expect( page.getByText( 'Plugin activated.' ) ).toBeVisible();
		await expect( page.locator( '.notice-error:visible' ) ).toHaveCount(
			0
		);
	} finally {
		await apiWP().put( PLUGIN_REST_ROUTE, { status: 'active' } );
	}
} );
