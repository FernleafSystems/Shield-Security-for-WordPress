<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops as ResultItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	Lib\Hashes,
	Lib\Hashes\Exceptions,
	Lib\Snapshots\StoreAction,
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
		( new StoreAction\ScheduleBuildAll() )->execute();
		add_action( 'upgrader_process_complete', [ $this, 'queueAssetScansFromUpgraderProcessComplete' ], 10, 2 );
		add_filter( 'upgrader_post_install', [ $this, 'queueAssetScansFromUpgraderPostInstall' ], 10, 2 );
		add_action( 'pre_uninstall_plugin', [ $this, 'queuePluginAssetScan' ] );
		add_action( 'deleted_plugin', [ $this, 'queuePluginAssetScan' ] );
		add_action( 'deleted_theme', [ $this, 'queueThemeAssetScan' ], 10, 2 );
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
		$status = self::con()->comps->scans->getScanResultsCount();

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
		$record = self::con()->db_con->scan_result_items->getRecord();
		$record->scan = $this->getSlug();
		$record->auto_filtered_at = $autoFiltered ? Services::Request()->ts() : 0;
		$record->item_id = $rawResult[ 'path_fragment' ];
		$record->item_type = ResultItemsDB\Handler::ITEM_TYPE_FILE;
		$record->last_seen_at = Services::Request()->ts();
		$record->resolved_at = 0;
		$record->resolution_reason = '';

		if ( !empty( $rawResult[ 'is_in_core' ] ) ) {
			$record->asset_type = 'core';
			$record->asset_key = 'core';
		}
		elseif ( !empty( $rawResult[ 'is_in_plugin' ] ) ) {
			$record->asset_type = 'plugin';
			$record->asset_key = (string)( $rawResult[ 'ptg_slug' ] ?? '' );
		}
		elseif ( !empty( $rawResult[ 'is_in_theme' ] ) ) {
			$record->asset_type = 'theme';
			$record->asset_key = (string)( $rawResult[ 'ptg_slug' ] ?? '' );
		}
		else {
			$record->asset_type = 'other';
			$record->asset_key = '';
		}

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
		( new StoreAction\CleanStale() )->execute();
		( new StoreAction\TouchAll() )->execute();
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
	public function cleanStaleResultItem( $item ) :bool {
		$dbhResultItems = self::con()->db_con->scan_result_items;
		/** @var ResultItemsDB\Update $updater */
		$updater = $dbhResultItems->getQueryUpdater();

		$changed = false;
		if ( ( $item->is_unrecognised || $item->is_mal || $item->is_unidentified )
			 && !$item->VO->isDeleted()
			 && !Services::WpFs()->isAccessibleFile( $item->path_full ) ) {
			$changed = $updater->setItemAssetReplaced( $item->VO->resultitem_id );
		}
		elseif ( $item->is_in_core ) {
			$CFH = Services::CoreFileHashes();
			if ( $item->is_missing && !$CFH->isCoreFile( $item->path_full ) ) {
				$changed = $updater->setItemAssetReplaced( $item->VO->resultitem_id );
			}
			elseif ( $item->is_checksumfail && $CFH->isCoreFileHashValid( $item->path_full ) ) {
				$changed = $updater->setItemRepaired( $item->VO->resultitem_id );
			}
		}
		elseif ( $item->is_in_plugin || $item->is_in_theme ) {
			try {
				$verifiedHash = ( new Hashes\Query() )->verifyHash( $item->path_full );
				if ( !$item->VO->isRepaired() && $item->is_checksumfail && $verifiedHash ) {
					$changed = $updater->setItemRepaired( $item->VO->resultitem_id );
				}
			}
			catch ( Exceptions\AssetHashesNotFound $e ) {
				// hashes are unavailable, so we do nothing
			}
			catch ( Exceptions\NonAssetFileException $e ) {
				$changed = $updater->setItemAssetReplaced( $item->VO->resultitem_id );
			}
			catch ( Exceptions\UnrecognisedAssetFile $e ) {
				// unrecognised file
			}
			catch ( \Exception $e ) {
			}
		}

		return $changed;
	}

	protected function handleResultsChanged() :void {
		unset( $this->latestResults );
		self::con()->comps->scans->resetScanResultsCountMemoization();
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
		return self::con()->opts->optIs( 'enable_core_file_integrity_scan', 'Y' );
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

	public function buildScanAction( ?Scans\Base\BaseScanActionVO $scanAction = null ) :Scans\Afs\ScanActionVO {
		return ( new Scans\Afs\BuildScanAction() )
			->setScanActionVO( $scanAction ?? $this->newScanActionVO() )
			->build()
			->getScanActionVO();
	}

	public function queueAssetScansFromUpgraderProcessComplete( $handler, $data ) :void {
		unset( $handler );

		if ( !\is_array( $data ) ) {
			return;
		}

		if ( ( $data[ 'action' ] ?? null ) === 'update' && ( $data[ 'type' ] ?? null ) === 'plugin' ) {
			foreach ( \array_filter( \is_array( $data[ 'plugins' ] ?? null ) ? $data[ 'plugins' ] : [] ) as $plugin ) {
				$this->queuePluginAssetScan( (string)$plugin );
			}
		}

		if ( ( $data[ 'action' ] ?? null ) === 'update' && ( $data[ 'type' ] ?? null ) === 'theme' ) {
			foreach ( \array_filter( \is_array( $data[ 'themes' ] ?? null ) ? $data[ 'themes' ] : [] ) as $theme ) {
				$this->queueThemeAssetScan( (string)$theme, true );
			}
		}
	}

	public function queueAssetScansFromUpgraderPostInstall( $response, $hookExtra ) {
		if ( \is_array( $hookExtra ) && ( !empty( $hookExtra[ 'plugin' ] ) || !empty( $hookExtra[ 'theme' ] ) ) ) {
			add_action( self::con()->prefix( 'pre_plugin_shutdown' ), function () use ( $hookExtra ) {
				if ( !empty( $hookExtra[ 'plugin' ] ) ) {
					$this->queuePluginAssetScan( (string)$hookExtra[ 'plugin' ] );
				}
				if ( !empty( $hookExtra[ 'theme' ] ) ) {
					$this->queueThemeAssetScan( (string)$hookExtra[ 'theme' ], true );
				}
			} );
		}
		return $response;
	}

	public function queuePluginAssetScan( string $plugin ) :void {
		if ( $plugin !== '' ) {
			self::con()->comps->scans->startAfsAssetScan( 'plugin', $plugin );
		}
	}

	public function queueThemeAssetScan( string $stylesheet, bool $wasDeleted = true ) :void {
		if ( $wasDeleted && $stylesheet !== '' ) {
			self::con()->comps->scans->startAfsAssetScan( 'theme', $stylesheet );
		}
	}

	/**
	 * Since we can't track site assets while the plugin is inactive, our snapshots and results
	 * are unreliable once the plugin has been deactivated.
	 */
	public function purge() {
		parent::purge();
		( new StoreAction\DeleteAll() )->execute();
		return $this;
	}

	public function getFileScanAreas() :array {
		$areas = self::con()->opts->optGet( 'file_scan_areas' );
		if ( !self::con()->isPremiumActive() ) {
			$available = [];
			foreach ( self::con()->opts->optDef( 'file_scan_areas' )[ 'value_options' ] as $valueOption ) {
				if ( empty( $valueOption[ 'premium' ] ) ) {
					$available[] = $valueOption[ 'value_key' ];
				}
			}
			$areas = \array_diff( $areas, $available );
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
