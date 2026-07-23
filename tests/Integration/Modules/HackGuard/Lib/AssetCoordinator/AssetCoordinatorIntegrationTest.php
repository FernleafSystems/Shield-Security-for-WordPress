<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard\Lib\AssetCoordinator;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator\AssetCoordinator;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class AssetCoordinatorIntegrationTest extends ShieldIntegrationTestCase {

	private const PLUGIN = 'shield-coordinator-test/coordinator.php';
	private const THEME = 'shield-coordinator-theme';

	private ?AssetCoordinator $coordinator = null;

	public function set_up() {
		parent::set_up();
		$this->coordinator = $this->requireController()->comps->asset_coordinator;
		$this->coordinator->deleteState();
		$this->clearCoordinatorCrons();
	}

	public function tear_down() {
		if ( $this->coordinator !== null ) {
			$this->coordinator->deleteState();
		}
		$this->clearCoordinatorCrons();
		parent::tear_down();
	}

	public function test_state_uses_only_dedicated_non_autoloaded_option() :void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site option persistence contract.' );
		}
		global $wpdb;
		$con = $this->requireController();
		$stateKey = $con->prefix( 'asset_coordinator_state' );
		$optsAllKey = $con->prefix( 'opts_all', '_' );
		$optsAllBefore = get_option( $optsAllKey, null );
		$now = \FernleafSystems\Wordpress\Services\Services::Request()->ts();

		$this->assertTrue( $this->coordinator->enqueueAsset( 'plugin', self::PLUGIN, 60 ) );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s",
				$stateKey
			),
			ARRAY_A
		);
		$this->assertIsArray( $row );
		$this->assertContains( $row[ 'autoload' ], [ 'no', 'off', 'auto-off' ] );
		$this->assertSame( $optsAllBefore, get_option( $optsAllKey, null ) );
		$this->assertSame( [
			'attempts' => 0,
			'due_at'   => $now + 60,
		], get_option( $stateKey )[ 'assets' ][ 'plugin' ][ self::PLUGIN ] );

		$this->coordinator->deleteState();
		$this->assertFalse( get_option( $stateKey, false ) );
	}

	public function test_real_cron_import_merges_before_exact_unscheduling() :void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site legacy import persistence contract.' );
		}
		$con = $this->requireController();
		$now = \FernleafSystems\Wordpress\Services\Services::Request()->ts();
		$afsHook = $con->prefix( 'afs_asset_change_cleanup' );
		$buildHook = $con->prefix( 'ptg_build_snapshots' );
		$wpvHook = $con->prefix( 'ondemand_scan_wpv' );

		wp_schedule_single_event( $now + 40, $afsHook, [ 'plugin', self::PLUGIN, 1 ] );
		wp_schedule_single_event( $now + 20, $afsHook, [ 'plugin', self::PLUGIN, 0 ] );
		wp_schedule_single_event( $now + 30, $afsHook, [ 'theme', self::THEME, 0 ] );
		wp_schedule_single_event( $now + 50, $buildHook );
		wp_schedule_single_event( $now + 10, $wpvHook );

		$method = new \ReflectionMethod( AssetCoordinator::class, 'importLegacyCrons' );
		$method->setAccessible( true );
		$method->invoke( $this->coordinator );

		$state = get_option( $con->prefix( 'asset_coordinator_state' ) );
		$this->assertSame(
			[ 'attempts' => 0, 'due_at' => $now + 20 ],
			$state[ 'assets' ][ 'plugin' ][ self::PLUGIN ]
		);
		$this->assertSame(
			[ 'attempts' => 0, 'due_at' => $now + 30 ],
			$state[ 'assets' ][ 'theme' ][ self::THEME ]
		);
		$this->assertTrue( $state[ 'build_missing_snapshots' ] );
		$this->assertSame( [ 'attempts' => 0, 'due_at' => $now + 10 ], $state[ 'wpv' ] );
		$this->assertFalse( wp_next_scheduled( $afsHook, [ 'plugin', self::PLUGIN, 1 ] ) );
		$this->assertFalse( wp_next_scheduled( $afsHook, [ 'plugin', self::PLUGIN, 0 ] ) );
		$this->assertFalse( wp_next_scheduled( $afsHook, [ 'theme', self::THEME, 0 ] ) );
		$this->assertFalse( wp_next_scheduled( $buildHook ) );
		$this->assertFalse( wp_next_scheduled( $wpvHook ) );
	}

	public function test_multisite_state_uses_current_network_site_option_only() :void {
		if ( !is_multisite() ) {
			$this->markTestSkipped( 'Requires the bounded multisite integration mode.' );
		}
		$key = $this->requireController()->prefix( 'asset_coordinator_state' );

		$this->assertTrue( $this->coordinator->enqueueAsset( 'plugin', self::PLUGIN, 60 ) );

		$this->assertIsArray( get_site_option( $key, null ) );
		$this->assertFalse( get_option( $key, false ) );
	}

	private function clearCoordinatorCrons() :void {
		$con = static::con();
		if ( $con === null ) {
			return;
		}
		foreach ( [
			$con->prefix( 'asset_coordinator' ),
			$con->prefix( 'afs_asset_change_cleanup' ),
			$con->prefix( 'ptg_build_snapshots' ),
			$con->prefix( 'ondemand_scan_wpv' ),
		] as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
}
