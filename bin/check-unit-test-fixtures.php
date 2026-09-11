<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\FilesystemFixturePolicy;

$projectRoot = \dirname( __DIR__ );
require_once $projectRoot.'/tests/Helpers/FilesystemFixturePolicy.php';

$targets = \array_slice( $argv, 1 );
if ( $targets === [] ) {
	$targets = [ $projectRoot.'/tests/Unit' ];
}

$policy = new FilesystemFixturePolicy();
$violations = [];
foreach ( $targets as $target ) {
	$resolved = $target;
	if ( !\preg_match( '#^(?:[A-Za-z]:[\\\\/]|/)#', $target ) ) {
		$resolved = $projectRoot.'/'.\ltrim( $target, '/\\' );
	}
	if ( \is_dir( $resolved ) ) {
		$violations = \array_merge( $violations, $policy->scanDirectory( $resolved ) );
	}
	elseif ( \is_file( $resolved ) ) {
		$violations = \array_merge( $violations, $policy->scanFile( $resolved ) );
	}
	else {
		\fwrite( STDERR, 'Fixture policy target does not exist: '.$target.\PHP_EOL );
		exit( 2 );
	}
}

if ( $violations === [] ) {
	\fwrite( STDOUT, 'Unit-test filesystem fixture policy passed.'.\PHP_EOL );
	exit( 0 );
}

foreach ( $violations as $violation ) {
	$file = \str_replace( '\\', '/', $violation[ 'file' ] );
	$root = \str_replace( '\\', '/', $projectRoot ).'/';
	if ( \strpos( $file, $root ) === 0 ) {
		$file = \substr( $file, \strlen( $root ) );
	}
	\fwrite(
		STDERR,
		\sprintf(
			'%s:%d: %s Remediation: %s%s',
			$file,
			$violation[ 'line' ],
			$violation[ 'message' ],
			$violation[ 'remediation' ],
			\PHP_EOL
		)
	);
}
exit( 1 );
