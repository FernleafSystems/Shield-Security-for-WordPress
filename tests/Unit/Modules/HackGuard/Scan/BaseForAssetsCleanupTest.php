<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler as ResultItemsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\BaseForAssets;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	Plugins,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

class BaseForAssetsCleanupTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_missing_plugin_asset_resolves_and_resets_memoized_counts() :void {
		$updater = new BaseForAssetsCleanupUpdater();
		$scans = new BaseForAssetsCleanupScans();
		$this->installController( $updater, $scans );
		ServicesState::installItems( [
			'service_wpplugins' => new BaseForAssetsCleanupPlugins( [] ),
			'service_wpthemes'  => new BaseForAssetsCleanupThemes( [] ),
		] );

		$changed = $this->newAssetScanController( [
			$this->newItem( ResultItemsHandler::ITEM_TYPE_PLUGIN, 'missing-plugin/plugin.php', 7 ),
		] )->cleanStalesResults();

		$this->assertTrue( $changed );
		$this->assertSame( [ 7 ], $updater->assetReplacedIds );
		$this->assertSame( 1, $scans->memoizationResets );
	}

	public function test_present_plugin_asset_does_not_resolve_or_reset_counts() :void {
		$updater = new BaseForAssetsCleanupUpdater();
		$scans = new BaseForAssetsCleanupScans();
		$this->installController( $updater, $scans );
		ServicesState::installItems( [
			'service_wpplugins' => new BaseForAssetsCleanupPlugins( [ 'present-plugin/plugin.php' ] ),
			'service_wpthemes'  => new BaseForAssetsCleanupThemes( [] ),
		] );

		$changed = $this->newAssetScanController( [
			$this->newItem( ResultItemsHandler::ITEM_TYPE_PLUGIN, 'present-plugin/plugin.php', 11 ),
		] )->cleanStalesResults();

		$this->assertFalse( $changed );
		$this->assertSame( [], $updater->assetReplacedIds );
		$this->assertSame( 0, $scans->memoizationResets );
	}

	public function test_missing_theme_asset_resolves_and_resets_memoized_counts() :void {
		$updater = new BaseForAssetsCleanupUpdater();
		$scans = new BaseForAssetsCleanupScans();
		$this->installController( $updater, $scans );
		ServicesState::installItems( [
			'service_wpplugins' => new BaseForAssetsCleanupPlugins( [] ),
			'service_wpthemes'  => new BaseForAssetsCleanupThemes( [] ),
		] );

		$changed = $this->newAssetScanController( [
			$this->newItem( ResultItemsHandler::ITEM_TYPE_THEME, 'missing-theme', 13 ),
		] )->cleanStalesResults();

		$this->assertTrue( $changed );
		$this->assertSame( [ 13 ], $updater->assetReplacedIds );
		$this->assertSame( 1, $scans->memoizationResets );
	}

	private function installController( BaseForAssetsCleanupUpdater $updater, BaseForAssetsCleanupScans $scans ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scan_result_items' => new BaseForAssetsCleanupResultItemsDb( $updater ),
		];
		$controller->comps = (object)[
			'scans' => $scans,
		];

		PluginControllerInstaller::install( $controller );
	}

	/**
	 * @param list<object> $items
	 */
	private function newAssetScanController( array $items ) :BaseForAssets {
		return new class( $items ) extends BaseForAssets {
			private array $items;

			public function __construct( array $items ) {
				$this->items = $items;
			}

			public function getAllResults() {
				return new class( $this->items ) {
					private array $items;

					public function __construct( array $items ) {
						$this->items = $items;
					}

					public function getAllItems() :array {
						return $this->items;
					}
				};
			}

			protected function newItemActionHandler() {
				throw new \BadMethodCallException( 'Not used by this test.' );
			}

			public function buildScanAction(
				?\FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO $scanAction = null
			) {
				unset( $scanAction );
				throw new \BadMethodCallException( 'Not used by this test.' );
			}
		};
	}

	private function newItem( string $itemType, string $itemID, int $resultItemID ) :object {
		return (object)[
			'VO' => (object)[
				'item_type'     => $itemType,
				'item_id'       => $itemID,
				'resultitem_id' => $resultItemID,
			],
		];
	}
}

class BaseForAssetsCleanupResultItemsDb {

	private BaseForAssetsCleanupUpdater $updater;

	public function __construct( BaseForAssetsCleanupUpdater $updater ) {
		$this->updater = $updater;
	}

	public function getQueryUpdater() :BaseForAssetsCleanupUpdater {
		return $this->updater;
	}
}

class BaseForAssetsCleanupUpdater {

	public array $assetReplacedIds = [];

	public function setItemAssetReplaced( int $recordID ) :bool {
		$this->assetReplacedIds[] = $recordID;
		return true;
	}
}

class BaseForAssetsCleanupScans {

	public int $memoizationResets = 0;

	public function resetScanResultsCountMemoization() :void {
		$this->memoizationResets++;
	}
}

class BaseForAssetsCleanupPlugins extends Plugins {

	private array $presentPlugins;

	public function __construct( array $presentPlugins ) {
		$this->presentPlugins = $presentPlugins;
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		unset( $reload );
		return \in_array( $file, $this->presentPlugins, true ) ? new BaseForAssetsCleanupPluginVo() : null;
	}
}

class BaseForAssetsCleanupThemes extends Themes {

	private array $presentThemes;

	public function __construct( array $presentThemes ) {
		$this->presentThemes = $presentThemes;
	}

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		unset( $reload );
		return \in_array( $stylesheet, $this->presentThemes, true ) ? new BaseForAssetsCleanupThemeVo() : null;
	}
}

class BaseForAssetsCleanupPluginVo extends WpPluginVo {

	public function __construct() {
	}
}

class BaseForAssetsCleanupThemeVo extends WpThemeVo {

	public function __construct() {
	}
}
