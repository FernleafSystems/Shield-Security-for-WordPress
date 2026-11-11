<?php
/**
 * Turns raw `wp plugin check --format=json` output into a readable report and
 * compares it with the accepted baseline (baseline.json beside this file).
 *
 * Usage: php report.php <plugin-check-output-file>
 * Env:   PLUGIN_CHECK_UPDATE_BASELINE=1    after reporting, rewrite baseline.json to accept every current finding
 *        PLUGIN_CHECK_REPORT_DIR           where findings.json is written (default /plugin-check-output)
 *        PLUGIN_CHECK_VERSION, PLUGIN_CHECK_MODE   recorded in the baseline
 *
 * Exit codes: 0 no findings beyond the baseline (always 0 when updating it), 1 new findings, 2 bad input.
 */
declare( strict_types=1 );

$checkOutputFile = $argv[ 1 ] ?? '';
if ( $checkOutputFile === '' || !is_file( $checkOutputFile ) ) {
	fwrite( STDERR, "Usage: php report.php <plugin-check-output-file>\n" );
	exit( 2 );
}

$baselineFile = __DIR__.'/baseline.json';
$reportDir = getenv( 'PLUGIN_CHECK_REPORT_DIR' ) ?: '/plugin-check-output';
$updateBaseline = getenv( 'PLUGIN_CHECK_UPDATE_BASELINE' ) === '1';
$version = (string)getenv( 'PLUGIN_CHECK_VERSION' );
$mode = (string)getenv( 'PLUGIN_CHECK_MODE' );

$findings = parseFindings( (string)file_get_contents( $checkOutputFile ) );

$baseline = is_file( $baselineFile ) ? json_decode( (string)file_get_contents( $baselineFile ), true ) : null;
$remaining = [];
foreach ( ( is_array( $baseline ) ? $baseline[ 'findings' ] ?? [] : [] ) as $entry ) {
	$remaining[ findingKey( $entry ) ] = (int)$entry[ 'count' ];
}
$baselineTotal = array_sum( $remaining );

$newFindings = [];
foreach ( $findings as $index => $finding ) {
	$key = findingKey( $finding );
	if ( ( $remaining[ $key ] ?? 0 ) > 0 ) {
		$remaining[ $key ]--;
		$findings[ $index ][ 'new' ] = false;
	}
	else {
		$findings[ $index ][ 'new' ] = true;
		$newFindings[] = $finding;
	}
}
$resolved = array_sum( $remaining );

writeJson( rtrim( $reportDir, '/\\' ).'/findings.json', $findings );

echo PHP_EOL.'Findings by rule:'.PHP_EOL;
$byRule = [];
foreach ( $findings as $finding ) {
	$rule = str_pad( $finding[ 'type' ], 8 ).' '.$finding[ 'code' ];
	$byRule[ $rule ] = ( $byRule[ $rule ] ?? 0 ) + 1;
}
arsort( $byRule );
foreach ( $byRule as $rule => $count ) {
	echo sprintf( "  %6d  %s\n", $count, $rule );
}

if ( $newFindings !== [] ) {
	echo PHP_EOL.'New findings (not in the baseline):'.PHP_EOL;
	foreach ( $newFindings as $finding ) {
		echo sprintf(
			"  [%s] %s:%d %s - %s\n",
			$finding[ 'type' ],
			$finding[ 'file' ],
			$finding[ 'line' ],
			$finding[ 'code' ],
			$finding[ 'message' ]
		);
		if ( $finding[ 'docs' ] !== '' ) {
			echo '      '.$finding[ 'docs' ].PHP_EOL;
		}
	}
}

