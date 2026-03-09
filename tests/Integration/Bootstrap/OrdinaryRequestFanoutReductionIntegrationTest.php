<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bootstrap;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\TracksOptionWrites;
use FernleafSystems\Wordpress\Services\Services;

class OrdinaryRequestFanoutReductionIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use TracksOptionWrites;

	private array $requestSnapshot = [];
	private array $optionSnapshots = [];
	private string $installDateOptionKey = '';
	private int $installDateOptionValue = 0;

	public function set_up() {
		parent::set_up();

		$con = $this->requireController();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->installDateOptionKey = $con->prefix( 'install_date', '_' );
		$this->installDateOptionValue = (int)Services::WpGeneral()->getOption( $this->installDateOptionKey );
		$this->optionSnapshots = [
			'importexport_enable'               => $con->opts->optGet( 'importexport_enable' ),
			'importexport_secretkey'            => $con->opts->optGet( 'importexport_secretkey' ),
			'importexport_secretkey_expires_at' => $con->opts->optGet( 'importexport_secretkey_expires_at' ),
			'enable_auto_integrations'          => $con->opts->optGet( 'enable_auto_integrations' ),
			'auto_integrations_track'           => $con->opts->optGet( 'auto_integrations_track' ),
			'installation_time'                 => $con->opts->optGet( 'installation_time' ),
		];

		$this->flushPendingOptionState();
	}

	public function tear_down() {
		$con = $this->requireController();

		foreach ( $this->optionSnapshots as $key => $value ) {
			$con->opts->optSet( $key, $value );
		}
		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}

		Services::WpGeneral()->updateOption( $this->installDateOptionKey, $this->installDateOptionValue );

		$this->stopTrackingOptionWrites();
		$this->restoreCurrentRequestState( $this->requestSnapshot );

		parent::tear_down();
	}

	public function test_public_request_does_not_generate_import_export_secret() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_secretkey', '' )
			->optSet( 'importexport_secretkey_expires_at', 0 )
			->store();

		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/',
		] );

		$con->comps->import_export->resetExecution()->execute();

		$this->assertSame( '', (string)$con->opts->optGet( 'importexport_secretkey' ) );
		$this->assertSame( 0, (int)$con->opts->optGet( 'importexport_secretkey_expires_at' ) );
		$this->assertFalse( $con->opts->hasChanges() );
	}

	public function test_public_request_does_not_run_auto_integrations() :void {
		$con = $this->requireController();
		$originalTrack = [
			'last_check_at' => 0,
			'profile_hash'  => '',
		];
		$con->opts
			->optSet( 'enable_auto_integrations', 'Y' )
			->optSet( 'auto_integrations_track', $originalTrack )
			->store();

		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/',
		] );

		$con->comps->integrations->resetExecution()->execute();

		$this->assertSame( $originalTrack, $con->opts->optGet( 'auto_integrations_track' ) );
		$this->assertFalse( $con->opts->hasChanges() );
	}

	public function test_store_real_install_date_is_idempotent_when_values_match() :void {
		$con = $this->requireController();
		$storedAt = Services::Request()->ts() - 600;

		Services::WpGeneral()->updateOption( $this->installDateOptionKey, $storedAt );
		$con->opts->optSet( 'installation_time', $storedAt )->store();

		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/',
		] );

		$this->startTrackingOptionWrites( [ $this->installDateOptionKey ] );

		$this->assertSame( $storedAt, $con->plugin->storeRealInstallDate() );
		$this->assertOptionWasNotWritten( $this->installDateOptionKey );
		$this->assertFalse( $con->opts->hasChanges() );
	}

	private function flushPendingOptionState() :void {
		$con = $this->requireController();
		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}
	}
}
