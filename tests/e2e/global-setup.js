/**
 * Internal dependencies
 */
const { admin, customer } = require( './config/default.json' ).users;
const { LOAD_STATE } = require( './utils/constants' );

/**
 * External dependencies
 */
const { chromium, expect } = require( '@playwright/test' );
const fs = require( 'fs' );

/* eslint-disable no-console */
module.exports = async ( config ) => {
	const { stateDir, baseURL, userAgent } = config.projects[ 0 ].use;

	console.log( `State Dir: ${ stateDir }` );
	console.log( `Base URL: ${ baseURL }` );

	// used throughout tests for authentication
	process.env.ADMINSTATE = `${ stateDir }adminState.json`;
	process.env.CUSTOMERSTATE = `${ stateDir }customerState.json`;

	// Clear out the previous save states
	try {
		fs.unlinkSync( process.env.ADMINSTATE );
		console.log( 'Admin state file deleted successfully.' );
	} catch ( err ) {
		if ( err.code === 'ENOENT' ) {
			console.log( 'Admin state file does not exist.' );
		} else {
			console.error( 'Admin state file could not be deleted: ' + err );
		}
	}
	try {
		fs.unlinkSync( process.env.CUSTOMERSTATE );
		console.log( 'Customer state file deleted successfully.' );
	} catch ( err ) {
		if ( err.code === 'ENOENT' ) {
			console.log( 'Customer state file does not exist.' );
		} else {
			console.log( 'Customer state file could not be deleted: ' + err );
		}
	}

	// Pre-requisites
	let adminLoggedIn = false;
	let customerLoggedIn = false;

	// Specify user agent when running against an external test site to avoid getting HTTP 406 NOT ACCEPTABLE errors.
	const contextOptions = { baseURL, userAgent };

	// Create browser, browserContext, and page for customer and admin users
	const browser = await chromium.launch();
	const adminContext = await browser.newContext( contextOptions );
	const customerContext = await browser.newContext( contextOptions );
	const adminPage = await adminContext.newPage();
	const customerPage = await customerContext.newPage();

	// Sign in as admin user and save state
	const adminRetries = 5;
	for ( let i = 0; i < adminRetries; i++ ) {
		try {
			console.log( 'Trying to log-in as admin...' );
			await adminPage.goto( `/wp-admin` );
			await adminPage
				.locator( 'input[name="log"]' )
				.fill( admin.username );
			await adminPage
				.locator( 'input[name="pwd"]' )
				.fill( admin.password );
			await adminPage.locator( 'text=Log In' ).click();
			await adminPage.waitForLoadState( LOAD_STATE.DOM_CONTENT_LOADED );
			await adminPage.goto( `/wp-admin` );
			await adminPage.waitForLoadState( LOAD_STATE.DOM_CONTENT_LOADED );

			await expect( adminPage.locator( 'div.wrap > h1' ) ).toHaveText(
				'Dashboard'
			);
			await adminPage
				.context()
				.storageState( { path: process.env.ADMINSTATE } );
			console.log( 'Logged-in as admin successfully.' );
			adminLoggedIn = true;
			break;
		} catch ( e ) {
			console.warn(
				`Admin log-in failed, Retrying... ${ i }/${ adminRetries }`
			);
			console.warn( e );
		}
	}

	if ( ! adminLoggedIn ) {
		console.error(
			'Cannot proceed e2e test, as admin login failed. Please check if the test site has been setup correctly.'
		);
		process.exit( 1 );
	}

	// Sign in as customer user and save state
	const customerRetries = 5;
	for ( let i = 0; i < customerRetries; i++ ) {
		try {
			console.log( 'Trying to log-in as customer...' );
			await customerPage.goto( `/wp-admin` );
			await customerPage
				.locator( 'input[name="log"]' )
				.fill( customer.username );
			await customerPage
				.locator( 'input[name="pwd"]' )
				.fill( customer.password );
			await customerPage.locator( 'text=Log In' ).click();

			await customerPage.goto( `/my-account` );
			await expect(
				customerPage.locator(
					'.woocommerce-MyAccount-navigation-link--customer-logout'
				)
			).toBeVisible();
			await expect(
				customerPage.locator(
					'div.woocommerce-MyAccount-content > p >> nth=0'
				)
			).toContainText( 'Hello' );

			await customerPage
				.context()
				.storageState( { path: process.env.CUSTOMERSTATE } );
			console.log( 'Logged-in as customer successfully.' );
			customerLoggedIn = true;
			break;
		} catch ( e ) {
			console.log(
				`Customer log-in failed. Retrying... ${ i }/${ customerRetries }`
			);
			console.log( e );
		}
	}

	if ( ! customerLoggedIn ) {
		console.error(
			'Cannot proceed e2e test, as customer login failed. Please check if the test site has been setup correctly.'
		);
		process.exit( 1 );
	}

	await adminContext.close();
	await browser.close();
};
