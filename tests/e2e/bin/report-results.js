/**
 * Writes the E2E results to the GitHub job summary, and warns about each test
 * that passed only on its retry, so it gets a ticket instead of going unseen.
 *
 * Usage: node tests/e2e/bin/report-results.js tests/e2e/test-results/results.json
 */
const fs = require( 'fs' );

const file = process.argv[ 2 ];

if ( ! file || ! fs.existsSync( file ) ) {
	process.stdout.write( `No E2E results at ${ file }.\n` );
	process.exit( 0 );
}

const report = JSON.parse( fs.readFileSync( file, 'utf8' ) );
const retried = [];

const collect = ( suite ) => {
	( suite.specs || [] ).forEach( ( spec ) =>
		spec.tests
			.filter( ( test ) => test.status === 'flaky' )
			.forEach( () =>
				retried.push( {
					file: `tests/e2e/specs/${ spec.file }`,
					line: spec.line,
					title: spec.title,
				} )
			)
	);
	( suite.suites || [] ).forEach( collect );
};
( report.suites || [] ).forEach( collect );

const { expected, unexpected, flaky, skipped } = report.stats;
const lines = [
	'### E2E tests',
	'',
	`${ expected } passed, ${ unexpected } failed, ${ flaky } passed only on retry, ${ skipped } skipped.`,
];

if ( retried.length ) {
	lines.push(
		'',
		'These passed only on retry. File a ticket for each one:',
		'',
		...retried.map(
			( { file: path, line, title } ) =>
				`- ${ path }:${ line } ${ title }`
		)
	);
	retried.forEach( ( { file: path, line, title } ) =>
		process.stdout.write(
			`::warning file=${ path },line=${ line },title=E2E test passed only on retry::${ title }\n`
		)
	);
}

const summary = lines.join( '\n' ) + '\n';

if ( process.env.GITHUB_STEP_SUMMARY ) {
	fs.appendFileSync( process.env.GITHUB_STEP_SUMMARY, summary );
} else {
	process.stdout.write( summary );
}
