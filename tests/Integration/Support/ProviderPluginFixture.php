<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseBotDetectionController;

trait ProviderPluginFixture {

	private array $providerFixtureActivePluginsSnapshot = [];

	private array $providerFixturePluginDirs = [];

	protected function snapshotProviderPluginFixtureState() :void {
		$activePlugins = \get_option( 'active_plugins', [] );
		$this->providerFixtureActivePluginsSnapshot = \is_array( $activePlugins ) ? $activePlugins : [];
		$this->providerFixturePluginDirs = [];
	}

	protected function restoreProviderPluginFixtureState() :void {
		\update_option( 'active_plugins', $this->providerFixtureActivePluginsSnapshot, false );
		$this->removeProviderFixturePlugins();
		$this->clearProviderPluginCache();
		$this->resetProviderCaches();
	}

	protected function installProviderFixture(
		string $pluginDir,
		string $pluginFile,
		string $className,
		string $pluginName,
		array $constants = []
	) :void {
		$dir = $this->providerPluginFixtureDir( $pluginDir );
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
		foreach ( $constants as $constantName => $constantValue ) {
			$content .= "if ( !\\defined( '{$constantName}' ) ) {\n"
						."\t\\define( '{$constantName}', ".\var_export( $constantValue, true )." );\n"
						."}\n";
		}

		if ( \file_put_contents( $file, $content ) === false ) {
			$this->markTestSkipped( 'Unable to write provider fixture plugin: '.$file );
		}
		require_once $file;

		$fragment = $pluginDir.'/'.$pluginFile;
		$active = \get_option( 'active_plugins', [] );
		$active = \is_array( $active ) ? $active : [];
		$active[] = $fragment;
		\update_option( 'active_plugins', \array_values( \array_unique( $active ) ), false );

		$this->providerFixturePluginDirs[ $pluginDir ] = $dir;
		$this->clearProviderPluginCache();
		$this->resetProviderCaches();
	}

	protected function resetProviderCaches() :void {
		if ( static::con() === null ) {
			return;
		}

		foreach ( [
			$this->requireController()->comps->forms_spam,
			$this->requireController()->comps->forms_users,
		] as $controller ) {
			\Closure::bind( function () :void {
				unset( $this->installedProviders );
			}, $controller, BaseBotDetectionController::class )();
		}
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

	private function clearProviderPluginCache() :void {
		if ( \function_exists( 'wp_clean_plugins_cache' ) ) {
			\wp_clean_plugins_cache( false );
		}
		\wp_cache_delete( 'plugins', 'plugins' );
	}

	private function removeProviderFixturePlugins() :void {
		foreach ( $this->providerFixturePluginDirs as $dir ) {
			$this->removeProviderFixtureDirectory( $dir );
		}
		$this->providerFixturePluginDirs = [];
	}

	private function removeProviderFixtureDirectory( string $dir ) :void {
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

	private function providerPluginFixtureDir( string $pluginDir ) :string {
		return \wp_normalize_path( WP_PLUGIN_DIR.'/'.$pluginDir );
	}
}
