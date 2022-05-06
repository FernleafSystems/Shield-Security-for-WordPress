<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\WildCardOptions;
use FernleafSystems\Wordpress\Services\Services;

class Options extends BaseShield\Options {

	public function getFilesToLock() :array {
		$locks = $this->getOpt( 'file_locker', [] );
		return is_array( $locks ) ? $locks : [];
	}

	public function getRepairAreas() :array {
		return is_array( $this->getOpt( 'file_repair_areas' ) ) ? $this->getOpt( 'file_repair_areas' ) : [];
	}

	public function getLastRealtimeScanAt( bool $update = false ) :int {
		$at = $this->getOpt( 'realtime_scan_last_at' );
		if ( empty( $at ) ) {
			$at = Services::Request()->ts();
			$this->setOpt( 'realtime_scan_last_at', $at );
		}
		if ( $update ) {
			$this->setOpt( 'realtime_scan_last_at', Services::Request()->ts() );
		}
		return $at;
	}

	/**
	 * @return string[] - precise REGEX patterns to match against PATH.
	 */
	public function getWhitelistedPathsAsRegex() :array {
		$paths = $this->getDef( 'default_whitelist_paths' );
		if ( $this->isPremium() ) {
			$paths = array_merge( $this->getOpt( 'scan_path_exclusions', [] ), $paths );
		}

		return array_map(
			function ( $value ) {
				return ( new WildCardOptions() )->buildFullRegexValue( $value, WildCardOptions::FILE_PATH_REL );
			},
			$paths
		);
	}

	public function getMalConfidenceBoundary() :int {
		return (int)apply_filters( 'shield/fp_confidence_boundary', 65 );
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
		$file = $this->getCon()->paths->forCacheItem( $fileName );
		if ( !empty( $file ) && $FS->exists( $file ) ) {
			$sigs = explode( "\n", $FS->getFileContent( $file, true ) );
		}
		else {
			$sigs = array_filter(
				array_map( 'trim',
					explode( "\n", Services::HttpRequest()->getContent( $url ) )
				),
				function ( $line ) {
					return ( strpos( $line, '#' ) !== 0 ) && strlen( $line ) > 0;
				}
			);

			if ( !empty( $file ) && !empty( $sigs ) ) {
				$FS->putFileContent( $file, implode( "\n", $sigs ), true );
			}
		}

		return is_array( $sigs ) ? $sigs : [];
	}

	public function isMalAutoRepairSurgical() :bool {
		return $this->isOpt( 'mal_autorepair_surgical', 'Y' );
	}

	public function isMalUseNetworkIntelligence() :bool {
		return $this->getMalConfidenceBoundary() > 0;
	}

	public function isAutoFilterResults() :bool {
		return (bool)apply_filters( 'shield/scan_auto_filter_results', true );
	}

	public function isPtgReinstallLinks() :bool {
		return $this->isOpt( 'ptg_reinstall_links', 'Y' ) && $this->isPremium();
	}

	public function isRepairFileAuto() :bool {
		return count( $this->getRepairAreas() ) > 0;
	}

	public function isRepairFilePlugin() :bool {
		return in_array( 'plugin', $this->getRepairAreas() );
	}

	public function isRepairFileTheme() :bool {
		return in_array( 'theme', $this->getRepairAreas() );
	}

	public function isRepairFileWP() :bool {
		return in_array( 'wp', $this->getRepairAreas() );
	}

	public function isWpvulnAutoupdatesEnabled() :bool {
		return $this->isOpt( 'wpvuln_scan_autoupdate', 'Y' );
	}

	public function getScanFrequency() :int {
		return (int)$this->getOpt( 'scan_frequency', 1 );
	}

	/**
	 * @return string[]
	 */
	public function getScanSlugs() :array {
		return $this->getDef( 'all_scan_slugs' );
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
		if ( !is_array( $toBuild ) ) {
			$toBuild = [];
		}
		if ( !empty( $toBuild ) ) {
			$wasCount = count( $toBuild );
			// We keep scans "to build" for no longer than a minute to prevent indefinite halting with failed Async HTTP.
			$toBuild = array_filter( $toBuild,
				function ( $toBuildAt ) {
					return is_int( $toBuildAt )
						   && Services::Request()->carbon()->subMinute()->timestamp < $toBuildAt;
				}
			);
			if ( $wasCount !== count( $toBuild ) ) {
				$this->setScansToBuild( $toBuild );
			}
		}
		return $toBuild;
	}

	/**
	 * @return $this
	 */
	public function setScansToBuild( array $scans ) {
		$this->setOpt( 'scans_to_build', array_intersect_key( $scans, array_flip( $this->getScanSlugs() ) ) );
		$this->getMod()->saveModOptions();
		return $this;
	}

	/**
	 * Provides an array where the key is the root dir, and the value is the specific file types.
	 * An empty array means all files.
	 * @return array[]
	 */
	public function getUfcScanDirectories() :array {
		$dirs = [
			path_join( ABSPATH, 'wp-admin' )    => [],
			path_join( ABSPATH, 'wp-includes' ) => []
		];

		if ( $this->isOpt( 'ufc_scan_uploads', 'Y' ) ) { // include uploads
			$uploadsDir = Services::WpGeneral()->getDirUploads();
			if ( !empty( $uploadsDir ) ) {
				$dirs[ $uploadsDir ] = [
					'php',
					'php5',
					'js',
				];
			}
		}

		return $dirs;
	}

	/**
	 * @return string
	 */
	public function getUnrecognisedFileScannerOption() {
		return $this->getOpt( 'enable_unrecognised_file_cleaner_scan', 'disabled' );
	}

	public function isUfsDeleteFiles() :bool {
		return $this->getUnrecognisedFileScannerOption() === 'enabled_delete_only';
	}

	public function isScanCron() :bool {
		return (bool)$this->getOpt( 'is_scan_cron' );
	}

	public function isEnabledAutoFileScanner() :bool {
		return $this->isOpt( 'enable_core_file_integrity_scan', 'Y' );
	}

	/**
	 * @return $this
	 */
	public function setIsScanCron( bool $isCron ) {
		return $this->setOpt( 'is_scan_cron', $isCron );
	}
}