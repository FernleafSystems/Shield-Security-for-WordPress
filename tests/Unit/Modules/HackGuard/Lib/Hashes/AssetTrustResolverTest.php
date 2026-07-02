<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Hashes;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	Exceptions\NonAssetFileException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	Fs,
	Plugins,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

class AssetTrustResolverTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		AssetTrustResolver::resetMemoization();
		ResolverFs::$isAbsPathCalls = 0;
		ResolverPlugins::$installedPluginFilesCalls = 0;
		ResolverThemes::$getThemesCalls = 0;
		Functions\when( 'path_join' )->alias( fn( string $a, string $b ) :string => $this->normalisePath( \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) ) );
		Functions\when( 'wp_normalize_path' )->alias( fn( string $path ) :string => $this->normalisePath( $path ) );
		Functions\when( 'get_theme_root' )->alias( fn() :string => $this->normalisePath( WP_CONTENT_DIR.'/themes' ) );
	}

	protected function tearDown() :void {
		AssetTrustResolver::resetMemoization();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_repeated_same_plugin_path_reuses_full_path_context() :void {
		$this->installEnvironment( [ 'alpha/alpha.php' ] );
		$path = $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/File.php' );
		$resolver = new AssetTrustResolver();

		$first = $resolver->resolveContext( $path );
		$callsAfterFirst = [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		];
		$second = $resolver->resolveContext( $path );

		$this->assertSame( 'plugin', $first->assetType );
		$this->assertSame( 'alpha/alpha.php', $first->assetKey );
		$this->assertSame( 'src/File.php', $first->relativePath );
		$this->assertSame( $first->assetType, $second->assetType );
		$this->assertSame( $first->assetKey, $second->assetKey );
		$this->assertSame( $first->relativePath, $second->relativePath );
		$this->assertSame( $callsAfterFirst, [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		] );
	}

	public function test_plugin_context_uses_reloaded_asset_version() :void {
		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [ 'alpha/alpha.php' ], '0.9.0', '1.0.0' ),
			'service_wpthemes'  => new ResolverThemes( [] ),
		] );
		$path = $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/File.php' );

		$context = ( new AssetTrustResolver() )->resolveContext( $path );

		$this->assertSame( '1.0.0', $context->assetVersion );
	}

	public function test_cached_plugin_context_refreshes_asset_version() :void {
		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [ 'alpha/alpha.php' ], '1.0.0' ),
			'service_wpthemes'  => new ResolverThemes( [] ),
		] );
		$path = $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/File.php' );
		$resolver = new AssetTrustResolver();
		$first = $resolver->resolveContext( $path );
		$this->assertSame( '1.0.0', $first->assetVersion );

		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [ 'alpha/alpha.php' ], '0.9.0', '1.1.0' ),
			'service_wpthemes'  => new ResolverThemes( [] ),
		] );
		$second = $resolver->resolveContext( $path );

		$this->assertSame( '1.1.0', $second->assetVersion );
		$this->assertSame( $first->assetType, $second->assetType );
		$this->assertSame( $first->assetKey, $second->assetKey );
		$this->assertSame( $first->relativePath, $second->relativePath );
	}

	public function test_repeated_same_theme_path_reuses_full_path_context() :void {
		$this->installEnvironment( [], [ 'clean' ] );
		$path = $this->normalisePath( WP_CONTENT_DIR.'/themes/clean/inc/File.php' );
		$resolver = new AssetTrustResolver();

		$first = $resolver->resolveContext( $path );
		$callsAfterFirst = [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		];
		$second = $resolver->resolveContext( $path );

		$this->assertSame( 'theme', $first->assetType );
		$this->assertSame( 'clean', $first->assetKey );
		$this->assertSame( 'inc/File.php', $first->relativePath );
		$this->assertSame( $first->assetType, $second->assetType );
		$this->assertSame( $first->assetKey, $second->assetKey );
		$this->assertSame( $first->relativePath, $second->relativePath );
		$this->assertSame( $callsAfterFirst, [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		] );
	}

	public function test_theme_context_uses_reloaded_asset_version() :void {
		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [] ),
			'service_wpthemes'  => new ResolverThemes( [ 'clean' ], '0.9.0', '1.0.0' ),
		] );
		$path = $this->normalisePath( WP_CONTENT_DIR.'/themes/clean/inc/File.php' );

		$context = ( new AssetTrustResolver() )->resolveContext( $path );

		$this->assertSame( '1.0.0', $context->assetVersion );
	}

	public function test_cached_theme_context_refreshes_asset_version() :void {
		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [] ),
			'service_wpthemes'  => new ResolverThemes( [ 'clean' ], '1.0.0' ),
		] );
		$path = $this->normalisePath( WP_CONTENT_DIR.'/themes/clean/inc/File.php' );
		$resolver = new AssetTrustResolver();
		$first = $resolver->resolveContext( $path );
		$this->assertSame( '1.0.0', $first->assetVersion );

		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [] ),
			'service_wpthemes'  => new ResolverThemes( [ 'clean' ], '0.9.0', '1.1.0' ),
		] );
		$second = $resolver->resolveContext( $path );

		$this->assertSame( '1.1.0', $second->assetVersion );
		$this->assertSame( $first->assetType, $second->assetType );
		$this->assertSame( $first->assetKey, $second->assetKey );
		$this->assertSame( $first->relativePath, $second->relativePath );
	}

	public function test_repeated_non_asset_path_miss_is_memoized() :void {
		$this->installEnvironment( [ 'alpha/alpha.php' ], [ 'clean' ] );
		$path = $this->normalisePath( WP_CONTENT_DIR.'/uploads/outside.php' );
		$resolver = new AssetTrustResolver();

		$this->assertResolveContextMiss( $resolver, $path );
		$callsAfterFirst = [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		];
		$this->assertResolveContextMiss( $resolver, $path );
		$this->assertSame( $callsAfterFirst, [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		] );
	}

	/**
	 * @dataProvider unknownAssetDirectoryProvider
	 */
	public function test_repeated_unknown_asset_directory_miss_is_memoized( string $path ) :void {
		$this->installEnvironment( [ 'alpha/alpha.php' ], [ 'clean' ] );
		$resolver = new AssetTrustResolver();
		$path = $this->normalisePath( $path );

		$this->assertResolveContextMiss( $resolver, $path );
		$callsAfterFirst = [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		];
		$this->assertResolveContextMiss( $resolver, $path );
		$this->assertSame( $callsAfterFirst, [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		] );
	}

	public static function unknownAssetDirectoryProvider() :array {
		return [
			'unknown plugin directory'      => [ WP_PLUGIN_DIR.'/missing/file.php' ],
			'unknown theme directory'       => [ WP_CONTENT_DIR.'/themes/missing/file.php' ],
			'plugin root sibling prefix'    => [ WP_PLUGIN_DIR.'alpha/file.php' ],
			'plugin root sibling backslash' => [ \str_replace( '/', '\\', WP_PLUGIN_DIR.'alpha/file.php' ) ],
			'theme root sibling prefix'     => [ WP_CONTENT_DIR.'/themesclean/file.php' ],
			'theme root sibling backslash'  => [ \str_replace( '/', '\\', WP_CONTENT_DIR.'/themesclean/file.php' ) ],
		];
	}

	private function installEnvironment( array $pluginFiles = [], array $themes = [] ) :void {
		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( $pluginFiles ),
			'service_wpthemes'  => new ResolverThemes( $themes ),
		] );
	}

	private function assertResolveContextMiss( AssetTrustResolver $resolver, string $path ) :void {
		try {
			$resolver->resolveContext( $path );
		}
		catch ( NonAssetFileException $e ) {
			$this->assertInstanceOf( NonAssetFileException::class, $e );
			return;
		}
		$this->fail( 'Expected asset context resolution to miss.' );
	}

	private function normalisePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}
}

