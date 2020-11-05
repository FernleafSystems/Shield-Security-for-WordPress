<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Options extends BaseShield\Options {

	public function getFilesToLock() :array {
		$locks = $this->getOpt( 'file_locker', [] );
		return is_array( $locks ) ? $locks : [];
	}

	public function getRepairAreas() :array {
		return is_array( $this->getOpt( 'file_repair_areas' ) ) ? $this->getOpt( 'file_repair_areas' ) : [];
	}

	/**
	 * @return int[] - keys are the unique report hash
	 */
	public function getMalFalsePositiveReports() :array {
		$FP = $this->getOpt( 'mal_fp_reports', [] );
		return is_array( $FP ) ? $FP : [];
	}

	public function isMalFalsePositiveReported( string $hash ) :bool {
		return isset( $this->getMalFalsePositiveReports()[ $hash ] );
	}

	public function getMalConfidenceBoundary() :int {
		return (int)apply_filters( 'icwp_shield_fp_confidence_boundary', 50 );
	}

	/**
	 * We do some WP Content dir replacement as there may be custom wp-content dir defines
	 * @return string[]
	 */
	public function getMalWhitelistPaths() {
		return array_map(
			function ( $sFragment ) {
				return str_replace(
					wp_normalize_path( ABSPATH.'wp-content' ),
					rtrim( wp_normalize_path( WP_CONTENT_DIR ), '/' ),
					wp_normalize_path( path_join( ABSPATH, ltrim( $sFragment, '/' ) ) )
				);
			},
			apply_filters( 'icwp_shield_malware_whitelist_paths', $this->getDef( 'malware_whitelist_paths' ) )
		);
	}

	/**
	 * @return int
	 */
	public function getMalQueueExpirationInterval() {
		return MINUTE_IN_SECONDS*10;
	}

	/**
	 * @return string[]
	 */
	public function getMalSignaturesSimple() {
		return $this->getMalSignatures( 'malsigs_simple.txt', $this->getDef( 'url_mal_sigs_simple' ) );
	}

	/**
	 * @return string[]
	 */
	public function getMalSignaturesRegex() {
		return $this->getMalSignatures( 'malsigs_regex.txt', $this->getDef( 'url_mal_sigs_regex' ) );
	}

	/**
	 * @param string $fileName
	 * @param string $url
	 * @return string[]
	 */
	public function getMalSignatures( string $fileName, string $url ) {
		$FS = Services::WpFs();
		$file = $this->getCon()->getPluginCachePath( $fileName );
		if ( $FS->exists( $file ) ) {
			$sigs = explode( "\n", $FS->getFileContent( $file, true ) );
		}
		else {
			$sigs = array_filter(
				array_map( 'trim',
					explode( "\n", Services::HttpRequest()->getContent( $url ) )
				),
				function ( $line ) {
					return ( ( strpos( $line, '#' ) !== 0 ) && strlen( $line ) > 0 );
				}
			);

			if ( !empty( $sigs ) ) {
				$FS->putFileContent( $file, implode( "\n", $sigs ), true );
			}
		}
		return $sigs;
	}

	public function isMalAutoRepairSurgical() :bool {
		return $this->isOpt( 'mal_autorepair_surgical', 'Y' );
	}

	public function isMalUseNetworkIntelligence() :bool {
		return $this->getMalConfidenceBoundary() > 0;
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
	 * @param string $sScan
	 * @param bool   $bAdd
	 * @return Options
	 */
	public function addRemoveScanToBuild( $sScan, $bAdd = true ) {
		$aS = $this->getScansToBuild();
		if ( $bAdd ) {
			$aS[ $sScan ] = Services::Request()->ts();
		}
		elseif ( isset( $aS[ $sScan ] ) ) {
			unset( $aS[ $sScan ] );
		}
		return $this->setScansToBuild( $aS );
	}

	/**
	 * @return int[] - keys are scan slugs
	 */
	public function getScansToBuild() {
		$aS = $this->getOpt( 'scans_to_build', [] );
		if ( !is_array( $aS ) ) {
			$aS = [];
		}
		if ( !empty( $aS ) ) {
			// We keep scans "to build" for no longer than a minute to prevent indefinite halting with failed Async HTTP.
			$aS = array_filter( $aS,
				function ( $nToBuildAt ) {
					return is_int( $nToBuildAt )
						   && Services::Request()->carbon()->subMinute()->timestamp < $nToBuildAt;
				}
			);
			$this->setScansToBuild( $aS );
		}
		return $aS;
	}

	/**
	 * @param array $aScans
	 * @return Options
	 */
	public function setScansToBuild( $aScans ) {
		return $this->setOpt( 'scans_to_build', array_intersect_key( $aScans, array_flip( $this->getScanSlugs() ) ) );
	}

	/**
	 * Provides an array where the key is the root dir, and the value is the specific file types.
	 * An empty array means all files.
	 * @return array[]
	 */
	public function getUfcScanDirectories() :array {
		$aDirs = [
			path_join( ABSPATH, 'wp-admin' )    => [],
			path_join( ABSPATH, 'wp-includes' ) => []
		];

		if ( $this->isOpt( 'ufc_scan_uploads', 'Y' ) ) { // include uploads
			$sUploadsDir = Services::WpGeneral()->getDirUploads();
			if ( !empty( $sUploadsDir ) ) {
				$aDirs[ $sUploadsDir ] = [
					'php',
					'php5',
					'js',
				];
			}
		}

		return $aDirs;
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

	/**
	 * @return string
	 */
	public function getWcfFileExclusions() {
		$sPattern = null;

		$aExclusions = $this->getOptions()->getDef( 'wcf_exclusions' );
		$aExclusions = is_array( $aExclusions ) ? $aExclusions : [];
		// Flywheel specific mods
		if ( defined( 'FLYWHEEL_PLUGIN_DIR' ) ) {
			$aExclusions[] = 'wp-settings.php';
			$aExclusions[] = 'wp-admin/includes/upgrade.php';
		}

		if ( is_array( $aExclusions ) && !empty( $aExclusions ) ) {
			$aQuoted = array_map(
				function ( $sExcl ) {
					return preg_quote( $sExcl, '#' );
				},
				$aExclusions
			);
			$sPattern = '#('.implode( '|', $aQuoted ).')#i';
		}
		return $sPattern;
	}

	/**
	 * Builds a regex-ready pattern for matching file names to exclude from scan if they're missing
	 * @return string|null
	 */
	public function getWcfMissingExclusions() {
		$sPattern = null;
		$aExclusions = $this->getOptions()->getDef( 'wcf_exclusions_missing_only' );
		if ( is_array( $aExclusions ) && !empty( $aExclusions ) ) {
			$aQuoted = array_map(
				function ( $sExcl ) {
					return preg_quote( $sExcl, '#' );
				},
				$aExclusions
			);
			$sPattern = '#('.implode( '|', $aQuoted ).')#i';
		}
		return $sPattern;
	}

	public function isScanCron() :bool {
		return (bool)$this->getOpt( 'is_scan_cron' );
	}

	/**
	 * @param bool $bIsScanCron
	 * @return $this
	 */
	public function setIsScanCron( $bIsScanCron ) {
		return $this->setOpt( 'is_scan_cron', $bIsScanCron );
	}

	/**
	 * @param array $aFP
	 * @return $this
	 */
	public function setMalFalsePositiveReports( array $aFP ) {
		return $this->setOpt( 'mal_fp_reports', array_filter(
			$aFP,
			function ( $nTS ) {
				return $nTS > Services::Request()->carbon()->subMonth()->timestamp;
			}
		) );
	}
}