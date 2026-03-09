<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bootstrap;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\TestRestFetchRequests;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\TracksOptionWrites;

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

		$this->startTrackingOptionWrites( $this->shieldTrackedOptionKeys() );

		\apply_filters( 'shield/custom_localisations', [], 'plugins.php', [ 'wpadmin' ] );
		$this->requireController()->onWpShutdown();

		foreach ( $this->shieldTrackedOptionKeys() as $option ) {
			$this->assertOptionWasNotWritten( $option );
		}
	}

	public function test_test_rest_action_still_persists_success_timestamp() :void {
		$con = $this->requireController();
		$this->loginAsSecurityAdmin();
		$con->opts->optSet( 'test_rest_data', [
			'maybe_test_at'   => 0,
			'success_test_at' => 0,
		] );
		$con->opts->store();

		$con->action_router->action( TestRestFetchRequests::class );
		$con->onWpShutdown();

		$data = $con->opts->optGet( 'test_rest_data' );
		$this->assertGreaterThan( 0, (int)( $data[ 'success_test_at' ] ?? 0 ) );
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
}
