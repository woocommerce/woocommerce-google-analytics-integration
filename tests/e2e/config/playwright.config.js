const { defineConfig, devices } = require( '@playwright/test' );
const { url } = require( './default.json' );

module.exports = defineConfig( {
	testDir: '../specs',

	/* Maximum time in milliseconds one test can run for. */
	timeout: 120 * 1000,

	expect: {
		/**
		 * Maximum time in milliseconds, expect() should wait for the condition to be met.
		 * For example in `await expect(locator).toHaveText();`
		 */
		timeout: 20 * 1000,
	},

	/* Number of workers */
	workers: 1,

	/* Retry on CI only */
	retries: process.env.CI ? 2 : 0,

	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: [
		[ 'list' ],
		[
			'html',
			{
				outputFolder: '../test-results/playwright-report',
				open: 'never',
			},
		],
	],

	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
	use: {
		/* Maximum time in milliseconds, each action such as `click()` can take. Defaults to 0 (no limit). */
		actionTimeout: 0,

		/* Base URL to use in actions like `await page.goto('/')`. */
		baseURL: url,

		/* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
		trace: 'retain-on-failure',

		screenshot: 'only-on-failure',
		stateDir: 'tests/e2e/test-results/storage/',
		video: 'on-first-retry',
		viewport: { width: 1280, height: 720 },
	},

	/* Configure projects for major browsers */
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],

	/* Folder for test artifacts such as screenshots, videos, traces, etc. */
	outputDir: '../test-results/report',

	/* Global setup and teardown scripts. */
	globalSetup: require.resolve( '../global-setup' ),

	/* Maximum number of tests to run in parallel. */
	maxFailures: 10,
} );