$types = array_count_values( array_column( $findings, 'type' ) );
echo PHP_EOL.sprintf(
	"Plugin Check %s (%s mode): %d findings (%d errors, %d warnings).\n",
	$version,
	$mode,
	count( $findings ),
	$types[ 'ERROR' ] ?? 0,
	$types[ 'WARNING' ] ?? 0
);
if ( is_array( $baseline ) ) {
	echo sprintf(
		"Baseline: %d accepted, %d new, %d resolved since the baseline was recorded.\n",
		$baselineTotal,
		count( $newFindings ),
		$resolved
	);
	$baselineProfile = sprintf( '%s (%s mode)', $baseline[ 'plugin_check_version' ] ?? 'unknown', $baseline[ 'mode' ] ?? 'unknown' );
	$runProfile = sprintf( '%s (%s mode)', $version, $mode );
	if ( $baselineProfile !== $runProfile ) {
		echo sprintf( "Note: the baseline was recorded with Plugin Check %s, this run used %s.\n", $baselineProfile, $runProfile );
	}
	if ( $resolved > 0 && !$updateBaseline ) {
		echo "Refresh the baseline with --update-baseline to lock in the resolved findings.\n";
	}
}

if ( $updateBaseline ) {
	writeJson( $baselineFile, [
		'plugin_check_version' => $version,
		'mode'                 => $mode,
		'findings'             => buildBaselineEntries( $findings ),
	] );
	echo sprintf( "Baseline updated: all %d current findings accepted.\n", count( $findings ) );
	exit( 0 );
}

exit( $newFindings === [] ? 0 : 1 );

/**
 * Plugin Check prints a "FILE: <path>" line followed by a JSON array of that file's findings.
 *
 * @return array<int,array{type:string,code:string,file:string,line:int,column:int,message:string,docs:string}>
 */
function parseFindings( string $output ) :array {
	$findings = [];
	$file = 'unknown';
	foreach ( preg_split( '/\R/', $output ) ?: [] as $line ) {
		$line = trim( $line );
		if ( preg_match( '/^FILE:\s*(.+)$/', $line, $matches ) === 1 ) {
			$file = trim( $matches[ 1 ] );
			continue;
		}
		if ( $line === '' || $line[ 0 ] !== '[' ) {
			continue;
		}
		$items = json_decode( $line, true );
		foreach ( is_array( $items ) ? $items : [] as $item ) {
			if ( !is_array( $item ) ) {
				continue;
			}
			$findings[] = [
				'type'    => strtoupper( (string)( $item[ 'type' ] ?? '' ) ),
				'code'    => decodeText( $item[ 'code' ] ?? '' ),
				'file'    => $file,
				'line'    => (int)( $item[ 'line' ] ?? 0 ),
				'column'  => (int)( $item[ 'column' ] ?? 0 ),
				'message' => decodeText( $item[ 'message' ] ?? '' ),
				'docs'    => (string)( $item[ 'docs' ] ?? '' ),
			];
		}
	}
	return $findings;
}

function decodeText( $value ) :string {
	return html_entity_decode( (string)$value, ENT_QUOTES | ENT_HTML5 );
}

/**
 * Line numbers are left out so that unrelated edits don't invalidate the baseline.
 */
function findingKey( array $finding ) :string {
	return implode( '|', [ $finding[ 'type' ], $finding[ 'code' ], $finding[ 'file' ], $finding[ 'message' ] ] );
}

/**
 * @return array<int,array{type:string,code:string,file:string,message:string,count:int}>
 */
function buildBaselineEntries( array $findings ) :array {
	$entries = [];
	foreach ( $findings as $finding ) {
		$key = findingKey( $finding );
		if ( !isset( $entries[ $key ] ) ) {
			$entries[ $key ] = [
				'type'    => $finding[ 'type' ],
				'code'    => $finding[ 'code' ],
				'file'    => $finding[ 'file' ],
				'message' => $finding[ 'message' ],
				'count'   => 0,
			];
		}
		$entries[ $key ][ 'count' ]++;
	}
	ksort( $entries );
	return array_values( $entries );
}

function writeJson( string $path, array $data ) :void {
	$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( $json === false || file_put_contents( $path, $json."\n" ) === false ) {
		fwrite( STDERR, 'Unable to write '.$path.PHP_EOL );
		exit( 2 );
	}
}
