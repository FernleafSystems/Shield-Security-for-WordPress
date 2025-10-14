#!/usr/bin/env php
<?php
declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

require dirname( __DIR__ ).'/vendor/autoload.php';

$options = getopt( '', [
    'output::',
    'skip-root-composer',
    'skip-lib-composer',
    'skip-npm-install',
    'skip-npm-build',
] );

$outputDir = $options['output'] ?? null;
$packagerOptions = [];

if ( isset( $options['skip-root-composer'] ) ) {
    $packagerOptions['composer_root'] = false;
}

if ( isset( $options['skip-lib-composer'] ) ) {
    $packagerOptions['composer_lib'] = false;
}

if ( isset( $options['skip-npm-install'] ) ) {
    $packagerOptions['npm_install'] = false;
}

if ( isset( $options['skip-npm-build'] ) ) {
    $packagerOptions['npm_build'] = false;
}

try {
    $packager = new PluginPackager();
    $path = $packager->package( is_string( $outputDir ) ? $outputDir : null, $packagerOptions );
	echo '✅ Shield plugin package created at: '.$path.PHP_EOL;
	exit( 0 );
}
catch ( Throwable $throwable ) {
	fwrite( STDERR, '❌ Packaging failed: '.$throwable->getMessage().PHP_EOL );
	exit( 1 );
}
