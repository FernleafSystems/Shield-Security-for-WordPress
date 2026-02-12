<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\AllowBetaUpgrades;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldWordPressTestCase;
use FernleafSystems\Wordpress\Services\Services;

class AllowBetaUpgradesIntegrationTest extends ShieldWordPressTestCase {

	private string $baseFile = '';

	private string $currentVersion = '';

	private array $filterBackups = [];

	private function registerAllowBetaTransientHooks() :AllowBetaUpgrades {
		$subject = new AllowBetaUpgrades();
		$reflection = new \ReflectionClass( $subject );
		$run = $reflection->getMethod( 'run' );
		$run->setAccessible( true );
		$run->invoke( $subject );
		return $subject;
	}

	public function set_up() {
		parent::set_up();

		$con = self::con();
		$this->assertNotNull( $con, 'Controller must be available for integration tests.' );

		$this->baseFile = $con->base_file;
		$this->currentVersion = $con->cfg->version();
		$this->backupFilters( [
			'site_transient_update_plugins',
			'pre_set_site_transient_update_plugins',
			'shield/enable_beta',
		] );
	}

	public function tear_down() {
		\delete_site_transient( 'update_plugins' );
		$this->restoreFilters();
		parent::tear_down();
	}

	private function backupFilters( array $hooks ) :void {
		global $wp_filter;

		foreach ( $hooks as $hook ) {
			$current = $wp_filter[ $hook ] ?? null;
			$this->filterBackups[ $hook ] = \is_object( $current ) ? clone $current : $current;
		}
	}

	private function restoreFilters() :void {
		global $wp_filter;

		foreach ( $this->filterBackups as $hook => $backup ) {
			if ( $backup === null ) {
				unset( $wp_filter[ $hook ] );
			}
			else {
				$wp_filter[ $hook ] = $backup;
			}
		}
	}

	private function createUpdates( array $response ) :\stdClass {
		$updates = new \stdClass();
		$updates->response = $response;
		return $updates;
	}

	public function testSiteTransientFilterRemovesStaleSelfEntryAndClearsAvailability() :void {
		$this->registerAllowBetaTransientHooks();

		$updates = $this->createUpdates( [
			$this->baseFile => (object)[
				'new_version' => $this->currentVersion,
			],
		] );
		\set_site_transient( 'update_plugins', $updates );

		$filtered = \get_site_transient( 'update_plugins' );
		$this->assertIsObject( $filtered );
		$this->assertIsArray( $filtered->response );
		$this->assertArrayNotHasKey( $this->baseFile, $filtered->response );
		$this->assertFalse( Services::WpPlugins()->isUpdateAvailable( $this->baseFile ) );
	}

	public function testSiteTransientFilterKeepsValidHigherVersionEntry() :void {
		$this->registerAllowBetaTransientHooks();

		$higherVersion = $this->currentVersion.'.1';
		$updates = $this->createUpdates( [
			$this->baseFile => (object)[
				'new_version' => $higherVersion,
			],
		] );
		\set_site_transient( 'update_plugins', $updates );

		$filtered = \get_site_transient( 'update_plugins' );
		$this->assertIsObject( $filtered );
		$this->assertIsArray( $filtered->response );
		$this->assertArrayHasKey( $this->baseFile, $filtered->response );
		$this->assertTrue( Services::WpPlugins()->isUpdateAvailable( $this->baseFile ) );
	}

	public function testPreSetTransientFilterRemovesStaleSelfEntry() :void {
		$this->registerAllowBetaTransientHooks();

		$updates = $this->createUpdates( [
			$this->baseFile => (object)[
				'new_version' => $this->currentVersion,
			],
		] );

		$filtered = \apply_filters( 'pre_set_site_transient_update_plugins', $updates );
		$this->assertIsObject( $filtered );
		$this->assertIsArray( $filtered->response );
		$this->assertArrayNotHasKey( $this->baseFile, $filtered->response );
	}

	public function testCleanupRunsEvenWhenBetaFeatureIsDisabled() :void {
		$this->registerAllowBetaTransientHooks();
		\add_filter( 'shield/enable_beta', '__return_false' );

		$updates = $this->createUpdates( [
			$this->baseFile => (object)[
				'new_version' => $this->currentVersion,
			],
		] );

		$filtered = \apply_filters( 'pre_set_site_transient_update_plugins', $updates );
		$this->assertIsObject( $filtered );
		$this->assertIsArray( $filtered->response );
		$this->assertArrayNotHasKey( $this->baseFile, $filtered->response );
	}

	public function testPreSetTransientCanInjectBetaWhenEnabledAndNormalUpdateMissing() :void {
		$subject = $this->registerAllowBetaTransientHooks();
		\add_filter( 'shield/enable_beta', '__return_true' );

		$betaEntry = (object)[
			'plugin'      => $this->baseFile,
			'new_version' => $this->currentVersion.'.1',
			'package'     => 'https://downloads.wordpress.org/plugin/wp-plugin-shield.zip',
		];

		$reflection = new \ReflectionClass( $subject );
		$betaProp = $reflection->getProperty( 'beta' );
		$betaProp->setAccessible( true );
		$betaProp->setValue( $subject, $betaEntry );

		$updates = $this->createUpdates( [] );
		$filtered = \apply_filters( 'pre_set_site_transient_update_plugins', $updates );

		$this->assertIsObject( $filtered );
		$this->assertIsArray( $filtered->response );
		$this->assertArrayHasKey( $this->baseFile, $filtered->response );
		$this->assertSame( $betaEntry->new_version, $filtered->response[ $this->baseFile ]->new_version );
	}
}
