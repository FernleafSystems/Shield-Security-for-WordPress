<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

trait CloakedPluginFixtureTrait {

	private array $cloakedPluginFixturePaths = [];
	private string $cloakedPluginFixtureFile = '';

	protected function createStandardCloakedPlugin( string $slug, string $name ) :string {
		$dir = \wp_normalize_path( WP_PLUGIN_DIR.'/'.$slug );
		if ( !\is_dir( $dir ) && !\wp_mkdir_p( $dir ) ) {
			$this->markTestSkipped( 'Unable to create temporary plugin directory.' );
		}

		$file = $slug.'/'.$slug.'.php';
		$path = \wp_normalize_path( WP_PLUGIN_DIR.'/'.$file );
		$this->writeCloakedPluginFile( $path, $name );

		$this->cloakedPluginFixtureFile = $file;
		$this->cloakedPluginFixturePaths[] = $dir;
		$this->cleanCloakedPluginCache();
		return $file;
	}

	protected function createMustUseCloakedPlugin( string $file, string $name ) :string {
		if ( !\defined( 'WPMU_PLUGIN_DIR' ) ) {
			$this->markTestSkipped( 'WPMU_PLUGIN_DIR is unavailable in this test environment.' );
		}

		$dir = \wp_normalize_path( WPMU_PLUGIN_DIR );
		if ( !\is_dir( $dir ) && !\wp_mkdir_p( $dir ) ) {
			$this->markTestSkipped( 'Unable to create temporary MU plugin directory.' );
		}

		$path = \wp_normalize_path( $dir.'/'.$file );
		$this->writeCloakedPluginFile( $path, $name );

		$this->cloakedPluginFixtureFile = $file;
		$this->cloakedPluginFixturePaths[] = $path;
		$this->cleanCloakedPluginCache();
		return $file;
	}

	public function hideCloakedPluginFromAllPlugins( $plugins ) {
		if ( \is_array( $plugins ) ) {
			unset( $plugins[ $this->cloakedPluginFixtureFile ] );
		}
		return $plugins;
	}

	public function hideCloakedPluginOnlyOnPluginsPage( $plugins ) {
		global $pagenow;

		return $pagenow === 'plugins.php'
			? $this->hideCloakedPluginFromAllPlugins( $plugins )
			: $plugins;
	}

	public function hideCloakedMustUsePlugins( $show, string $type ) {
		return $type === 'mustuse' ? false : $show;
	}

	public function hideCloakedPluginFromPluginsList( $plugins ) {
		if ( \is_array( $plugins ) ) {
			foreach ( \array_keys( $plugins ) as $group ) {
				if ( \is_array( $plugins[ $group ] ?? null ) ) {
					unset( $plugins[ $group ][ $this->cloakedPluginFixtureFile ] );
				}
			}
		}
		return $plugins;
	}

	protected function removeCloakedPluginFixtureFilters() :void {
		\remove_filter( 'all_plugins', [ $this, 'hideCloakedPluginFromAllPlugins' ], 1000 );
		\remove_filter( 'all_plugins', [ $this, 'hideCloakedPluginOnlyOnPluginsPage' ], 1000 );
		\remove_filter( 'show_advanced_plugins', [ $this, 'hideCloakedMustUsePlugins' ], 1000 );
		\remove_filter( 'plugins_list', [ $this, 'hideCloakedPluginFromPluginsList' ], 1000 );
	}

	protected function cleanupCloakedPluginFixtures() :void {
		$this->removeCloakedPluginFixtures();
	}

	protected function removeCloakedPluginFixtures() :void {
		foreach ( \array_reverse( $this->cloakedPluginFixturePaths ) as $path ) {
			$path = \wp_normalize_path( $path );
			if ( \is_file( $path ) ) {
				@\unlink( $path );
			}
			elseif ( \is_dir( $path ) ) {
				$this->deleteCloakedPluginFixtureDirectory( $path );
			}
		}
		$this->cloakedPluginFixturePaths = [];
		$this->cloakedPluginFixtureFile = '';
		$this->cleanCloakedPluginCache();
	}

	protected function resetCloakedPluginFindingsCache() :void {
		if ( static::con() === null ) {
			return;
		}

		$currentState = new \ReflectionProperty( $this->requireController()->comps->hidden_plugins, 'currentState' );
		$currentState->setAccessible( true );
		$currentState->setValue( $this->requireController()->comps->hidden_plugins, null );
	}

	private function writeCloakedPluginFile( string $path, string $name ) :void {
		$content = "<?php\n/*\nPlugin Name: {$name}\nVersion: 9.9.9\n*/\nadd_action('init', static function () {});\n";
		if ( \file_put_contents( $path, $content ) === false ) {
			$this->fail( 'Failed to write fixture plugin: '.$path );
		}
	}

	private function cleanCloakedPluginCache() :void {
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

	private function deleteCloakedPluginFixtureDirectory( string $dir ) :void {
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
}
