<?php

require_once '/app/tests/Helpers/RuntimeTestState.php';
require_once '/app/tests/Helpers/TestDataFactory.php';
require_once '/app/tests/Helpers/BrowserFixtureRegistry.php';
require_once '/app/tests/Helpers/ActionRouter/PluginAdminRouteRuntime.php';
require_once '/app/tests/Helpers/ActionRouter/ActionsQueueRuntimeProbe.php';
require_once '/app/tests/Helpers/ActionRouter/ActionsQueueFixtureBuilder.php';
require_once '/app/tests/Helpers/ActionRouter/DashboardDefaultsFixtureBuilder.php';
require_once '/app/tests/Helpers/ActionRouter/ImportExportFileFixtureBuilder.php';
require_once '/app/tests/Helpers/ActionRouter/IpAnalysisActivityMetaFixtureBuilder.php';
require_once '/app/tests/Helpers/ActionRouter/IpRulesTableFixtureBuilder.php';
require_once '/app/tests/Helpers/ActionRouter/MainwpSitesFixtureBuilder.php';
require_once '/app/tests/Helpers/ActionRouter/MerlinWelcomeFixtureBuilder.php';
require_once '/app/tests/Helpers/ActionRouter/MfaProfileFixtureBuilder.php';
require_once '/app/tests/Helpers/ActionRouter/NotBotAltchaFixtureBuilder.php';
require_once '/app/tests/Helpers/ActionRouter/PublicBlockRecoveryFixtureBuilder.php';
require_once '/app/tests/Helpers/ActionRouter/SecurityAdminFixtureBuilder.php';
require_once '/app/tests/Helpers/ActionRouter/SecurityHeadersFixtureBuilder.php';

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\BrowserFixtureRegistry;

$argsList = \is_array( $args ?? null ) ? $args : [];

if ( $argsList === [] ) {
	$argv = $_SERVER['argv'] ?? [];
	$dashdashIndex = \array_search( '--', $argv, true );
	if ( \is_int( $dashdashIndex ) ) {
		$argsList = \array_slice( $argv, $dashdashIndex + 1 );
	}
}

if ( ( $argsList[ 0 ] ?? null ) === '--' ) {
	\array_shift( $argsList );
}

$fixture = \trim( (string)( $argsList[ 0 ] ?? '' ) );
$action = \trim( (string)( $argsList[ 1 ] ?? '' ) );
$fixtureArgs = \array_values( \array_filter(
	\array_slice( $argsList, 2 ),
	static fn( $value ) :bool => \is_string( $value ) && $value !== ''
) );

if ( $fixture === '' || $action === '' ) {
	throw new \RuntimeException( 'Fixture and action are required.' );
}

echo \wp_json_encode( BrowserFixtureRegistry::run( $fixture, $action, $fixtureArgs ) );
