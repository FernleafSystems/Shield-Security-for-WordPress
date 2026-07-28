<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots\{
	SnapshotPlugins,
	SnapshotPluginVo,
	SnapshotThemes,
	SnapshotThemeVo
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

class FindAssetsToSnapTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_returns_every_valid_installed_plugin_and_theme_without_activity_filtering() :void {
		$activePlugin = new SnapshotPluginVo( 'active-root.php', '1.0.0' );
		$inactivePlugin = new SnapshotPluginVo( 'inactive-plugin/plugin.php', '2.0.0' );
		$inactivePlugin->active = false;

		$activeParent = new SnapshotThemeVo( 'parent-theme', '3.0.0' );
		$inactiveTheme = new SnapshotThemeVo( 'inactive-theme', '4.0.0' );
		$inactiveTheme->active = false;
		$activeChild = new SnapshotThemeVo( 'child-theme', '5.0.0' );

		ServicesState::installItems( [
			'service_wpplugins' => new SnapshotPlugins( [
				$activePlugin,
				null,
				$inactivePlugin,
				new \stdClass(),
			] ),
			'service_wpthemes'  => new SnapshotThemes( [
				$activeChild,
				'invalid-theme',
				$activeParent,
				$inactiveTheme,
			] ),
		] );

		$assets = ( new FindAssetsToSnap() )->run();

		$this->assertSame( [
			'active-root.php',
			'inactive-plugin/plugin.php',
		], \array_values( \array_map(
			static fn( SnapshotPluginVo $plugin ) :string => $plugin->file,
			\array_filter( $assets, static fn( $asset ) :bool => $asset instanceof SnapshotPluginVo )
		) ) );
		$this->assertSame( [
			'child-theme',
			'parent-theme',
			'inactive-theme',
		], \array_values( \array_map(
			static fn( SnapshotThemeVo $theme ) :string => $theme->stylesheet,
			\array_filter( $assets, static fn( $asset ) :bool => $asset instanceof SnapshotThemeVo )
		) ) );
		$this->assertCount( 5, $assets );
		$this->assertSame( 1, \count( \array_filter(
			$assets,
			static fn( $asset ) :bool => $asset instanceof SnapshotThemeVo
											 && $asset->stylesheet === 'child-theme'
		) ) );
		$this->assertSame( 1, \count( \array_filter(
			$assets,
			static fn( $asset ) :bool => $asset instanceof SnapshotThemeVo
											 && $asset->stylesheet === 'parent-theme'
		) ) );
	}

	public function test_reloads_canonical_assets_deduplicates_resolved_identity_and_isolates_invalid_inventory() :void {
		$pluginV1 = new SnapshotPluginVo( 'duplicate/plugin.php', '1.0.0' );
		$pluginV2 = new SnapshotPluginVo( 'duplicate/plugin.php', '2.0.0' );
		$currentPlugin = new SnapshotPluginVo( 'duplicate/plugin.php', '3.0.0' );
		$rootPlugin = new SnapshotPluginVo( 'root-plugin.php', '4.0.0' );
		$blankPlugin = new SnapshotPluginVo( ' ', '1.0.0' );
		$blankPluginVersion = new SnapshotPluginVo( 'blank-version/plugin.php', ' ' );
		$wrongPluginType = new FindAssetsWrongTypePluginVo( 'wrong-type/plugin.php', '1.0.0' );
		$unresolvedPlugin = new SnapshotPluginVo( 'unresolved/plugin.php', '1.0.0' );
		$conflictingPlugin = new SnapshotPluginVo( 'conflict/plugin.php', '1.0.0' );

		$themeV1 = new SnapshotThemeVo( 'duplicate-theme', '1.0.0' );
		$themeV2 = new SnapshotThemeVo( 'duplicate-theme', '2.0.0' );
		$currentTheme = new SnapshotThemeVo( 'duplicate-theme', '3.0.0' );
		$blankTheme = new SnapshotThemeVo( ' ', '1.0.0' );
		$blankThemeVersion = new SnapshotThemeVo( 'blank-version-theme', ' ' );
		$wrongThemeType = new FindAssetsWrongTypeThemeVo( 'wrong-type-theme', '1.0.0' );
		$unresolvedTheme = new SnapshotThemeVo( 'unresolved-theme', '1.0.0' );

		$plugins = new FindAssetsRecordingPlugins(
			[
				$pluginV1,
				$pluginV2,
				$pluginV2,
				$rootPlugin,
				$blankPlugin,
				$blankPluginVersion,
				$wrongPluginType,
				$unresolvedPlugin,
				$conflictingPlugin,
				null,
				new \stdClass(),
			],
			[
				'duplicate/plugin.php' => $currentPlugin,
				'root-plugin.php'      => $rootPlugin,
				'conflict/plugin.php'  => new SnapshotPluginVo( 'other/plugin.php', '1.0.0' ),
			]
		);
		$themes = new FindAssetsRecordingThemes(
			[
				$themeV1,
				$themeV2,
				$themeV2,
				$blankTheme,
				$blankThemeVersion,
				$wrongThemeType,
				$unresolvedTheme,
				'not-a-theme',
			],
			[
				'duplicate-theme' => $currentTheme,
			]
		);
		ServicesState::installItems( [
			'service_wpplugins' => $plugins,
			'service_wpthemes'  => $themes,
		] );

		$assets = ( new FindAssetsToSnap() )->run();

		$this->assertSame( [
			'plugin|duplicate/plugin.php|3.0.0',
			'plugin|root-plugin.php|4.0.0',
			'theme|duplicate-theme|3.0.0',
		], \array_values( \array_map(
			static fn( $asset ) :string => \implode( '|', [
				$asset->asset_type,
				$asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet,
				$asset->Version,
			] ),
			$assets
		) ) );
		$this->assertSame( [
			[ 'duplicate/plugin.php', true ],
			[ 'root-plugin.php', true ],
			[ 'unresolved/plugin.php', true ],
			[ 'conflict/plugin.php', true ],
		], $plugins->reloads );
		$this->assertSame( [
			[ 'duplicate-theme', true ],
			[ 'unresolved-theme', true ],
		], $themes->reloads );
	}
}

class FindAssetsWrongTypePluginVo extends SnapshotPluginVo {

	public function __get( string $key ) {
		return $key === 'asset_type' ? 'theme' : parent::__get( $key );
	}
}

class FindAssetsWrongTypeThemeVo extends SnapshotThemeVo {

	public function __get( string $key ) {
		return $key === 'asset_type' ? 'plugin' : parent::__get( $key );
	}
}

class FindAssetsRecordingPlugins extends SnapshotPlugins {

	public array $reloads = [];

	/** @var array<string,WpPluginVo> */
	private array $resolved;

	public function __construct( array $inventory, array $resolved ) {
		parent::__construct( $inventory );
		$this->resolved = $resolved;
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		$this->reloads[] = [ $file, $reload ];
		return $this->resolved[ $file ] ?? null;
	}
}

class FindAssetsRecordingThemes extends SnapshotThemes {

	public array $reloads = [];

	/** @var array<string,WpThemeVo> */
	private array $resolved;

	public function __construct( array $inventory, array $resolved ) {
		parent::__construct( $inventory );
		$this->resolved = $resolved;
	}

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		$this->reloads[] = [ $stylesheet, $reload ];
		return $this->resolved[ $stylesheet ] ?? null;
	}
}
