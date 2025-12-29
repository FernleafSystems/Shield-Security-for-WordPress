<?php declare( strict_types=1 );

/**
 * Helper tool to generate a manifest snapshot (hash + size) for select deterministic files
 * in a built Shield package. Not run during tests; invoke manually when updating Strauss output.
 *
 * Usage:
 *   php tools/build-packager-manifest.php --package-dir=/path/to/package --output=tests/fixtures/packager/expected-manifest.json
 */

if ( PHP_SAPI !== 'cli' ) {
	echo "Run from CLI.\n";
	exit( 1 );
}

$options = getopt( '', [ 'package-dir:', 'output:' ] );
$packageDir = $options[ 'package-dir' ] ?? null;
$output = $options[ 'output' ] ?? null;

if ( !is_string( $packageDir ) || $packageDir === '' || !is_dir( $packageDir ) ) {
	echo "Usage: php tools/build-packager-manifest.php --package-dir=/path/to/package --output=tests/fixtures/packager/expected-manifest.json\n";
	exit( 1 );
}

if ( !is_string( $output ) || $output === '' ) {
	echo "Output path is required (--output).\n";
	exit( 1 );
}

$files = [
	'src/lib/vendor_prefixed/autoload.php',
	'src/lib/vendor_prefixed/autoload-classmap.php',
	'src/lib/vendor/composer/autoload_files.php',
	'src/lib/vendor/composer/autoload_psr4.php',
	'src/lib/vendor/composer/autoload_static.php',
];

$manifest = [ 'files' => [] ];

foreach ( $files as $rel ) {
	$path = rtrim( $packageDir, '/\\' ).DIRECTORY_SEPARATOR.$rel;
	if ( !file_exists( $path ) ) {
		echo "Warning: missing $rel\n";
		continue;
	}
	$manifest[ 'files' ][ $rel ] = [
		'sha256' => hash_file( 'sha256', $path ),
		'size'   => filesize( $path ),
	];
}

$json = json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ).PHP_EOL;
if ( file_put_contents( $output, $json ) === false ) {
	echo "Failed to write manifest to $output\n";
	exit( 1 );
}

echo "Manifest written to $output\n";

