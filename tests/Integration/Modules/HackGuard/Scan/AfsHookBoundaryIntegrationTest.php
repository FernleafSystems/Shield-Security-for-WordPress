<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\AssetChange\Cleanup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class AfsHookBoundaryIntegrationTest extends ShieldIntegrationTestCase {

	private const PLUGIN = 'shield-boundary-test/boundary.php';
	private const THEME = 'shield-boundary-theme';
	private const DEFAULT_THEME = 'shield-boundary-default-theme';

	private ?Afs $afs = null;

	private ?Cleanup $cleanup = null;

	private array $hookBackups = [];

	public function set_up() {
		parent::set_up();
		$this->afs = new Afs();
		$this->cleanup = new Cleanup();
		global $wp_filter;
		foreach ( [
			'upgrader_process_complete',
			'pre_uninstall_plugin',
			'deleted_plugin',
			'deleted_theme',
			$this->cleanup->getHook(),
		] as $hook ) {
			$this->hookBackups[ $hook ] = $wp_filter[ $hook ] ?? null;
			unset( $wp_filter[ $hook ] );
		}
		add_action( 'upgrader_process_complete', [ $this->afs, 'queueAssetScansFromUpgraderProcessComplete' ], 10, 2 );
		add_action( 'pre_uninstall_plugin', [ $this->afs, 'queuePluginAssetScan' ] );
		add_action( 'deleted_plugin', [ $this->afs, 'queuePluginAssetScan' ] );
		add_action( 'deleted_theme', [ $this->afs, 'queueThemeAssetScan' ], 10, 2 );
		add_action( $this->cleanup->getHook(), [ $this->cleanup, 'run' ], 10, 3 );
	}

	public function tear_down() {
		if ( $this->afs !== null ) {
			remove_action( 'upgrader_process_complete', [ $this->afs, 'queueAssetScansFromUpgraderProcessComplete' ], 10 );
			remove_action( 'pre_uninstall_plugin', [ $this->afs, 'queuePluginAssetScan' ] );
			remove_action( 'deleted_plugin', [ $this->afs, 'queuePluginAssetScan' ] );
			remove_action( 'deleted_theme', [ $this->afs, 'queueThemeAssetScan' ], 10 );
		}
		if ( $this->cleanup !== null ) {
			remove_action( $this->cleanup->getHook(), [ $this->cleanup, 'run' ], 10 );
			foreach ( [ self::PLUGIN, self::THEME, self::DEFAULT_THEME ] as $assetKey ) {
				$assetType = $assetKey === self::PLUGIN ? 'plugin' : 'theme';
				foreach ( [ 0, 1 ] as $retry ) {
					wp_clear_scheduled_hook( $this->cleanup->getHook(), [ $assetType, $assetKey, $retry ] );
				}
			}
		}
		global $wp_filter;
		foreach ( $this->hookBackups as $hook => $backup ) {
			if ( $backup === null ) {
				unset( $wp_filter[ $hook ] );
			}
			else {
				$wp_filter[ $hook ] = $backup;
			}
		}
		parent::tear_down();
	}

	public function test_wordpress_upgrade_and_delete_hooks_ignore_hostile_values_and_keep_valid_siblings() :void {
		do_action( 'upgrader_process_complete', null, [
			'action'  => 'update',
			'type'    => 'plugin',
			'plugins' => [ [], (object)[], 123, '  ', self::PLUGIN ],
		] );
		do_action( 'upgrader_process_complete', null, [
			'action' => 'update',
			'type'   => 'theme',
			'themes' => [ false, 1.5, self::THEME ],
		] );
		do_action( 'deleted_plugin', [] );
		do_action( 'deleted_theme', (object)[], true );
		do_action( 'deleted_plugin', '0' );
		do_action( 'deleted_theme', ' 0 ', true );
		do_action( 'pre_uninstall_plugin' );
		do_action( 'deleted_plugin' );
		do_action( 'deleted_theme' );

		$this->assertNotFalse( wp_next_scheduled( $this->cleanup->getHook(), [ 'plugin', self::PLUGIN, 0 ] ) );
		$this->assertNotFalse( wp_next_scheduled( $this->cleanup->getHook(), [ 'theme', self::THEME, 0 ] ) );
		$this->assertFalse( wp_next_scheduled( $this->cleanup->getHook(), [ 'plugin', '0', 0 ] ) );
		$this->assertFalse( wp_next_scheduled( $this->cleanup->getHook(), [ 'theme', '0', 0 ] ) );
	}

	public function test_wordpress_theme_delete_hook_uses_success_default_when_argument_is_omitted() :void {
		do_action( 'deleted_theme', self::DEFAULT_THEME );

		$this->assertNotFalse( wp_next_scheduled(
			$this->cleanup->getHook(),
			[ 'theme', self::DEFAULT_THEME, 0 ]
		) );
	}

	public function test_wordpress_cron_hook_ignores_hostile_argument_types() :void {
		do_action( $this->cleanup->getHook() );
		do_action( $this->cleanup->getHook(), null, [], '0' );
		do_action( $this->cleanup->getHook(), 'plugin', 'shield-boundary-invalid/invalid.php', -1 );

		$this->assertFalse( wp_next_scheduled(
			$this->cleanup->getHook(),
			[ 'plugin', 'shield-boundary-invalid/invalid.php', 0 ]
		) );
	}
}
