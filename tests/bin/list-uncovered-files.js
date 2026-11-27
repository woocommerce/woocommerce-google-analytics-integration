/**
 * Prints a Markdown summary of a PHPUnit clover report: the total line
 * coverage and the plugin files no test reaches. It only reports.
 *
 * Usage: node tests/bin/list-uncovered-files.js clover.xml [base-path]
 */
const fs = require( 'fs' );

const [ file, basePath = process.cwd() ] = process.argv.slice( 2 );

if ( ! file || ! fs.existsSync( file ) ) {
	process.stderr.write(
		'Usage: node tests/bin/list-uncovered-files.js clover.xml [base-path]\n'
	);
	process.exit( 1 );
}

const base = basePath.replace( /\/?$/, '/' );
const clover = fs.readFileSync( file, 'utf8' );
let total = 0;
let covered = 0;
const uncovered = [];

// Each <file> ends with its own <metrics>, after those of its classes.
for ( const [ , name, body ] of clover.matchAll(
	/<file name="([^"]+)">([\s\S]*?)<\/file>/g
) ) {
	const metrics =
		[ ...body.matchAll( /<metrics [^>]*>/g ) ].pop()?.[ 0 ] ?? '';
	const count = ( attribute ) =>
		Number(
			metrics.match( new RegExp( ` ${ attribute }="(\\d+)"` ) )?.[ 1 ] ??
				0
		);
	const statements = count( 'statements' );
	const hits = count( 'coveredstatements' );

	total += statements;
	covered += hits;

	if ( statements > 0 && hits === 0 ) {
		uncovered.push( name.replace( base, '' ) );
	}
}

uncovered.sort();

const percent = total ? ( ( 100 * covered ) / total ).toFixed( 1 ) : '0.0';
process.stdout.write(
	[
		`### PHP line coverage: ${ percent }% (${ covered } of ${ total } lines)`,
		'',
		`${ uncovered.length } files have no covered lines:`,
		'',
		...uncovered.map( ( path ) => `- \`${ path }\`` ),
		'',
	].join( '\n' )
);
