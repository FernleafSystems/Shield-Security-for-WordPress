#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\Cli\LegacyCliAdapterRunner;
use Symfony\Component\Filesystem\Path;

require dirname( __DIR__ ).'/vendor/autoload.php';

const SHIELD_DOCKER_TEST_MODES = [
	'--source' => 'test:source',
	'--package-targeted' => 'test:package-targeted',
	'--package-full' => 'test:package-full',
	'--analyze-source' => 'analyze:source',
	'--analyze-package' => 'analyze:package',
];

$rootDir = Path::normalize( dirname( __DIR__ ) );
$args = \array_slice( $_SERVER[ 'argv' ] ?? [], 1 );
$adapter = new LegacyCliAdapterRunner();
exit(
	$adapter->run(
		$args,
		$rootDir,
		SHIELD_DOCKER_TEST_MODES,
		'test:source',
		'writeHelp'
	)
);

function writeHelp() :void {
	\fwrite( \STDOUT, 'Usage: php bin/run-docker-tests.php [--source|--package-targeted|--package-full|--analyze-source|--analyze-package]'.\PHP_EOL );
	\fwrite( \STDOUT, \PHP_EOL );
	\fwrite( \STDOUT, 'Modes:'.\PHP_EOL );
	\fwrite( \STDOUT, '  (default)         Source runtime checks against working tree'.\PHP_EOL );
	\fwrite( \STDOUT, '  --source          Source runtime checks against working tree'.\PHP_EOL );
	\fwrite( \STDOUT, '  --package-targeted Focused package validation checks'.\PHP_EOL );
	\fwrite( \STDOUT, '  --package-full    Full packaged Docker runtime checks'.\PHP_EOL );
	\fwrite( \STDOUT, '  --analyze-source  Source static analysis checks'.\PHP_EOL );
	\fwrite( \STDOUT, '  --analyze-package Packaged static analysis checks'.\PHP_EOL );
	\fwrite( \STDOUT, \PHP_EOL );
	\fwrite( \STDOUT, 'Primary CLI: php bin/shield <command>'.\PHP_EOL );
	\fwrite( \STDOUT, 'This script is a backwards-compatible adapter.'.\PHP_EOL );
}
