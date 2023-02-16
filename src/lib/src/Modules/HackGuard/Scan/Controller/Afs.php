<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	Lib,
	ModCon,
	Options,
	Scan
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems\Ops\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class Afs extends BaseForFiles {

	public const SCAN_SLUG = 'afs';
	use PluginCronsConsumer;

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
			'id'    => $this->getCon()->prefix( 'problems-'.$this->getSlug() ),
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
		( new Lib\Snapshots\StoreAction\ScheduleBuildAll() )
			->setMod( $this->getMod() )
			->schedule();
	}

	public function runHourlyCron() {
		( new Lib\Snapshots\StoreAction\CleanStale() )
			->setMod( $this->getMod() )
			->run();
		( new Lib\Snapshots\StoreAction\TouchAll() )
			->setMod( $this->getMod() )
			->run();
	}

	public function actionPluginReinstall( string $file ) :bool {
		$success = false;
		$WPP = Services::WpPlugins();
		$plugin = $WPP->getPluginAsVo( $file );
		if ( $plugin->isWpOrg() && $WPP->reinstall( $plugin->file ) ) {
			try {
				( new Lib\Snapshots\StoreAction\Delete() )
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

	/**
	 * Can only possibly repair themes, plugins or core files.
	 * @return Scans\Afs\ResultsSet
	 */
	protected function getItemsToAutoRepair() {
		/** @var Options $opts */
		$opts = $this->getOptions();

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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$FS = Services::WpFs();
		/** @var Update $updater */
		$updater = $mod->getDbH_ResultItems()->getQueryUpdater();

		if ( ( $item->is_unrecognised || $item->is_mal ) && !$FS->isFile( $item->path_full ) ) {
			$updater->setItemDeleted( $item->VO->resultitem_id );
		}
		elseif ( $item->is_in_core ) {
			$CFH = Services::CoreFileHashes();
			if ( $item->is_missing && !$CFH->isCoreFile( $item->path_full ) ) {
				$mod->getDbH_ResultItems()->getQueryDeleter()->deleteById( $item->VO->resultitem_id );
			}
			elseif ( $item->is_checksumfail && $CFH->isCoreFileHashValid( $item->path_full ) ) {
				$updater->setItemRepaired( $item->VO->resultitem_id );
			}
		}
		elseif ( $item->is_in_plugin || $item->is_in_theme ) {
			try {
				$verifiedHash = ( new Lib\Hashes\Query() )
					->setMod( $this->getMod() )
					->verifyHash( $item->path_full );
				if ( $item->is_checksumfail && $verifiedHash ) {
					/** @var Update $updater */
					$updater = $mod->getDbH_ResultItems()->getQueryUpdater();
					$updater->setItemRepaired( $item->VO->resultitem_id );
				}
			}
			catch ( Lib\Hashes\Exceptions\AssetHashesNotFound $e ) {
				// hashes are unavailable, so we do nothing
			}
			catch ( Lib\Hashes\Exceptions\NoneAssetFileException $e ) {
				// asset has probably been since removed
				$mod->getDbH_ResultItems()->getQueryDeleter()->deleteById( $item->VO->resultitem_id );
			}
			catch ( Lib\Hashes\Exceptions\UnrecognisedAssetFile $e ) {
				// unrecognised file
			}
			catch ( \Exception $e ) {
			}
		}
	}

	public function getQueueGroupSize() :int {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isOpt( 'optimise_scan_speed', 'Y' ) ? 80 : 45;
	}

	/**
	 * @return Scans\Afs\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Afs\Utilities\ItemActionHandler();
	}

	public function isCronAutoRepair() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isRepairFileAuto();
	}

	public function isEnabled() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledAutoFileScanner();
	}

	public function isEnabledMalwareScan() :bool {
		return $this->isEnabled() && !$this->isRestrictedMalwareScan();
	}

	public function isEnabledPluginThemeScan() :bool {
		return $this->isEnabled() && !$this->isRestrictedPluginThemeScan()
			   && $this->getCon()->cache_dir_handler->exists();
	}

	public function isRestrictedMalwareScan() :bool {
		return !$this->getCon()->isPremiumActive();
	}

	public function isRestrictedPluginThemeScan() :bool {
		return !$this->getCon()->isPremiumActive();
	}

	protected function isPremiumOnly() :bool {
		return false;
	}

	/**
	 * @return Scans\Afs\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Afs\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}

	/**
	 * Since we can't track site assets while the plugin is inactive, our snapshots and results
	 * are unreliable once the plugin has been deactivated.
	 */
	public function purge() {
		parent::purge();
		( new Lib\Snapshots\StoreAction\DeleteAll() )
			->setMod( $this->getMod() )
			->run();
	}
}