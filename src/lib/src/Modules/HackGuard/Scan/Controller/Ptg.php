<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

class Ptg extends BaseForAssets {

	const SCAN_SLUG = 'ptg';

	protected function run() {
		parent::run();
		( new HackGuard\Scan\Utilities\PtgAddReinstallLinks() )
			->setScanController( $this )
			->execute();

		$this->setupCronHooks();
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		add_action( $this->getCon()->prefix( 'plugin_shutdown' ), [ $this, 'onModuleShutdown' ] );
	}

	public function onWpLoaded() {
		( new StoreAction\ScheduleBuildAll() )
			->setMod( $this->getMod() )
			->hookBuild();
	}

	public function onModuleShutdown() {
		( new StoreAction\ScheduleBuildAll() )
			->setMod( $this->getMod() )
			->schedule();
	}

	public function runHourlyCron() {
		( new StoreAction\TouchAll() )
			->setMod( $this->getMod() )
			->run();
		( new StoreAction\CleanAll() )
			->setMod( $this->getMod() )
			->run();
	}

	/**
	 * @return Scans\Ptg\ResultsSet
	 */
	protected function getItemsToAutoRepair() {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		/** @var Scans\Ptg\ResultsSet $results */
		$results = parent::getItemsToAutoRepair();

		if ( !$opts->isRepairFilePlugin() || !$opts->isRepairFileTheme() ) {
			if ( $opts->isRepairFileTheme() ) {
				$results = $results->getResultsForThemesContext();
			}
			elseif ( $opts->isRepairFilePlugin() ) {
				$results = $results->getResultsForPluginsContext();
			}

			/** @var Scans\Ptg\ResultItem $item */
			foreach ( $results->getItems() as $item ) {
				if ( $item->is_unrecognised ) {
					$results->removeItem( $item );
				}
			}
		}

		return $results;
	}

	public function isCronAutoRepair() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isRepairFilePlugin() || $opts->isRepairFileTheme();
	}

	/**
	 * @param Scans\Ptg\ResultItem $item
	 * @return bool
	 */
	protected function isResultItemStale( $item ) :bool {
		$FS = Services::WPFS();
		$stale = parent::isResultItemStale( $item )
				 || ( ( $item->is_unrecognised || $item->is_different ) && !$FS->isFile( $item->path_full ) );

		if ( !$stale ) {
			$asset = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $item->path_full );
			if ( empty( $asset ) ) {
				$asset = ( new WpOrg\Theme\Files() )->findThemeFromFile( $item->path_full );
			}
			$stale = empty( $asset );
		}

		return $stale;
	}

	/**
	 * @return Scans\Ptg\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Ptg\Utilities\ItemActionHandler();
	}

	public function actionPluginReinstall( string $file ) :bool {
		$success = false;
		$WPP = Services::WpPlugins();
		$plugin = $WPP->getPluginAsVo( $file );
		if ( $plugin->isWpOrg() && $WPP->reinstall( $plugin->file ) ) {
			try {
				( new HackGuard\Lib\Snapshots\StoreAction\Build() )
					->setMod( $this->getMod() )
					->setAsset( $plugin )
					->run();
				$success = true;
			}
			catch ( \Exception $e ) {
			}
		}
		return $success;
	}

	public function isEnabled() :bool {
		return $this->getOptions()->isOpt( 'ptg_enable', 'Y' );
	}

	public function isReady() :bool {
		return parent::isReady() && $this->getCon()->hasCacheDir();
	}

	/**
	 * Since we can't track site assets while the plugin is inactive, our snapshots and results
	 * are unreliable once the plugin has been deactivated.
	 */
	public function purge() {
		parent::purge();
		( new HackGuard\Lib\Snapshots\StoreAction\DeleteAll() )
			->setMod( $this->getMod() )
			->run();
	}

	/**
	 * @return Scans\Ptg\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Ptg\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}

	public function buildScanResult( array $rawResult ) :ScanResults\Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var ScanResults\Ops\Record $record */
		$record = $mod->getDbH_ScanResults()->getRecord();
		$record->meta = $rawResult;
		$record->hash = $rawResult[ 'hash' ];
		$record->item_id = $rawResult[ 'path_fragment' ];
		$record->item_type = 'f';
		return $record;
	}
}