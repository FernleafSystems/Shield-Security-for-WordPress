<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	DB\ResultItems\Ops\Update,
	ModCon,
	Options
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class Wcf extends BaseForFiles {

	const SCAN_SLUG = 'wcf';

	public function getScanFileExclusions() :string {
		$pattern = '';

		$exclusions = $this->getOptions()->getDef( 'wcf_exclusions' );
		// Flywheel specific mods
		if ( defined( 'FLYWHEEL_PLUGIN_DIR' ) ) {
			$exclusions[] = 'wp-settings.php';
			$exclusions[] = 'wp-admin/includes/upgrade.php';
		}

		if ( is_array( $exclusions ) && !empty( $exclusions ) ) {
			$quoted = array_map(
				function ( $exclusion ) {
					return preg_quote( $exclusion, '#' );
				},
				$exclusions
			);
			$pattern = '#('.implode( '|', $quoted ).')#i';
		}
		return $pattern;
	}

	/**
	 * Builds a regex-ready pattern for matching file names to exclude from scan if they're missing
	 */
	public function getScanExclusionsForMissingItems() :string {
		$pattern = '';
		$exclusions = $this->getOptions()->getDef( 'wcf_exclusions_missing_only' );
		if ( !empty( $exclusions ) ) {
			$quoted = array_map(
				function ( $exclusion ) {
					return preg_quote( $exclusion, '#' );
				},
				$exclusions
			);
			$pattern = '#('.implode( '|', $quoted ).')#i';
		}
		return $pattern;
	}

	/**
	 * @return Scans\Wcf\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Wcf\Utilities\ItemActionHandler();
	}

	/**
	 * @param Scans\Wcf\ResultItem $item
	 */
	public function cleanStaleResultItem( $item ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$CFH = Services::CoreFileHashes();
		if ( !$CFH->isCoreFile( $item->path_full ) ) {
			$mod->getDbH_ResultItems()->getQueryDeleter()->deleteById( $item->VO->resultitem_id );
		}
		elseif ( $CFH->isCoreFileHashValid( $item->path_full ) ) {
			/** @var Update $updater */
			$updater = $mod->getDbH_ResultItems()->getQueryUpdater();
			$updater->setItemRepaired( $item->VO->resultitem_id );
		}
	}

	public function isCronAutoRepair() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isRepairFileWP();
	}

	public function isEnabled() :bool {
		return $this->getOptions()->isOpt( 'enable_core_file_integrity_scan', 'Y' );
	}

	protected function isPremiumOnly() :bool {
		return false;
	}

	/**
	 * @return Scans\Wcf\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Wcf\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}
}