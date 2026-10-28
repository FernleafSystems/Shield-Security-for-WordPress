<?php declare( strict_types=1 );

// Internal child process for parallel lane preparation. BrowserTestLane holds the lease and owns cleanup.
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteDefinitions;
use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteManager;

$rootDir = \dirname( __DIR__, 4 );
require $rootDir.'/vendor/autoload.php';

try {
	$input = \json_decode( (string)\stream_get_contents( \STDIN ), true, 512, \JSON_THROW_ON_ERROR );
	exit( ( new LocalSiteManager( LocalSiteDefinitions::browserLane( (int)$input[ 'laneIndex' ] ) ) )->prepareBrowserLane(
		$rootDir,
		(string)$input[ 'mode' ],
		true,
		(string)$input[ 'fixtureToken' ],
		null,
		$input[ 'hostManifest' ],
		$input[ 'labelEnv' ]
	) );
}
catch ( \Throwable $error ) {
	\fwrite( \STDERR, $error->getMessage().\PHP_EOL );
	exit( 1 );
}
