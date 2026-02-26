#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\Cli\LegacyCliAdapterRunner;
use Symfony\Component\Filesystem\Path;

require dirname( __DIR__ ).'/vendor/autoload.php';

const SHIELD_STATIC_ANALYSIS_MODES = [
	'--source' => 'analyze:source',
	'--package' => 'analyze:package',
];

$rootDir = Path::normalize( dirname( __DIR__ ) );
$args = \array_slice( $_SERVER[ 'argv' ] ?? [], 1 );
$adapter = new LegacyCliAdapterRunner();
exit(
	$adapter->run(
		$args,
		$rootDir,
		SHIELD_STATIC_ANALYSIS_MODES,
		'analyze:source',
		'writeHelp'
	)
);

function writeHelp() :void {
	\fwrite( \STDOUT, 'Usage: php bin/run-static-analysis.php [--source|--package]'.\PHP_EOL );
	\fwrite( \STDOUT, \PHP_EOL );
	\fwrite( \STDOUT, 'Modes:'.\PHP_EOL );
	\fwrite( \STDOUT, '  (default) Source static analysis checks'.\PHP_EOL );
	\fwrite( \STDOUT, '  --source  Source static analysis checks'.\PHP_EOL );
	\fwrite( \STDOUT, '  --package Packaged static analysis checks'.\PHP_EOL );
	\fwrite( \STDOUT, \PHP_EOL );
	\fwrite( \STDOUT, 'Primary CLI: php bin/shield <command>'.\PHP_EOL );
	\fwrite( \STDOUT, 'This script is a backwards-compatible adapter.'.\PHP_EOL );
}
