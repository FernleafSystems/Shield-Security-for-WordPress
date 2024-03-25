<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\WildCardOptions;
use FernleafSystems\Wordpress\Services\Services;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	/**
	 * @deprecated 19.1
	 */
	public function getFilesToLock() :array {
		return $this->getOpt( 'file_locker', [] );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getFileScanAreas() :array {
		return [];
	}

	/**
	 * @deprecated 19.1
	 */
	public function isRepairFilePlugin() :bool {
		return \in_array( 'plugin', $this->getRepairAreas() );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isRepairFileTheme() :bool {
		return \in_array( 'theme', $this->getRepairAreas() );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isRepairFileWP() :bool {
		return \in_array( 'wp', $this->getRepairAreas() );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getRepairAreas() :array {
		return $this->getOpt( 'file_repair_areas' );
	}

	/**
	 * @return string[] - precise REGEX patterns to match against PATH.
	 * @deprecated 19.1
	 */
	public function getWhitelistedPathsAsRegex() :array {
		$paths = $this->getDef( 'default_whitelist_paths' );
		if ( self::con()->isPremiumActive() ) {
			$paths = \array_merge( $this->getOpt( 'scan_path_exclusions', [] ), $paths );
		}

		return \array_map(
			function ( $value ) {
				return ( new WildCardOptions() )->buildFullRegexValue( $value, WildCardOptions::FILE_PATH_REL );
			},
			$paths
		);
	}

	/**
	 * @deprecated 19.1
	 */
	public function isAutoFilterResults() :bool {
		return (bool)apply_filters( 'shield/scan_auto_filter_results', true );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getScanFrequency() :int {
		return (int)$this->getOpt( 'scan_frequency', 1 );
	}

	/**
	 * @return $this
	 * @deprecated 19.1
	 */
	public function addRemoveScanToBuild( string $scan, bool $addScan = true ) {
		$scans = $this->getScansToBuild();
		if ( $addScan ) {
			$scans[ $scan ] = Services::Request()->ts();
		}
		else {
			unset( $scans[ $scan ] );
		}
		return $this->setScansToBuild( $scans );
	}

	/**
	 * @return int[] - keys are scan slugs
	 * @deprecated 19.1
	 */
	public function getScansToBuild() :array {
		$toBuild = $this->getOpt( 'scans_to_build', [] );
		if ( !\is_array( $toBuild ) ) {
			$toBuild = [];
		}
		if ( !empty( $toBuild ) ) {
			$wasCount = \count( $toBuild );
			// We keep scans "to build" for no longer than a minute to prevent indefinite halting with failed Async HTTP.
			$toBuild = \array_filter( $toBuild,
				function ( $toBuildAt ) {
					return \is_int( $toBuildAt )
						   && Services::Request()->carbon()->subMinute()->timestamp < $toBuildAt;
				}
			);
			if ( $wasCount !== \count( $toBuild ) ) {
				$this->setScansToBuild( $toBuild );
			}
		}
		return $toBuild;
	}

	/**
	 * @return $this
	 * @deprecated 19.1
	 */
	public function setScansToBuild( array $scans ) {
		$this->setOpt( 'scans_to_build',
			\array_intersect_key( $scans,
				\array_flip( self::con()->getModule_HackGuard()->getScansCon()->getScanSlugs() )
			)
		);
		self::con()->opts->store();
		return $this;
	}

	/**
	 * @deprecated 19.1
	 */
	public function isScanCron() :bool {
		return (bool)$this->getOpt( 'is_scan_cron' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledAutoFileScanner() :bool {
		return $this->isOpt( 'enable_core_file_integrity_scan', 'Y' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function setIsScanCron( bool $isCron ) {
		$this->setOpt( 'is_scan_cron', $isCron );
	}

	/**
	 * @deprecated 19.1
	 */
	private function cleanScanExclusions() {
	}
}