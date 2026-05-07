<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bootstrap;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\TestRestFetchRequests;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\TracksOptionWrites;
use FernleafSystems\Wordpress\Services\Services;

class ShieldBootstrapWriteSuppressionIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use TracksOptionWrites;

	private array $originalTestRestData = [];

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->originalTestRestData = (array)$this->requireController()->opts->optGet( 'test_rest_data' );
		$this->flushShieldPersistenceState();
	}

	public function tear_down() {
		$con = $this->requireController();
		$con->opts->optSet( 'test_rest_data', $this->originalTestRestData );
		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}
		$this->stopTrackingOptionWrites();
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_front_request_shutdown_does_not_write_shield_options() :void {
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/',
		] );

		$this->startTrackingOptionWrites( $this->shieldTrackedOptionKeys() );

		$this->requireController()->onWpShutdown();

		foreach ( $this->shieldTrackedOptionKeys() as $option ) {
			$this->assertOptionWasNotWritten( $option );
		}
	}

	public function test_generic_admin_localisation_and_shutdown_do_not_write_shield_options() :void {
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/wp-admin/plugins.php',
			'SCRIPT_NAME'    => '/wp-admin/plugins.php',
			'PHP_SELF'       => '/wp-admin/plugins.php',
		] );
		$this->setTestRestData( [
			TestRestFetchRequests::DATA_MAYBE_TEST_AT   => Services::Request()->ts(),
			TestRestFetchRequests::DATA_SUCCESS_TEST_AT => 0,
		] );

		$this->startTrackingOptionWrites( $this->shieldTrackedOptionKeys() );

		$testRest = $this->localiseWpAdminTestRestComponent();
		$this->requireController()->onWpShutdown();

		$this->assertFalse( (bool)( $testRest[ 'flags' ][ 'can_run' ] ?? true ) );
		foreach ( $this->shieldTrackedOptionKeys() as $option ) {
			$this->assertOptionWasNotWritten( $option );
		}
	}

	public function test_due_test_rest_localisation_marks_attempt_once() :void {
		$now = Services::Request()->ts();
		$this->setTestRestData( [
			TestRestFetchRequests::DATA_MAYBE_TEST_AT   => $now - \DAY_IN_SECONDS - 1,
			TestRestFetchRequests::DATA_SUCCESS_TEST_AT => 0,
		] );

		$this->startTrackingOptionWrites( $this->shieldTrackedOptionKeys() );

		$testRest = $this->localiseWpAdminTestRestComponent();

		$this->assertTrue( (bool)( $testRest[ 'flags' ][ 'can_run' ] ?? false ) );
		$this->assertShieldOptionsWriteCount( 1 );
		$data = $this->requireController()->opts->optGet( TestRestFetchRequests::OPT_KEY );
		$this->assertGreaterThanOrEqual( $now, (int)( $data[ TestRestFetchRequests::DATA_MAYBE_TEST_AT ] ?? 0 ) );

		$this->stopTrackingOptionWrites();
		$this->startTrackingOptionWrites( $this->shieldTrackedOptionKeys() );

		$testRest = $this->localiseWpAdminTestRestComponent();

		$this->assertFalse( (bool)( $testRest[ 'flags' ][ 'can_run' ] ?? true ) );
		$this->assertShieldOptionsWriteCount( 0 );
	}

	public function test_recent_attempt_suppresses_test_rest_even_without_success() :void {
		$this->setTestRestData( [
			TestRestFetchRequests::DATA_MAYBE_TEST_AT   => Services::Request()->ts(),
			TestRestFetchRequests::DATA_SUCCESS_TEST_AT => 0,
		] );
		$this->startTrackingOptionWrites( $this->shieldTrackedOptionKeys() );

		$testRest = $this->localiseWpAdminTestRestComponent();

		$this->assertFalse( (bool)( $testRest[ 'flags' ][ 'can_run' ] ?? true ) );
		$this->assertShieldOptionsWriteCount( 0 );
	}

	public function test_old_attempt_permits_test_rest_regardless_of_recent_success() :void {
		$now = Services::Request()->ts();
		$this->setTestRestData( [
			TestRestFetchRequests::DATA_MAYBE_TEST_AT   => $now - \DAY_IN_SECONDS - 1,
			TestRestFetchRequests::DATA_SUCCESS_TEST_AT => $now,
		] );
		$this->startTrackingOptionWrites( $this->shieldTrackedOptionKeys() );

		$testRest = $this->localiseWpAdminTestRestComponent();

		$this->assertTrue( (bool)( $testRest[ 'flags' ][ 'can_run' ] ?? false ) );
		$this->assertShieldOptionsWriteCount( 1 );
	}

	public function test_test_rest_action_still_persists_success_timestamp() :void {
		$con = $this->requireController();
		$this->loginAsSecurityAdmin();
		$this->setTestRestData( [
			TestRestFetchRequests::DATA_MAYBE_TEST_AT   => 0,
			TestRestFetchRequests::DATA_SUCCESS_TEST_AT => 0,
		] );

		$con->action_router->action( TestRestFetchRequests::class );
		$con->onWpShutdown();

		$data = $con->opts->optGet( TestRestFetchRequests::OPT_KEY );
		$this->assertGreaterThan( 0, (int)( $data[ TestRestFetchRequests::DATA_SUCCESS_TEST_AT ] ?? 0 ) );
	}

	private function flushShieldPersistenceState() :void {
		$con = $this->requireController();
		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}
		$con->cfg->persist_required = false;
		$this->setControllerPrechecks( null );
		$con->comps->assets_customizer->execute();
	}

	private function shieldTrackedOptionKeys() :array {
		$con = $this->requireController();
		return [
			$con->prefix( 'opts_all', '_' ),
			$con->prefix( 'ip_rules_cache', '_' ),
			'aptoweb_controller_'.\substr( \hash( 'md5', \get_class( $con ) ), 0, 6 ),
		];
	}

	private function setControllerPrechecks( ?array $prechecks ) :void {
		$ref = new \ReflectionClass( $this->requireController() );
		$prop = $ref->getProperty( 'prechecks' );
		$prop->setAccessible( true );
		$prop->setValue( $this->requireController(), $prechecks );
	}

	private function setTestRestData( array $data ) :void {
		$con = $this->requireController();
		$con->opts->optSet( TestRestFetchRequests::OPT_KEY, $data );
		$con->opts->store();
	}

	private function localiseWpAdminTestRestComponent() :array {
		$locals = \apply_filters( 'shield/custom_localisations', [], 'plugins.php', [ 'wpadmin' ] );
		foreach ( \is_array( $locals ) ? $locals : [] as $local ) {
			if ( \is_array( $local ) && ( $local[ 0 ] ?? '' ) === 'wpadmin' ) {
				$testRest = $local[ 2 ][ 'comps' ][ 'testrest' ] ?? [];
				$this->assertIsArray( $testRest );
				return $testRest;
			}
		}

		$this->fail( 'Missing wpadmin testrest component.' );
	}

	private function assertShieldOptionsWriteCount( int $expected ) :void {
		$optsAll = $this->requireController()->prefix( 'opts_all', '_' );
		$writes = \array_filter(
			$this->getTrackedOptionWrites(),
			fn( array $write ) => ( $write[ 'option' ] ?? '' ) === $optsAll
		);
		$this->assertCount( $expected, $writes );
	}
}
