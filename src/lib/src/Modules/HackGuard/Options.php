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

	public function getFileScanAreas() :array {
		$opts = self::con()->opts;

		$areas = $this->getOpt( 'file_scan_areas', [] );
		if ( !self::con()->isPremiumActive() ) {
			$available = [];
			$valueOptions = \method_exists( $opts, 'optDef' ) ? $opts->optDef( 'file_scan_areas' )[ 'value_options' ]
				: $this->getOptProperty( 'file_scan_areas', 'value_options' );
			foreach ( $valueOptions as $valueOption ) {
				if ( empty( $valueOption[ 'premium' ] ) ) {
					$available[] = $valueOption[ 'value_key' ];
				}
			}
			$areas = \array_diff( $areas, $available );
		}

		return $areas;
	}

	public function getRepairAreas() :array {
		return $this->getOpt( 'file_repair_areas' );
	}

	/**
	 * @return string[] - precise REGEX patterns to match against PATH.
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
	 * @return string[]
	 */
	public function getMalSignaturesSimple() :array {
		return $this->getMalSignatures( 'malsigs_simple.txt', $this->getDef( 'url_mal_sigs_simple' ) );
	}

	/**
	 * @return string[]
	 */
	public function getMalSignaturesRegex() :array {
		return $this->getMalSignatures( 'malsigs_regex.txt', $this->getDef( 'url_mal_sigs_regex' ) );
	}

	/**
	 * @return string[]
	 */
	private function getMalSignatures( string $fileName, string $url ) :array {
		$FS = Services::WpFs();
		$file = self::con()->cache_dir_handler->cacheItemPath( $fileName );
		if ( !empty( $file ) && $FS->exists( $file ) ) {
			$sigs = \explode( "\n", $FS->getFileContent( $file, true ) );
		}
		else {
			$sigs = \array_filter(
				\array_map( '\trim',
					\explode( "\n", Services::HttpRequest()->getContent( $url ) )
				),
				function ( $line ) {
					return ( \strpos( $line, '#' ) !== 0 ) && \strlen( $line ) > 0;
				}
			);

			if ( !empty( $file ) && !empty( $sigs ) ) {
				$FS->putFileContent( $file, \implode( "\n", $sigs ), true );
			}
		}

		return \is_array( $sigs ) ? $sigs : [];
	}

	public function isAutoFilterResults() :bool {
		return (bool)apply_filters( 'shield/scan_auto_filter_results', true );
	}

	public function isRepairFilePlugin() :bool {
		return \in_array( 'plugin', $this->getRepairAreas() );
	}

	public function isRepairFileTheme() :bool {
		return \in_array( 'theme', $this->getRepairAreas() );
	}

	public function isRepairFileWP() :bool {
		return \in_array( 'wp', $this->getRepairAreas() );
	}

	public function getScanFrequency() :int {
		return (int)$this->getOpt( 'scan_frequency', 1 );
	}

	/**
	 * @return $this
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

	public function isScanCron() :bool {
		return (bool)$this->getOpt( 'is_scan_cron' );
	}

	public function isEnabledAutoFileScanner() :bool {
		return $this->isOpt( 'enable_core_file_integrity_scan', 'Y' );
	}

	public function setIsScanCron( bool $isCron ) {
		$this->setOpt( 'is_scan_cron', $isCron );
	}

	/**
	 * @deprecated 19.1
	 */
	private function cleanScanExclusions() {
	}
}