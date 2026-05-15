<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseBotDetectionController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class IntegrationsConIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private const OPTION_KEYS = [
		'enable_auto_integrations',
		'auto_integrations_track',
		'form_spam_providers',
		'user_form_providers',
	];

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	private array $activePluginsSnapshot = [];

	private array $fixturePluginDirs = [];

	public function set_up() {
		parent::set_up();

		$this->optionSnapshot = $this->snapshotSelectedOptions( self::OPTION_KEYS );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$activePlugins = \get_option( 'active_plugins', [] );
		$this->activePluginsSnapshot = \is_array( $activePlugins ) ? $activePlugins : [];
		$this->fixturePluginDirs = [];
		$this->prepareAdminRequest();
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionSnapshot );
			\update_option( 'active_plugins', $this->activePluginsSnapshot, false );
			$this->removeFixturePlugins();
			$this->clearPluginCache();
			$this->resetProviderCaches();
			$this->restoreCurrentRequestState( $this->requestSnapshot );
		}

		parent::tear_down();
	}

	public function test_build_integrations_states_exposes_installed_provider_contracts() :void {
		$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam', 'thirdparty_scan_users' ] );
		$this->installProviderFixture( 'ninjaforms', 'ninja-forms.php', 'Ninja_Forms', 'Ninja Forms' );
		$this->installProviderFixture(
			'easy-digital-downloads',
			'easy-digital-downloads.php',
			'Easy_Digital_Downloads',
			'Easy Digital Downloads'
		);

		$con = $this->requireController();
		$con->opts
			->optSet( 'form_spam_providers', [ 'ninjaforms' ] )
			->optSet( 'user_form_providers', [ 'wordpress' ] )
			->store();
		$this->resetProviderCaches();

		$states = $con->comps->integrations->buildIntegrationsStates();

		$this->assertIntegrationState( $states, 'wordpress', 'enabled', 'WordPress', true );
		$this->assertIntegrationState( $states, 'ninjaforms', 'enabled', 'Ninja Forms', true );
		$this->assertIntegrationState( $states, 'easydigitaldownloads', 'available', 'Easy Digital Downloads', true );
	}

	public function test_build_integrations_states_reflects_capability_by_provider_family() :void {
		$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam' ] );
		$this->installProviderFixture( 'ninjaforms', 'ninja-forms.php', 'Ninja_Forms', 'Ninja Forms' );
		$this->installProviderFixture(
			'easy-digital-downloads',
			'easy-digital-downloads.php',
			'Easy_Digital_Downloads',
			'Easy Digital Downloads'
		);

		$con = $this->requireController();
		$con->opts
			->optSet( 'form_spam_providers', [] )
			->optSet( 'user_form_providers', [ 'wordpress' ] )
			->store();
		$this->resetProviderCaches();

		$states = $con->comps->integrations->buildIntegrationsStates();

		$this->assertIntegrationState( $states, 'ninjaforms', 'available', 'Ninja Forms', true );
		$this->assertIntegrationState( $states, 'easydigitaldownloads', 'available', 'Easy Digital Downloads', false );
	}

	public function test_disabled_auto_integrations_does_not_mutate_track_or_providers() :void {
		$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam', 'thirdparty_scan_users' ] );
		$this->installProviderFixture( 'ninjaforms', 'ninja-forms.php', 'Ninja_Forms', 'Ninja Forms' );
		$this->installProviderFixture(
			'easy-digital-downloads',
			'easy-digital-downloads.php',
			'Easy_Digital_Downloads',
			'Easy Digital Downloads'
		);

		$track = [
			'last_check_at' => 1234567890,
			'profile_hash'  => 'existing-profile',
		];
		$con = $this->requireController();
		$con->opts
			->optSet( 'enable_auto_integrations', 'N' )
			->optSet( 'auto_integrations_track', $track )
			->optSet( 'form_spam_providers', [] )
			->optSet( 'user_form_providers', [ 'wordpress' ] )
			->store();

		$con->comps->integrations->resetExecution()->execute();

		$this->assertSame( $track, $con->opts->optGet( 'auto_integrations_track' ) );
		$this->assertSame( [], $con->opts->optGet( 'form_spam_providers' ) );
		$this->assertSame( [ 'wordpress' ], $con->opts->optGet( 'user_form_providers' ) );
	}

	public function test_auto_integrations_adds_capable_installed_providers_and_persists_them() :void {
		$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam', 'thirdparty_scan_users' ] );
		$this->installProviderFixture( 'ninjaforms', 'ninja-forms.php', 'Ninja_Forms', 'Ninja Forms' );
		$this->installProviderFixture(
			'easy-digital-downloads',
			'easy-digital-downloads.php',
			'Easy_Digital_Downloads',
			'Easy Digital Downloads'
		);

		$con = $this->requireController();
		$con->opts
			->optSet( 'enable_auto_integrations', 'Y' )
			->optSet( 'auto_integrations_track', [] )
			->optSet( 'form_spam_providers', [] )
			->optSet( 'user_form_providers', [ 'wordpress' ] )
			->store();
		$this->resetProviderCaches();

		$con->comps->integrations->resetExecution()->execute();

		$track = $con->opts->optGet( 'auto_integrations_track' );
		$this->assertIsArray( $track );
		$this->assertArrayHasKey( 'last_check_at', $track );
		$this->assertArrayHasKey( 'profile_hash', $track );
		$this->assertGreaterThan( 0, (int)$track[ 'last_check_at' ] );
		$this->assertNotSame( '', (string)$track[ 'profile_hash' ] );
		$this->assertSame( [ 'ninjaforms' ], $con->opts->optGet( 'form_spam_providers' ) );
		$this->assertSame( [ 'wordpress', 'easydigitaldownloads' ], $con->opts->optGet( 'user_form_providers' ) );

		RuntimeTestState::resetOptionsRuntimeCache();
		$this->assertSame( [ 'ninjaforms' ], $con->opts->optGet( 'form_spam_providers' ) );
		$this->assertSame( [ 'wordpress', 'easydigitaldownloads' ], $con->opts->optGet( 'user_form_providers' ) );

		$con->comps->integrations->resetExecution()->execute();
		$this->assertSame( $track, $con->opts->optGet( 'auto_integrations_track' ) );
		$this->assertSame( [ 'ninjaforms' ], $con->opts->optGet( 'form_spam_providers' ) );
		$this->assertSame( [ 'wordpress', 'easydigitaldownloads' ], $con->opts->optGet( 'user_form_providers' ) );
	}

	public function test_auto_integrations_respects_capabilities_and_normalizes_duplicates() :void {
		$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam' ] );
		$this->installProviderFixture( 'ninjaforms', 'ninja-forms.php', 'Ninja_Forms', 'Ninja Forms' );
		$this->installProviderFixture(
			'easy-digital-downloads',
			'easy-digital-downloads.php',
			'Easy_Digital_Downloads',
			'Easy Digital Downloads'
		);

		$con = $this->requireController();
		$con->opts
			->optSet( 'enable_auto_integrations', 'Y' )
			->optSet( 'auto_integrations_track', [] )
			->optSet( 'form_spam_providers', [ 'ninjaforms', 'ninjaforms' ] )
			->optSet( 'user_form_providers', [ 'wordpress' ] )
			->store();
		$this->resetProviderCaches();

		$con->comps->integrations->resetExecution()->execute();

		$this->assertSame( [ 'ninjaforms' ], $con->opts->optGet( 'form_spam_providers' ) );
		$this->assertSame( [ 'wordpress' ], $con->opts->optGet( 'user_form_providers' ) );
	}

	private function prepareAdminRequest() :void {
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
	}

	private function installProviderFixture(
		string $pluginDir,
		string $pluginFile,
		string $className,
		string $pluginName
	) :void {
		$dir = $this->pluginFixtureDir( $pluginDir );
		$file = $dir.'/'.$pluginFile;
		$this->ensureClassCanBeProvidedByFixture( $className, $file );

		if ( !\is_dir( $dir ) && !\wp_mkdir_p( $dir ) ) {
			$this->markTestSkipped( 'Unable to create provider fixture directory: '.$dir );
		}

		$content = "<?php\n"
				   ."/*\n"
				   ."Plugin Name: Shield Integration Fixture - {$pluginName}\n"
				   ."*/\n"
				   ."if ( !\\class_exists( '{$className}', false ) ) {\n"
				   ."\tclass {$className} {}\n"
				   ."}\n";
		if ( \file_put_contents( $file, $content ) === false ) {
			$this->markTestSkipped( 'Unable to write provider fixture plugin: '.$file );
		}
		require_once $file;

		$fragment = $pluginDir.'/'.$pluginFile;
		$active = \get_option( 'active_plugins', [] );
		$active = \is_array( $active ) ? $active : [];
		$active[] = $fragment;
		\update_option( 'active_plugins', \array_values( \array_unique( $active ) ), false );

		$this->fixturePluginDirs[ $pluginDir ] = $dir;
		$this->clearPluginCache();
		$this->resetProviderCaches();
	}

	private function ensureClassCanBeProvidedByFixture( string $className, string $fixtureFile ) :void {
		if ( !\class_exists( $className, false ) ) {
			return;
		}

		try {
			$file = ( new \ReflectionClass( $className ) )->getFileName();
		}
		catch ( \ReflectionException $e ) {
			$file = '';
		}
		$file = \is_string( $file ) ? \wp_normalize_path( $file ) : '';
		if ( $file !== \wp_normalize_path( $fixtureFile ) ) {
			$this->markTestSkipped( "Provider class {$className} is already loaded from outside this fixture." );
		}
	}

	private function clearPluginCache() :void {
		if ( \function_exists( 'wp_clean_plugins_cache' ) ) {
			\wp_clean_plugins_cache( false );
		}
		\wp_cache_delete( 'plugins', 'plugins' );
	}

	private function resetProviderCaches() :void {
		foreach ( [
			$this->requireController()->comps->forms_spam,
			$this->requireController()->comps->forms_users,
		] as $controller ) {
			\Closure::bind( function () :void {
				unset( $this->installedProviders );
			}, $controller, BaseBotDetectionController::class )();
		}
	}

	private function removeFixturePlugins() :void {
		foreach ( $this->fixturePluginDirs as $dir ) {
			$this->removeDirectory( $dir );
		}
		$this->fixturePluginDirs = [];
	}

	private function removeDirectory( string $dir ) :void {
		$dir = \wp_normalize_path( $dir );
		$pluginDir = \wp_normalize_path( WP_PLUGIN_DIR );
		if ( \strpos( $dir, $pluginDir.'/' ) !== 0 || !\is_dir( $dir ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			/** @var \SplFileInfo $item */
			if ( $item->isDir() ) {
				@\rmdir( $item->getPathname() );
			}
			else {
				@\unlink( $item->getPathname() );
			}
		}
		@\rmdir( $dir );
	}

	private function pluginFixtureDir( string $pluginDir ) :string {
		return \wp_normalize_path( WP_PLUGIN_DIR.'/'.$pluginDir );
	}

	private function assertIntegrationState(
		array $states,
		string $slug,
		string $state,
		string $name,
		bool $hasCap
	) :void {
		$this->assertArrayHasKey( $slug, $states );
		$integration = $states[ $slug ];

		$this->assertSame( $slug, $integration[ 'slug' ] ?? null );
		$this->assertSame( $state, $integration[ 'state' ] ?? null );
		$this->assertSame( $name, $integration[ 'name' ] ?? null );
		$this->assertSame( $hasCap, $integration[ 'has_cap' ] ?? null );
		$keys = \array_keys( $integration );
		\sort( $keys );
		$this->assertSame( [ 'has_cap', 'name', 'slug', 'state' ], $keys );
	}
}