class ResolverFs extends Fs {
	public static int $isAbsPathCalls = 0;

	public function isAbsPath( $path ) {
		self::$isAbsPathCalls++;
		return \preg_match( '#^([A-Z]:)?/#i', \str_replace( '\\', '/', (string)$path ) ) === 1;
	}
}

class ResolverPlugins extends Plugins {
	public static int $installedPluginFilesCalls = 0;

	private array $pluginFiles;

	private string $version;

	private ?string $reloadVersion;

	public function __construct( array $pluginFiles, string $version = '1.0.0', ?string $reloadVersion = null ) {
		$this->pluginFiles = $pluginFiles;
		$this->version = $version;
		$this->reloadVersion = $reloadVersion;
	}

	public function getInstalledPluginFiles() :array {
		self::$installedPluginFilesCalls++;
		return $this->pluginFiles;
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		return \in_array( $file, $this->pluginFiles, true )
			? new ResolverPluginVo( $file, $reload && $this->reloadVersion !== null ? $this->reloadVersion : $this->version )
			: null;
	}
}

class ResolverThemes extends Themes {
	public static int $getThemesCalls = 0;

	private array $themes;

	private string $version;

	private ?string $reloadVersion;

	public function __construct( array $themes, string $version = '1.0.0', ?string $reloadVersion = null ) {
		$this->themes = $themes;
		$this->version = $version;
		$this->reloadVersion = $reloadVersion;
	}

	public function getThemes() :array {
		self::$getThemesCalls++;
		return \array_map(
			static fn( string $stylesheet ) => new class( $stylesheet ) {
				private string $stylesheet;

				public function __construct( string $stylesheet ) {
					$this->stylesheet = $stylesheet;
				}

				public function get_stylesheet() :string {
					return $this->stylesheet;
				}
			},
			$this->themes
		);
	}

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		return \in_array( $stylesheet, $this->themes, true )
			? new ResolverThemeVo( $stylesheet, $reload && $this->reloadVersion !== null ? $this->reloadVersion : $this->version )
			: null;
	}
}

class ResolverPluginVo extends WpPluginVo {
	public string $file;
	public string $Version;

	public function __construct( string $file, string $version = '1.0.0' ) {
		$this->file = $file;
		$this->Version = $version;
	}

	public function __get( string $key ) {
		switch ( $key ) {
			case 'asset_type':
				return 'plugin';
			case 'unique_id':
				return $this->file;
			case 'slug':
				return \dirname( $this->file );
			default:
				return $this->{$key} ?? null;
		}
	}
}

class ResolverThemeVo extends WpThemeVo {
	public string $stylesheet;
	public string $Version;

	public function __construct( string $stylesheet, string $version = '1.0.0' ) {
		$this->stylesheet = $stylesheet;
		$this->Version = $version;
	}

	public function __get( string $key ) {
		switch ( $key ) {
			case 'asset_type':
			case 'unique_id':
			case 'slug':
				return $key === 'asset_type' ? 'theme' : $this->stylesheet;
			case 'is_child':
				return false;
			default:
				return $this->{$key} ?? null;
		}
	}
}
