<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops as ResultItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	Lib,
	Scan
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class Afs extends Base {

	use PluginCronsConsumer;

	public const SCAN_SLUG = 'afs';

	protected function run() {
		parent::run();
		$this->setupCronHooks();
		( new Scan\Utilities\PtgAddReinstallLinks() )->execute();
		( new Lib\Snapshots\StoreAction\ScheduleBuildAll() )->execute();
	}

	/**
	 * @return array{name:string, subtitle:string}
	 */
	public function getStrings() :array {
		return [
			'name'     => __( 'WordPress Filesystem Scan', 'wp-simple-firewall' ),
			'subtitle' => __( 'Filesystem Scan looking for modified, missing and unrecognised files (use config to adjust scan areas)', 'wp-simple-firewall' ),
		];
	}

	public function getAdminMenuItems() :array {
		$items = [];
		$status = $this->mod()->getScansCon()->getScanResultsCount();

		$template = [
			'id'    => self::con()->prefix( 'problems-'.$this->getSlug() ),
			'title' => '<div class="wp-core-ui wp-ui-notification shield-counter"><span aria-hidden="true">%s</span></div>',
		];

		$count = $status->countMalware();
		if ( $count > 0 ) {
			$warning = $template;
			$warning[ 'id' ] .= '-malware';
			$warning[ 'title' ] = __( 'Potential Malware', 'wp-simple-firewall' ).sprintf( $warning[ 'title' ], $count );
			$warning[ 'warnings' ] = $count;
			$items[] = $warning;
		}

		$count = $status->countWPFiles();
		if ( $count > 0 ) {
			$warning = $template;
			$warning[ 'id' ] .= '-wp';
			$warning[ 'title' ] = __( 'WordPress Core Files', 'wp-simple-firewall' ).sprintf( $warning[ 'title' ], $count );
			$warning[ 'warnings' ] = $count;
			$items[] = $warning;
		}

		$count = $status->countPluginFiles();
		if ( $count > 0 ) {
			$warning = $template;
			$warning[ 'id' ] .= '-plugin';
			$warning[ 'title' ] = __( 'Plugin Files', 'wp-simple-firewall' ).sprintf( $warning[ 'title' ], $count );
			$warning[ 'warnings' ] = $count;
			$items[] = $warning;
		}

		$count = $status->countThemeFiles();
		if ( $count > 0 ) {
			$warning = $template;
			$warning[ 'id' ] .= '-theme';
			$warning[ 'title' ] = __( 'Theme Files', 'wp-simple-firewall' ).sprintf( $warning[ 'title' ], $count );
			$warning[ 'warnings' ] = $count;
			$items[] = $warning;
		}

		return $items;
	}

	public function buildScanResult( array $rawResult ) :ResultItemsDB\Record {
		$autoFiltered = $rawResult[ 'auto_filter' ] ?? false;

		/** @var ResultItemsDB\Record $record */
		$record = self::con()->db_con->dbhResultItems()->getRecord();
		$record->auto_filtered_at = $autoFiltered ? Services::Request()->ts() : 0;
		$record->item_id = $rawResult[ 'path_fragment' ];
		$record->item_type = ResultItemsDB\Handler::ITEM_TYPE_FILE;

		$metaToClear = [
			'auto_filter',
			'path_full',
			'scan',
			'hash',
		];
		foreach ( $metaToClear as $metaItem ) {
			unset( $rawResult[ $metaItem ] );
		}

		$meta = $rawResult;
		$record->meta = $meta;

		return $record;
	}

	public function runHourlyCron() {
		( new Lib\Snapshots\StoreAction\CleanStale() )->execute();
		( new Lib\Snapshots\StoreAction\TouchAll() )->execute();
	}

	public function actionPluginReinstall( string $file ) :bool {
		$success = false;
		$WPP = Services::WpPlugins();
		$plugin = $WPP->getPluginAsVo( $file );
		if ( $plugin->isWpOrg() && $WPP->reinstall( $plugin->file ) ) {
			try {
				( new Lib\Snapshots\StoreAction\Delete() )
					->setAsset( $plugin )
					->run();
				$success = true;
			}
			catch ( \Exception $e ) {
			}
		}
		return $success;
	}

	/**
	 * Can only possibly repair themes, plugins or core files.
	 */
	protected function getItemsToAutoRepair() :Scans\Afs\ResultsSet {
		$repairResults = $this->getNewResultsSet();

		/** @var Scans\Afs\ResultItem $item */
		foreach ( parent::getItemsToAutoRepair()->getAllItems() as $item ) {

			if ( $item->is_in_core && $this->isRepairFileWP() ) {
				$repairResults->addItem( $item );
			}
			if ( $item->is_in_plugin && $this->isRepairFilePlugin() ) {
				$repairResults->addItem( $item );
			}
			if ( $item->is_in_theme && $this->isRepairFileTheme() ) {
				$repairResults->addItem( $item );
			}
		}

		return $repairResults;
	}

	/**
	 * @param Scans\Afs\ResultItem $item
	 */
	public function cleanStaleResultItem( $item ) {
		$dbhResultItems = self::con()->db_con->dbhResultItems();
		/** @var ResultItemsDB\Update $updater */
		$updater = $dbhResultItems->getQueryUpdater();

		if ( ( $item->is_unrecognised || $item->is_mal ) && !Services::WpFs()->isAccessibleFile( $item->path_full ) ) {
			$updater->setItemDeleted( $item->VO->resultitem_id );
		}
		elseif ( $item->is_in_core ) {
			$CFH = Services::CoreFileHashes();
			if ( $item->is_missing && !$CFH->isCoreFile( $item->path_full ) ) {
				$dbhResultItems->getQueryDeleter()->deleteById( $item->VO->resultitem_id );
			}
			elseif ( $item->is_checksumfail && $CFH->isCoreFileHashValid( $item->path_full ) ) {
				$updater->setItemRepaired( $item->VO->resultitem_id );
			}
		}
		elseif ( $item->is_in_plugin || $item->is_in_theme ) {
			try {
				$verifiedHash = ( new Lib\Hashes\Query() )->verifyHash( $item->path_full );
				if ( $item->is_checksumfail && $verifiedHash ) {
					/** @var ResultItemsDB\Update $updater */
					$updater = $dbhResultItems->getQueryUpdater();
					$updater->setItemRepaired( $item->VO->resultitem_id );
				}
			}
			catch ( Lib\Hashes\Exceptions\AssetHashesNotFound $e ) {
				// hashes are unavailable, so we do nothing
			}
			catch ( Lib\Hashes\Exceptions\NonAssetFileException $e ) {
				// asset has probably been since removed
				$dbhResultItems->getQueryDeleter()->deleteById( $item->VO->resultitem_id );
			}
			catch ( Lib\Hashes\Exceptions\UnrecognisedAssetFile $e ) {
				// unrecognised file
			}
			catch ( \Exception $e ) {
			}
		}
	}

	public function getQueueGroupSize() :int {
		return self::con()->opts->optIs( 'optimise_scan_speed', 'Y' ) ? 80 : 45;
	}

	protected function newItemActionHandler() :Scans\Afs\Utilities\ItemActionHandler {
		return new Scans\Afs\Utilities\ItemActionHandler();
	}

	public function isCronAutoRepair() :bool {
		return \count( $this->getRepairAreas() ) > 0;
	}

	public function isEnabled() :bool {
		$con = self::con();
		return $con->comps !== null && $con->comps->opts_lookup->optIsAndModForOptEnabled( 'enable_core_file_integrity_scan', 'Y' );
	}

	public function isEnabledMalwareScanPHP() :bool {
		return $this->isEnabled()
			   && \in_array( 'malware_php', $this->getFileScanAreas() )
			   && self::con()->caps->canScanMalwareLocal();
	}

	public function isScanEnabledPlugins() :bool {
		return $this->isEnabled()
			   && \in_array( 'plugins', $this->getFileScanAreas() )
			   && self::con()->cache_dir_handler->exists()
			   && self::con()->caps->canScanPluginsThemesLocal();
	}

	public function isScanEnabledThemes() :bool {
		return $this->isEnabled()
			   && \in_array( 'themes', $this->getFileScanAreas() )
			   && self::con()->cache_dir_handler->exists()
			   && self::con()->caps->canScanPluginsThemesLocal();
	}

	public function isScanEnabledWpContent() :bool {
		return $this->isEnabled()
			   && \in_array( 'wpcontent', $this->getFileScanAreas() )
			   && self::con()->caps->canScanAllFiles();
	}

	public function isScanEnabledWpCore() :bool {
		return $this->isEnabled() && \in_array( 'wp', $this->getFileScanAreas() );
	}

	public function isScanEnabledWpRoot() :bool {
		return $this->isEnabled()
			   && \in_array( 'wproot', $this->getFileScanAreas() )
			   && self::con()->caps->canScanAllFiles();
	}

	protected function isPremiumOnly() :bool {
		return false;
	}

	public function buildScanAction() :Scans\Afs\ScanActionVO {
		return ( new Scans\Afs\BuildScanAction() )
			->setScanActionVO( $this->getScanActionVO() )
			->build()
			->getScanActionVO();
	}

	/**
	 * Since we can't track site assets while the plugin is inactive, our snapshots and results
	 * are unreliable once the plugin has been deactivated.
	 */
	public function purge() {
		parent::purge();
		( new Lib\Snapshots\StoreAction\DeleteAll() )->execute();
	}

	public function getFileScanAreas() :array {
		$areas = [];

		$opts = self::con()->opts;
		if ( \method_exists( $opts, 'optGet' ) ) {
			$areas = $opts->optGet( 'file_scan_areas' );
			if ( !self::con()->isPremiumActive() ) {
				$available = [];
				foreach ( $opts->optDef( 'file_scan_areas' )[ 'value_options' ] as $valueOption ) {
					if ( empty( $valueOption[ 'premium' ] ) ) {
						$available[] = $valueOption[ 'value_key' ];
					}
				}
				$areas = \array_diff( $areas, $available );
			}
		}

		return $areas;
	}

	public function isRepairFilePlugin() :bool {
		return $this->isScanEnabledPlugins() && \in_array( 'plugin', $this->getRepairAreas() );
	}

	public function isRepairFileTheme() :bool {
		return $this->isScanEnabledThemes() && \in_array( 'theme', $this->getRepairAreas() );
	}

	public function isRepairFileWP() :bool {
		return $this->isScanEnabledWpCore() && \in_array( 'wp', $this->getRepairAreas() );
	}

	public function getRepairAreas() :array {
		return self::con()->opts->optGet( 'file_repair_areas' );
	}
}