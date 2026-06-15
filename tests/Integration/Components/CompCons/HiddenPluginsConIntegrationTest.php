<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins\HiddenPluginState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class HiddenPluginsConIntegrationTest extends ShieldIntegrationTestCase {

	use LocalEmailCapture;

	private array $optionsSnapshot = [];
	private array $createdPaths = [];
	private string $fixturePluginFile = '';

	public function set_up() {
		parent::set_up();
		$this->startLocalEmailCapture();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'instant_alert_hidden_plugins',
			'instant_alerts_data',
			HiddenPluginState::OPT_KEY,
			'block_send_email_address',
		] );
		$this->requireController()->opts
			->optSet( 'instant_alert_hidden_plugins', 'email' )
			->optSet( 'instant_alerts_data', [] )
			->optSet( HiddenPluginState::OPT_KEY, [] )
			->optSet( 'block_send_email_address', 'hidden-plugins@example.com' )
			->store();
		$this->resetInstantAlertHandlers();
	}

	public function tear_down() {
		remove_filter( 'all_plugins', [ $this, 'hideFixturePluginFromAllPlugins' ], 1000 );
		remove_filter( 'show_advanced_plugins', [ $this, 'hideMustUsePlugins' ], 1000 );
		remove_filter( 'plugins_list', [ $this, 'hideFixturePluginFromPluginsList' ], 1000 );
		$this->deleteCreatedPaths();
		$this->cleanPluginCache();
		$this->stopLocalEmailCapture();
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
			$this->resetInstantAlertHandlers();
		}
		parent::tear_down();
	}

	public function testStandardPluginHiddenByAllPluginsFilterFiresEventAndDedupeAlert() :void {
		$con = $this->requireController();
		$this->fixturePluginFile = $this->createStandardPlugin( 'shi-hidden-all', 'SHI Hidden All Plugins' );
		add_filter( 'all_plugins', [ $this, 'hideFixturePluginFromAllPlugins' ], 1000 );

		$this->captureShieldEvents();

		$con->comps->hidden_plugins->detect();
		$this->assertHiddenPluginEvent( $this->fixturePluginFile, 'all_plugins' );
		$this->assertCount( 1, $this->capturedMails() );
		$mail = $this->lastCapturedMail();
		$this->assertArrayHasKey( 'html_body', $mail );
		$this->assertHtmlContainsMarker(
			$this->fixturePluginFile,
			(string)$mail[ 'html_body' ],
			'Hidden plugin alert HTML body'
		);

		$con->comps->hidden_plugins->detect();
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'plugin_hidden_detected' ) );
		$this->assertCount( 1, $this->capturedMails() );
	}

	public function testMustUsePluginHiddenByShowAdvancedPluginsFilterFiresEvent() :void {
		$this->requireController()->opts
			->optSet( 'instant_alert_hidden_plugins', 'disabled' )
			->optSet( HiddenPluginState::OPT_KEY, [] )
			->store();
		$this->resetInstantAlertHandlers();
		$this->fixturePluginFile = $this->createMustUsePlugin( 'shi-hidden-mu.php', 'SHI Hidden MU' );
		add_filter( 'show_advanced_plugins', [ $this, 'hideMustUsePlugins' ], 1000, 2 );

		$this->captureShieldEvents();

		$this->requireController()->comps->hidden_plugins->detect();
		$this->assertHiddenPluginEvent( $this->fixturePluginFile, 'show_advanced_plugins' );
		$this->assertCount( 0, $this->capturedMails() );
	}

	public function testPluginRemovedByPluginsListFilterFiresEvent() :void {
		$this->requireController()->opts
			->optSet( 'instant_alert_hidden_plugins', 'disabled' )
			->optSet( HiddenPluginState::OPT_KEY, [] )
			->store();
		$this->resetInstantAlertHandlers();
		$this->fixturePluginFile = $this->createStandardPlugin( 'shi-hidden-list', 'SHI Hidden List' );
		add_filter( 'plugins_list', [ $this, 'hideFixturePluginFromPluginsList' ], 1000 );

		$this->captureShieldEvents();

		$this->requireController()->comps->hidden_plugins->detect();
		$this->assertHiddenPluginEvent( $this->fixturePluginFile, 'plugins_list' );
	}

	public function testNeutralPluginsListObserverDetectsHiddenFinalListWithoutMutatingList() :void {
		$this->requireController()->opts
			->optSet( 'instant_alert_hidden_plugins', 'disabled' )
			->optSet( HiddenPluginState::OPT_KEY, [] )
			->store();
		$this->resetInstantAlertHandlers();
		$this->fixturePluginFile = $this->createStandardPlugin( 'shi-hidden-observer', 'SHI Hidden Observer' );
		$originalGet = $_GET;
		unset( $_GET[ 'plugin_status' ], $_GET[ 's' ] );

		try {
			$finalPluginsList = $this->hideFixturePluginFromPluginsList( [
				'all'                  => \get_plugins(),
				'search'               => [],
				'active'               => [],
				'inactive'             => \get_plugins(),
				'recently_activated'   => [],
				'upgrade'              => [],
				'mustuse'              => [],
				'dropins'              => [],
				'paused'               => [],
				'auto-update-enabled'  => [],
				'auto-update-disabled' => [],
			] );

			$this->captureShieldEvents();

			$this->assertSame(
				$finalPluginsList,
				$this->requireController()->comps->hidden_plugins->observePluginsList( $finalPluginsList )
			);
			$this->assertHiddenPluginEvent( $this->fixturePluginFile, 'plugins_list' );
		}
		finally {
			$_GET = $originalGet;
		}
	}

	public function hideFixturePluginFromAllPlugins( mixed $plugins ) :mixed {
		if ( \is_array( $plugins ) ) {
			unset( $plugins[ $this->fixturePluginFile ] );
		}
		return $plugins;
	}

	public function hideMustUsePlugins( mixed $show, string $type ) :mixed {
		return $type === 'mustuse' ? false : $show;
	}

	public function hideFixturePluginFromPluginsList( mixed $plugins ) :mixed {
		if ( \is_array( $plugins ) ) {
			foreach ( \array_keys( $plugins ) as $group ) {
				if ( \is_array( $plugins[ $group ] ?? null ) ) {
					unset( $plugins[ $group ][ $this->fixturePluginFile ] );
				}
			}
		}
		return $plugins;
	}

	private function assertHiddenPluginEvent( string $pluginFile, string $reason ) :void {
		$events = $this->getCapturedEventsByKey( 'plugin_hidden_detected' );
		$this->assertCount( 1, $events );
		$this->assertArrayHasKey( 'audit_params', $events[ 0 ][ 'meta' ] );
		$auditParams = $events[ 0 ][ 'meta' ][ 'audit_params' ];
		$this->assertIsArray( $auditParams );
		$this->assertArrayHasKey( 'plugin', $auditParams );
		$this->assertArrayHasKey( 'hidden_by', $auditParams );
		$this->assertSame( $pluginFile, $auditParams[ 'plugin' ] );
		$this->assertStringContainsString( $reason, (string)$auditParams[ 'hidden_by' ] );
	}

	private function createStandardPlugin( string $slug, string $name ) :string {
		$dir = \wp_normalize_path( WP_PLUGIN_DIR.'/'.$slug );
		if ( !\is_dir( $dir ) && !\wp_mkdir_p( $dir ) ) {
			$this->markTestSkipped( 'Unable to create temporary plugin directory.' );
		}
		$file = $slug.'/'.$slug.'.php';
		$path = \wp_normalize_path( WP_PLUGIN_DIR.'/'.$file );
		$this->writePluginFile( $path, $name );
		$this->createdPaths[] = $dir;
		$this->cleanPluginCache();
		return $file;
	}

	private function createMustUsePlugin( string $file, string $name ) :string {
		if ( !\defined( 'WPMU_PLUGIN_DIR' ) ) {
			$this->markTestSkipped( 'WPMU_PLUGIN_DIR is unavailable in this test environment.' );
		}

		$dir = \wp_normalize_path( WPMU_PLUGIN_DIR );
		if ( !\is_dir( $dir ) && !\wp_mkdir_p( $dir ) ) {
			$this->markTestSkipped( 'Unable to create temporary MU plugin directory.' );
		}

		$path = \wp_normalize_path( $dir.'/'.$file );
		$this->writePluginFile( $path, $name );
		$this->createdPaths[] = $path;
		$this->cleanPluginCache();
		return $file;
	}

	private function writePluginFile( string $path, string $name ) :void {
		$content = "<?php\n/*\nPlugin Name: {$name}\nVersion: 9.9.9\n*/\nadd_action('init', static function () {});\n";
		if ( \file_put_contents( $path, $content ) === false ) {
			$this->fail( 'Failed to write fixture plugin: '.$path );
		}
	}

	private function cleanPluginCache() :void {
		if ( !\function_exists( 'wp_clean_plugins_cache' ) && \defined( 'ABSPATH' ) ) {
			$pluginApi = \rtrim( \str_replace( '\\', '/', ABSPATH ), '/' ).'/wp-admin/includes/plugin.php';
			if ( \is_file( $pluginApi ) ) {
				require_once $pluginApi;
			}
		}
		if ( \function_exists( 'wp_clean_plugins_cache' ) ) {
			\wp_clean_plugins_cache( false );
		}
	}

	private function deleteCreatedPaths() :void {
		foreach ( \array_reverse( $this->createdPaths ) as $path ) {
			$path = \wp_normalize_path( $path );
			if ( \is_file( $path ) ) {
				@\unlink( $path );
			}
			elseif ( \is_dir( $path ) ) {
				$this->deleteDirectoryRecursively( $path );
			}
		}
		$this->createdPaths = [];
	}

	private function deleteDirectoryRecursively( string $dir ) :void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			/** @var \SplFileInfo $item */
			$item->isDir() ? @\rmdir( $item->getPathname() ) : @\unlink( $item->getPathname() );
		}
		@\rmdir( $dir );
	}

	private function resetInstantAlertHandlers() :void {
		$alertsProperty = new \ReflectionProperty( $this->requireController()->comps->instant_alerts, 'alerts' );
		$alertsProperty->setAccessible( true );
		$alertsProperty->setValue( $this->requireController()->comps->instant_alerts, null );
	}
}
