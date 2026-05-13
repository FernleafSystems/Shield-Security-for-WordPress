<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansVulnerabilitiesBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\ScanResultVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc\{
	ResultItem as ApcResultItem,
	ResultsSet as ApcResultsSet
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\{
	ResultItem as WpvResultItem,
	ResultsSet as WpvResultsSet
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	MaintenancePluginsService,
	MaintenanceThemesService,
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestPluginUrls
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ScansVulnerabilitiesBuilderTest extends BaseUnitTest {

	protected array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_dedupes_distinct_assets_across_vulnerable_and_abandoned_sections() :void {
		$this->installTestControllerAndServices(
			[
				$this->buildWpvResultItem( 'plugin-one/plugin-one.php', 'p' ),
			],
			[
				$this->buildApcResultItem( 'plugin-one/plugin-one.php', 'p' ),
				$this->buildApcResultItem( 'twentyfourteen', 't' ),
			],
			[
				'plugin-one/plugin-one.php' => $this->buildPluginVo( 'plugin-one/plugin-one.php', 'plugin-one', 'Plugin One' ),
			],
			[
				'twentyfourteen' => $this->buildThemeVo( 'twentyfourteen', 'Twenty Fourteen' ),
			]
		);

		$payload = ( new ScansVulnerabilitiesBuilder() )->build();

		$this->assertSame( 2, $payload[ 'count' ] );
		$this->assertSame( 'critical', $payload[ 'status' ] );
		$this->assertSame( 1, $payload[ 'sections' ][ 'vulnerable' ][ 'count' ] );
		$this->assertSame( 'critical', $payload[ 'sections' ][ 'vulnerable' ][ 'status' ] );
		$this->assertSame( 2, $payload[ 'sections' ][ 'abandoned' ][ 'count' ] );
		$this->assertSame( 'critical', $payload[ 'sections' ][ 'abandoned' ][ 'status' ] );
		$this->assertCount( 1, $payload[ 'sections' ][ 'vulnerable' ][ 'items' ] );
		$this->assertCount( 2, $payload[ 'sections' ][ 'abandoned' ][ 'items' ] );
		$this->assertSame( [ 'plugin-one/plugin-one.php', 'twentyfourteen' ], \array_column( $payload[ 'sections' ][ 'abandoned' ][ 'items' ], 'asset_key' ) );
		$this->assertSame( 'plugin-one/plugin-one.php', $payload[ 'sections' ][ 'vulnerable' ][ 'items' ][ 0 ][ 'asset_key' ] );
		$this->assertSame( 'plugin', $payload[ 'sections' ][ 'vulnerable' ][ 'items' ][ 0 ][ 'asset_type' ] );
	}

	public function test_build_uses_critical_status_when_only_abandoned_assets_exist() :void {
		$this->installTestControllerAndServices(
			[],
			[
				$this->buildApcResultItem( 'classic', 't' ),
			],
			[],
			[
				'classic' => $this->buildThemeVo( 'classic', 'Classic' ),
			]
		);

		$payload = ( new ScansVulnerabilitiesBuilder() )->build();

		$this->assertSame( 1, $payload[ 'count' ] );
		$this->assertSame( 'critical', $payload[ 'status' ] );
		$this->assertSame( 0, $payload[ 'sections' ][ 'vulnerable' ][ 'count' ] );
		$this->assertSame( 'good', $payload[ 'sections' ][ 'vulnerable' ][ 'status' ] );
		$this->assertSame( 1, $payload[ 'sections' ][ 'abandoned' ][ 'count' ] );
		$this->assertSame( 'critical', $payload[ 'sections' ][ 'abandoned' ][ 'status' ] );
		$this->assertSame( 'classic', $payload[ 'sections' ][ 'abandoned' ][ 'items' ][ 0 ][ 'asset_key' ] );
		$this->assertSame( [ 'classic' ], \array_column( $payload[ 'sections' ][ 'abandoned' ][ 'items' ], 'asset_key' ) );
	}

	public function test_abandoned_plugin_result_does_not_fallback_to_theme_by_slug() :void {
		$this->installTestControllerAndServices(
			[],
			[
				$this->buildApcResultItem( 'ghost-plugin/ghost-plugin.php', 'p' ),
				$this->buildApcResultItem( 'twentyfifteen', 't' ),
			],
			[],
			[
				'ghost-plugin/ghost-plugin.php' => $this->buildThemeVo( 'ghost-plugin/ghost-plugin.php', 'Ghost as Theme' ),
				'twentyfifteen' => $this->buildThemeVo( 'twentyfifteen', 'Twenty Fifteen' ),
			]
		);

		$payload = ( new ScansVulnerabilitiesBuilder() )->build();

		$this->assertCount( 1, $payload[ 'sections' ][ 'abandoned' ][ 'items' ] );
		$this->assertSame( 'twentyfifteen', $payload[ 'sections' ][ 'abandoned' ][ 'items' ][ 0 ][ 'asset_key' ] );
		$this->assertSame( 1, $payload[ 'sections' ][ 'abandoned' ][ 'count' ] );
		$this->assertEmpty(
			\array_filter(
				\array_column( $payload[ 'sections' ][ 'abandoned' ][ 'items' ], 'asset_key' ),
				static fn ( string $assetKey ) :bool => $assetKey === 'ghost-plugin/ghost-plugin.php'
			)
		);
	}

	public function test_vulnerable_rows_resolve_action_targets_by_item_type() :void {
		$this->installTestControllerAndServices(
			[
				$this->buildWpvResultItem( 'plugin-alpha/plugin-alpha.php', 'p' ),
				$this->buildWpvResultItem( 'plugin-alpha/plugin-alpha.php', 'p' ),
				$this->buildWpvResultItem( 'twentytwentyfive', 't' ),
			],
			[],
			[
				'plugin-alpha/plugin-alpha.php' => $this->buildPluginVo( 'plugin-alpha/plugin-alpha.php', 'plugin-alpha', 'Plugin Alpha' ),
			],
			[
				'twentytwentyfive' => $this->buildThemeVo( 'twentytwentyfive', 'Twenty Twenty Five' ),
			]
		);

		$payload = ( new ScansVulnerabilitiesBuilder() )->build();
		$vulnerableRows = $payload[ 'sections' ][ 'vulnerable' ][ 'items' ];

		$this->assertSame( 2, $payload[ 'sections' ][ 'vulnerable' ][ 'count' ] );
		$this->assertSame( 2, $vulnerableRows[ 0 ][ 'count' ] );
		$this->assertSame( 1, $vulnerableRows[ 1 ][ 'count' ] );

		$itemsByAssetKey = [];
		foreach ( $vulnerableRows as $item ) {
			$itemsByAssetKey[ $item[ 'asset_key' ] ] = $item;
		}

		$this->assertSame( 'plugin', $itemsByAssetKey[ 'plugin-alpha/plugin-alpha.php' ][ 'asset_type' ] );
		$this->assertSame( 'theme', $itemsByAssetKey[ 'twentytwentyfive' ][ 'asset_type' ] );
		$this->assertSame( '/wp-admin/plugins.php', $itemsByAssetKey[ 'plugin-alpha/plugin-alpha.php' ][ 'actions' ][ 0 ][ 'href' ] );
		$this->assertSame( '/wp-admin/themes.php', $itemsByAssetKey[ 'twentytwentyfive' ][ 'actions' ][ 0 ][ 'href' ] );
		$this->assertSame( 'navigate', $itemsByAssetKey[ 'plugin-alpha/plugin-alpha.php' ][ 'actions' ][ 0 ][ 'type' ] );
		$this->assertSame( 'navigate', $itemsByAssetKey[ 'twentytwentyfive' ][ 'actions' ][ 0 ][ 'type' ] );
	}

	public function test_vulnerable_rows_with_same_slug_are_grouped_by_item_type() :void {
		$this->installTestControllerAndServices(
			[
				$this->buildWpvResultItem( 'shared/shared.php', 'p' ),
				$this->buildWpvResultItem( 'shared/shared.php', 't' ),
			],
			[],
			[
				'shared/shared.php' => $this->buildPluginVo( 'shared/shared.php', 'shared', 'Shared Plugin' ),
			],
			[
				'shared/shared.php' => $this->buildThemeVo( 'shared/shared.php', 'Shared Theme' ),
			]
		);

		$payload = ( new ScansVulnerabilitiesBuilder() )->build();
		$itemsByType = [];
		foreach ( $payload[ 'sections' ][ 'vulnerable' ][ 'items' ] as $item ) {
			$itemsByType[ $item[ 'asset_type' ] ] = $item;
		}

		$this->assertSame( 2, $payload[ 'count' ] );
		$this->assertSame( 2, $payload[ 'sections' ][ 'vulnerable' ][ 'count' ] );
		$this->assertArrayHasKey( 'plugin', $itemsByType );
		$this->assertArrayHasKey( 'theme', $itemsByType );
		$this->assertSame( 'shared/shared.php', $itemsByType[ 'plugin' ][ 'asset_key' ] );
		$this->assertSame( 'shared/shared.php', $itemsByType[ 'theme' ][ 'asset_key' ] );
		$this->assertSame( 'vulnerability-plugin-shared/shared.php', $itemsByType[ 'plugin' ][ 'key' ] );
		$this->assertSame( 'vulnerability-theme-shared/shared.php', $itemsByType[ 'theme' ][ 'key' ] );
		$this->assertSame( '/wp-admin/plugins.php', $itemsByType[ 'plugin' ][ 'actions' ][ 0 ][ 'href' ] );
		$this->assertSame( '/wp-admin/themes.php', $itemsByType[ 'theme' ][ 'actions' ][ 0 ][ 'href' ] );
	}

	public function test_unknown_or_empty_item_type_is_skipped() :void {
		$this->installTestControllerAndServices(
			[
				$this->buildWpvResultItem( 'plugin-beta/plugin-beta.php', '' ),
				$this->buildWpvResultItem( 'plugin-beta/plugin-beta.php', 'p' ),
			],
			[
				$this->buildApcResultItem( 'classic', '' ),
			],
			[
				'plugin-beta/plugin-beta.php' => $this->buildPluginVo( 'plugin-beta/plugin-beta.php', 'plugin-beta', 'Plugin Beta' ),
			],
			[
				'classic' => $this->buildThemeVo( 'classic', 'Classic' ),
			]
		);

		$payload = ( new ScansVulnerabilitiesBuilder() )->build();
		$vulnerableRows = $payload[ 'sections' ][ 'vulnerable' ];
		$abandonedRows = $payload[ 'sections' ][ 'abandoned' ];
		$vulnerableRowsByAsset = [];

		foreach ( $vulnerableRows[ 'items' ] as $item ) {
			$vulnerableRowsByAsset[ $item[ 'asset_key' ] ] = $item;
		}

		$this->assertSame( 1, $vulnerableRows[ 'count' ] );
		$this->assertSame( 1, $vulnerableRows[ 'items' ][ 0 ][ 'count' ] );
		$this->assertArrayHasKey( 'plugin-beta/plugin-beta.php', $vulnerableRowsByAsset );
		$this->assertSame( 0, $abandonedRows[ 'count' ] );
		$this->assertSame( [], $abandonedRows[ 'items' ] );
	}

	private function buildPluginVo( string $file, string $slug, string $label ) :WpPluginVo {
		return new class( $file, $slug, $label ) extends WpPluginVo {
			public string $file;
			public string $slug;
			public string $Title;
			public string $Name;
			public string $Version;
			public string $new_version;

			public function __construct( string $file, string $slug, string $label ) {
				$this->file = $file;
				$this->slug = $slug;
				$this->Title = $label;
				$this->Name = $label;
				$this->Version = '1.0.0';
				$this->new_version = '';
			}

			public function __get( string $key ) {
				switch ( $key ) {
					case 'asset_type':
						return 'plugin';
					case 'slug':
						return $this->slug;
					case 'unique_id':
						return $this->file;
					case 'version':
						return $this->Version;
					default:
						return $this->{$key} ?? null;
				}
			}

			public function getInstallDir() :string {
				return '/';
			}

			public function isWpOrg() :bool {
				return false;
			}
		};
	}

	private function buildThemeVo( string $stylesheet, string $label ) :WpThemeVo {
		return new class( $stylesheet, $label ) extends WpThemeVo {
			public string $stylesheet;
			public string $Name;
			public string $Version;
			public string $new_version;
			public bool $is_child = false;
			public bool $is_parent = false;

			public function __construct( string $stylesheet, string $label ) {
				$this->stylesheet = $stylesheet;
				$this->Name = $label;
				$this->Version = '1.0.0';
				$this->new_version = '';
			}

			public function __get( string $key ) {
				switch ( $key ) {
					case 'asset_type':
						return 'theme';
					case 'slug':
					case 'unique_id':
						return $this->stylesheet;
					case 'version':
						return $this->Version;
					case 'child_theme':
						return null;
					case 'parent_theme':
						return null;
					default:
						return $this->{$key} ?? null;
				}
			}

			public function getInstallDir() :string {
				return '/';
			}

			public function isWpOrg() :bool {
				return false;
			}
		};
	}

	private function buildWpvResultItem( string $slug, string $itemType ) :WpvResultItem {
		return $this->buildResultItem( (new WpvResultItem()), $slug, $itemType );
	}

	private function buildApcResultItem( string $slug, string $itemType ) :ApcResultItem {
		return $this->buildResultItem( (new ApcResultItem()), $slug, $itemType );
	}

	private function buildResultItem( object $item, string $slug, string $itemType ) {
		$item->VO = ( new ScanResultVO() )->applyFromArray( [
			'item_id'   => $slug,
			'item_type' => $itemType,
		] );
		return $item;
	}

	private function buildScansComponent( array $vulnerableRows, array $abandonedRows ) :object {
		$wpvSet = ( new WpvResultsSet() )->setItems( $vulnerableRows );
		$apcSet = ( new ApcResultsSet() )->setItems( $abandonedRows );

		return new class( $wpvSet, $apcSet ) {
			private WpvResultsSet $wpv;
			private ApcResultsSet $apc;

			public function __construct( WpvResultsSet $wpv, ApcResultsSet $apc ) {
				$this->wpv = $wpv;
				$this->apc = $apc;
			}

			public function WPV() :object {
				return new class( $this->wpv ) {
					private WpvResultsSet $results;

					public function __construct( WpvResultsSet $results ) {
						$this->results = $results;
					}

					public function getResultsForDisplay() :WpvResultsSet {
						return $this->results;
					}
				};
			}

			public function APC() :object {
				return new class( $this->apc ) {
					private ApcResultsSet $results;

					public function __construct( ApcResultsSet $results ) {
						$this->results = $results;
					}

					public function getResultsForDisplay() :ApcResultsSet {
						return $this->results;
					}
				};
			}
		};
	}

	private function installTestControllerAndServices(
		array $vulnerableRows,
		array $abandonedRows,
		array $pluginVos,
		array $themeVos
	) :void {
		ServicesState::installItems( [
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpplugins' => new MaintenancePluginsService( [
				'updates'    => [],
				'plugins'    => [],
				'active'     => [],
				'plugin_vos' => $pluginVos,
			] ),
			'service_wpthemes' => new MaintenanceThemesService( [
				'updates'    => [],
				'themes'     => [],
				'current'    => '',
				'current_parent' => '',
				'theme_vos'  => $themeVos,
			] ),
		] );

		UnitTestControllerFactory::install(
			new class extends UnitTestPluginUrls {
				public function vulnerabilityLookupByPlugin( string $pluginSlug, string $version = '' ) :string {
					return '/lookup/plugin/'.$pluginSlug.'?version='.$version;
				}

				public function vulnerabilityLookupByTheme( string $stylesheet, string $version = '' ) :string {
					return '/lookup/theme/'.$stylesheet.'?version='.$version;
				}
			},
			null,
			(object)[
				'comps' => (object)[
					'scans' => $this->buildScansComponent( $vulnerableRows, $abandonedRows ),
				],
			]
		);
	}
}
