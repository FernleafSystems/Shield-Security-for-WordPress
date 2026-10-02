<?php declare( strict_types=1 );

// Internal child process. BrowserTestLane retains the lease and owns cleanup.
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteDefinitions;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;

$rootDir = \dirname( __DIR__, 4 );
require $rootDir.'/vendor/autoload.php';

try {
	$input = \json_decode( \stream_get_contents( \STDIN ), true, 512, \JSON_THROW_ON_ERROR );
	exit( ( new LocalSiteManager( LocalSiteDefinitions::browserLane( $input[ 'laneIndex' ] ) ) )->prepareBrowserLane(
		$rootDir,
		$input[ 'mode' ],
		$input[ 'requirePlaywright' ],
		$input[ 'fixtureToken' ],
		null,
		$input[ 'hostManifest' ],
		$input[ 'labelEnv' ],
		true
	) );
}
catch ( \Throwable $error ) {
	\fwrite( \STDERR, $error->getMessage().\PHP_EOL );
	exit( 1 );
}
