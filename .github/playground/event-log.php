<?php
/**
 * Plugin Name: Google Analytics for WooCommerce – preview event log
 * Description: Lists the gtag commands of a visit in a panel on the storefront. For WordPress Playground previews of pull request builds only.
 *
 * The Playground setup copies this file to mu-plugins. See README.md in this folder.
 *
 * @package WC_Google_Analytics_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_footer',
	function () {
		// The plugin prints no tracking code for administrators.
		$hint = current_user_can( 'manage_options' )
			? 'No events: the plugin does not track administrators. Log out to see them.'
			: 'Waiting for the first event.';
		?>
		<style>
			#ga4w-preview-log { position: fixed; right: 12px; bottom: 12px; z-index: 99999; width: 360px; max-width: calc(100vw - 24px); max-height: 40vh; overflow: auto; padding: 8px 12px; border: 1px solid #1e1e1e; border-radius: 4px; background: #fff; color: #1e1e1e; font: 12px/1.5 Menlo, Consolas, monospace; box-shadow: 0 2px 8px rgba(0, 0, 0, .25); }
			#ga4w-preview-log > summary { position: sticky; top: -8px; padding: 4px 0; background: #fff; cursor: pointer; font-weight: 600; }
			#ga4w-preview-log ul { margin: 8px 0; padding: 0; list-style: none; }
			#ga4w-preview-log li { margin: 0; }
			#ga4w-preview-log li.ga4w-page { margin-top: 6px; color: #757575; }
			#ga4w-preview-log pre { margin: 0 0 4px; padding: 4px; background: #f0f0f0; font: inherit; white-space: pre-wrap; word-break: break-word; }
			#ga4w-preview-log button { font: inherit; }
		</style>
		<details id="ga4w-preview-log" open>
			<summary>Analytics events (<span id="ga4w-preview-count">0</span>)</summary>
			<p id="ga4w-preview-hint"><?php echo esc_html( $hint ); ?></p>
			<ul id="ga4w-preview-list"></ul>
			<button type="button" id="ga4w-preview-clear">Clear</button>
		</details>
		<script>
			( function () {
				// playground.wordpress.net serves each temporary store under /scope:<id>/.
				var scope = ( window.location.pathname.match( /^\/scope:[^/]+/ ) || [ '' ] )[ 0 ];
				var storageKey = 'ga4wPreviewEvents' + scope;
				var closedKey = 'ga4wPreviewClosed';
				var panel = document.getElementById( 'ga4w-preview-log' );
				var list = document.getElementById( 'ga4w-preview-list' );
				var hint = document.getElementById( 'ga4w-preview-hint' );
				var count = document.getElementById( 'ga4w-preview-count' );
				var entries = [];
				var seen = 0;

				// The log and the state of the panel last for the visit, across pages.
				try {
					entries = JSON.parse( window.sessionStorage.getItem( storageKey ) ) || [];
					panel.open = ! window.sessionStorage.getItem( closedKey );
				} catch ( e ) {}

				panel.addEventListener( 'toggle', function () {
					try {
						window.sessionStorage.setItem( closedKey, panel.open ? '' : '1' );
					} catch ( e ) {}
				} );

				function save() {
					try {
						window.sessionStorage.setItem( storageKey, JSON.stringify( entries.slice( -200 ) ) );
					} catch ( e ) {}
				}

				function render( entry ) {
					var item = document.createElement( 'li' );
					if ( entry.page ) {
						item.className = 'ga4w-page';
						item.textContent = entry.page;
					} else if ( entry.detail ) {
						var details = document.createElement( 'details' );
						var summary = document.createElement( 'summary' );
						var pre = document.createElement( 'pre' );
						summary.textContent = entry.title;
						pre.textContent = entry.detail;
						details.appendChild( summary );
						details.appendChild( pre );
						item.appendChild( details );
					} else {
						item.textContent = entry.title;
					}
					list.appendChild( item );
					if ( ! entry.page ) {
						hint.hidden = true;
						count.textContent = String( Number( count.textContent ) + 1 );
					}
				}

				function add( entry ) {
					entries.push( entry );
					render( entry );
					save();
					panel.scrollTop = panel.scrollHeight;
				}

				// gtag() pushes its arguments object: [ command, name or target, parameters ].
				function describe( pushed ) {
					var args = pushed && typeof pushed.length === 'number' ? Array.prototype.slice.call( pushed ) : null;
					var detail = '';
					if ( ! args ) {
						// gtag.js adds its own lifecycle entries, such as gtm.dom.
						if ( ! pushed || /^gtm\./.test( String( pushed.event ) ) ) {
							return null;
						}
						args = [ 'push', pushed ];
					}
					try {
						detail = JSON.stringify( args[ args.length - 1 ], null, 2 );
					} catch ( e ) {}
					if ( typeof args[ args.length - 1 ] !== 'object' ) {
						return { title: args.join( ' ' ) };
					}
					return { title: args.slice( 0, -1 ).join( ' ' ), detail: detail };
				}

				entries.forEach( render );
				add( { page: window.location.pathname.slice( scope.length ) } );

				window.setInterval( function () {
					var layer = window.dataLayer;
					if ( ! Array.isArray( layer ) ) {
						return;
					}
					for ( ; seen < layer.length; seen++ ) {
						var entry = describe( layer[ seen ] );
						if ( entry ) {
							add( entry );
						}
					}
				}, 250 );

				document.getElementById( 'ga4w-preview-clear' ).addEventListener( 'click', function () {
					entries = [];
					save();
					list.textContent = '';
					count.textContent = '0';
					hint.hidden = false;
				} );
			} )();
		</script>
		<?php
	},
	100
);
