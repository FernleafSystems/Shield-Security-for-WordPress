<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	Lib,
	Scan
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems\Ops\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class Afs extends BaseForFiles {

	use PluginCronsConsumer;

	public const SCAN_SLUG = 'afs';

	protected function run() {
		parent::run();
		( new Scan\Utilities\PtgAddReinstallLinks() )
			->setScanController( $this )
			->execute();

		$this->setupCronHooks();
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
	}

	public function getAdminMenuItems() :array {
		$items = [];
		$status = $this->getScansController()->getScanResultsCount();

		$template = [
			'id'    => $this->con()->prefix( 'problems-'.$this->getSlug() ),
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

	public function onWpLoaded() {
		( new Lib\Snapshots\StoreAction\ScheduleBuildAll() )->schedule();
	}

	public function runHourlyCron() {
		( new Lib\Snapshots\StoreAction\CleanStale() )->run();
		( new Lib\Snapshots\StoreAction\TouchAll() )->run();
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
		$opts = $this->opts();

		$repairResults = $this->getNewResultsSet();

		/** @var Scans\Afs\ResultItem $item */
		foreach ( parent::getItemsToAutoRepair()->getAllItems() as $item ) {

			if ( $item->is_in_core && $opts->isRepairFileWP() ) {
				$repairResults->addItem( $item );
			}
			if ( $item->is_in_plugin && $opts->isRepairFilePlugin() ) {
				$repairResults->addItem( $item );
			}
			if ( $item->is_in_theme && $opts->isRepairFileTheme() ) {
				$repairResults->addItem( $item );
			}
		}

		return $repairResults;
	}

	/**
	 * @param Scans\Afs\ResultItem $item
	 */
	public function cleanStaleResultItem( $item ) {
		$dbhResultItems = $this->mod()->getDbH_ResultItems();
		/** @var Update $updater */
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
					/** @var Update $updater */
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
		return $this->opts()->isOpt( 'optimise_scan_speed', 'Y' ) ? 80 : 45;
	}

	protected function newItemActionHandler() :Scans\Afs\Utilities\ItemActionHandler {
		return new Scans\Afs\Utilities\ItemActionHandler();
	}

	public function isCronAutoRepair() :bool {
		return count( $this->opts()->getRepairAreas() ) > 0;
	}

	public function isEnabled() :bool {
		return $this->opts()->isEnabledAutoFileScanner();
	}

	public function isEnabledMalwareScanPHP() :bool {
		return $this->isEnabled()
			   && \in_array( 'malware_php', $this->opts()->getFileScanAreas() )
			   && $this->con()->caps->canScanMalwareLocal();
	}

	public function isScanEnabledPlugins() :bool {
		return $this->isEnabled()
			   && \in_array( 'plugins', $this->opts()->getFileScanAreas() )
			   && $this->con()->cache_dir_handler->exists()
			   && $this->con()->caps->canScanPluginsThemesLocal();
	}

	public function isScanEnabledThemes() :bool {
		return $this->isEnabled()
			   && \in_array( 'themes', $this->opts()->getFileScanAreas() )
			   && $this->con()->cache_dir_handler->exists()
			   && $this->con()->caps->canScanPluginsThemesLocal();
	}

	public function isScanEnabledWpContent() :bool {
		return $this->isEnabled()
			   && \in_array( 'wpcontent', $this->opts()->getFileScanAreas() )
			   && $this->con()->caps->canScanAllFiles();
	}

	public function isScanEnabledWpRoot() :bool {
		return $this->isEnabled()
			   && \in_array( 'wproot', $this->opts()->getFileScanAreas() )
			   && $this->con()->caps->canScanAllFiles();
	}

	protected function isPremiumOnly() :bool {
		return false;
	}

	/**
	 * @throws \Exception
	 */
	public function buildScanAction() {
		( new Scans\Afs\BuildScanAction() )
			->setScanController( $this )
			->build();
		return $this->getScanActionVO();
	}

	/**
	 * Since we can't track site assets while the plugin is inactive, our snapshots and results
	 * are unreliable once the plugin has been deactivated.
	 */
	public function purge() {
		parent::purge();
		( new Lib\Snapshots\StoreAction\DeleteAll() )->run();
	}
}