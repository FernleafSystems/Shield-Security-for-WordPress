<?php declare( strict_types=1 );
/**
 * Build plugin.json from modular spec files.
 *
 * Usage: php bin/build-config.php
 *
 * This script merges the 16 JSON files in plugin-spec/ into a single plugin.json.
 * It must be run before tests or any operations that require plugin.json.
 *
 * The generated plugin.json is gitignored and must be regenerated on each checkout.
 */

require __DIR__ . '/../vendor/autoload.php';

use FernleafSystems\ShieldPlatform\Tooling\ConfigMerger;

$root = dirname( __DIR__ );
$specDir = $root . '/plugin-spec';
$outputPath = $root . '/plugin.json';

try {
	( new ConfigMerger() )->mergeToFile( $specDir, $outputPath );
	echo "✓ plugin.json generated\n";
}
catch ( Throwable $e ) {
	echo "✗ Failed to generate plugin.json: {$e->getMessage()}\n";
	exit( 1 );
}

