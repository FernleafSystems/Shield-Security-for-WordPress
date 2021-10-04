<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
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
	 * @return bool
	 */
	protected function isResultItemStale( $item ) :bool {
		$CFH = Services::CoreFileHashes();
		return !$CFH->isCoreFile( $item->path_full ) || $CFH->isCoreFileHashValid( $item->path_full );
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