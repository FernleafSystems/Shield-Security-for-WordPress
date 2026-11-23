<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bootstrap;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Services;

class OrdinaryRequestFanoutReductionIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];
	private array $optionSnapshots = [];

	public function set_up() {
		parent::set_up();

		$con = $this->requireController();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
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
			if ( $key === 'auto_integrations_track' ) {
				continue;
			}
			$con->opts->optSet( $key, $value );
		}
		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}
		$con->opts->optSet( 'auto_integrations_track', $this->optionSnapshots[ 'auto_integrations_track' ] );
		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}

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
		$this->enablePremiumCapabilities();
		$originalTrack = [
			'last_check_at' => 0,
			'profile_hash'  => '',
		];
		$con->opts->optSet( 'enable_auto_integrations', 'Y' )->store();
		$con->opts->optSet( 'auto_integrations_track', $originalTrack )->store();

		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/',
		] );

		$con->comps->integrations->resetExecution()->execute();

		$this->assertSame( $originalTrack, $con->opts->optGet( 'auto_integrations_track' ) );
		$this->assertFalse( $con->opts->hasChanges() );
	}

	public function test_admin_request_runs_auto_integrations_when_track_cleared() :void {
		$con = $this->requireController();
		$this->enablePremiumCapabilities();
		$con->opts
			->optSet( 'enable_auto_integrations', 'Y' )
			->optSet( 'auto_integrations_track', [] )
			->store();

		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD'  => 'GET',
				'REQUEST_URI'     => '/wp-admin/admin.php?page=shield',
				'SCRIPT_NAME'     => '/wp-admin/admin.php',
				'SCRIPT_FILENAME' => '/wp-admin/admin.php',
				'PHP_SELF'        => '/wp-admin/admin.php',
			],
			[],
			[],
			[
				'path'        => '/wp-admin/admin.php',
				'script_name' => 'admin.php',
				'wp_is_admin' => true,
				'wp_is_ajax'  => false,
				'wp_is_cron'  => false,
			]
		);

		$con->comps->integrations->resetExecution()->execute();

		$track = $con->opts->optGet( 'auto_integrations_track' );
		$this->assertIsArray( $track );
		$this->assertArrayHasKey( 'last_check_at', $track );
		$this->assertArrayHasKey( 'profile_hash', $track );
		$this->assertGreaterThan( 0, (int)$track[ 'last_check_at' ] );
		$this->assertIsString( $track[ 'profile_hash' ] );
		$this->assertNotSame( '', $track[ 'profile_hash' ] );
	}

	public function test_store_real_install_date_keeps_an_existing_date_without_changes() :void {
		$con = $this->requireController();
		$storedAt = Services::Request()->ts() - 600;
		$con->opts->optSet( 'installation_time', $storedAt )->store();

		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/',
		] );

		$this->assertSame( $storedAt, $con->plugin->storeRealInstallDate() );
		$this->assertFalse( $con->opts->hasChanges() );
	}

	/**
	 * Older versions also kept the install date in a separate WP option. Sites still have it, but after a reset
	 * the install date must start again rather than come back from that option.
	 */
	public function test_store_real_install_date_ignores_the_retired_wp_option() :void {
		$con = $this->requireController();
		$retiredKey = $con->prefix( 'install_date', '_' );
		$retiredValue = \get_option( $retiredKey, false );
		$retiredDate = Services::Request()->ts() - \YEAR_IN_SECONDS;
		\update_option( $retiredKey, $retiredDate );

		try {
			$con->opts->optSet( 'installation_time', 0 )->store();

			$installedAt = $con->plugin->storeRealInstallDate();

			$this->assertGreaterThan( $retiredDate, $installedAt );
			$this->assertSame( $installedAt, (int)$con->opts->optGet( 'installation_time' ) );
		}
		finally {
			$retiredValue === false ? \delete_option( $retiredKey ) : \update_option( $retiredKey, $retiredValue );
		}
	}

	private function flushPendingOptionState() :void {
		$con = $this->requireController();
		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}
	}
}
