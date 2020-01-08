<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Options extends Base\ShieldOptions {

	/**
	 * @return string[]
	 */
	public function getDbColumns_Scanner() {
		return $this->getDef( 'table_columns_scanner' );
	}

	/**
	 * @return string[]
	 */
	public function getDbColumns_ScanQueue() {
		return $this->getDef( 'table_columns_scanqueue' );
	}

	/**
	 * @return string
	 */
	public function getDbTable_Scanner() {
		return $this->getCon()->prefixOption( $this->getDef( 'table_name_scanner' ) );
	}

	/**
	 * @return string
	 */
	public function getDbTable_ScanQueue() {
		return $this->getCon()->prefixOption( $this->getDef( 'table_name_scanqueue' ) );
	}

	/**
	 * @return bool
	 */
	public function isApcEnabled() {
		return !$this->isOpt( 'enabled_scan_apc', 'disabled' );
	}

	/**
	 * @return int[] - keys are the unique report hash
	 */
	public function getMalFalsePositiveReports() {
		$aFP = $this->getOpt( 'mal_fp_reports', [] );
		return is_array( $aFP ) ? $aFP : [];
	}

	/**
	 * @param string $sReportHash
	 * @return bool
	 */
	public function isMalFalsePositiveReported( $sReportHash ) {
		return isset( $this->getMalFalsePositiveReports()[ $sReportHash ] );
	}

	/**
	 * @return int
	 */
	public function getMalConfidenceBoundary() {
		return (int)$this->getOpt( 'mal_fp_confidence' );
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
			$this->getDef( 'malware_whitelist_paths' )
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
	 * @param string $sFilename
	 * @param string $sUrl
	 * @return string[]
	 */
	public function getMalSignatures( $sFilename, $sUrl ) {
		$oWpFs = Services::WpFs();
		$sFile = $this->getCon()->getPluginCachePath( $sFilename );
		if ( $oWpFs->exists( $sFile ) ) {
			$aSigs = explode( "\n", $oWpFs->getFileContent( $sFile, true ) );
		}
		else {
			$aSigs = array_filter(
				array_map( 'trim',
					explode( "\n", Services::HttpRequest()->getContent( $sUrl ) )
				),
				function ( $sLine ) {
					return ( ( strpos( $sLine, '#' ) !== 0 ) && strlen( $sLine ) > 0 );
				}
			);

			if ( !empty( $aSigs ) ) {
				$oWpFs->putFileContent( $sFile, implode( "\n", $aSigs ), true );
			}
		}
		return $aSigs;
	}

	/**
	 * @return bool
	 */
	public function isMalAutoRepairPlugins() {
		return $this->isOpt( 'mal_autorepair_plugins', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isMalAutoRepairThemes() {
		return $this->isOpt( 'mal_autorepair_themes', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isMalAutoRepair() {
		return $this->isMalAutoRepairCore() || $this->isMalAutoRepairPlugins() || $this->isMalAutoRepairThemes()
			   || $this->isMalAutoRepairSurgical();
	}

	/**
	 * @return bool
	 */
	public function isMalAutoRepairCore() {
		return $this->isOpt( 'mal_autorepair_core', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isMalAutoRepairSurgical() {
		return $this->isOpt( 'mal_autorepair_surgical', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isMalScanEnabled() {
		return !$this->isOpt( 'mal_scan_enable', 'disabled' );
	}

	/**
	 * @return bool
	 */
	public function isMalUseNetworkIntelligence() {
		return $this->getMalConfidenceBoundary() > 0;
	}

	/**
	 * @return string[]
	 */
	public function getPtgFileExtensions() {
		$aExt = $this->getOpt( 'ptg_extensions' );
		return is_array( $aExt ) ? $aExt : [];
	}

	/**
	 * @return int
	 */
	public function getPtgScanDepth() {
		return 0;
	}

	/**
	 * @return bool
	 */
	public function isPtgEnabled() {
		return $this->isOpt( 'ptg_enable', 'enabled' ) && $this->isOptReqsMet( 'ptg_enable' );
	}

	/**
	 * @return bool
	 */
	public function isPtgReinstallLinks() {
		return $this->isPremium() && $this->isOpt( 'ptg_reinstall_links', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isWcfScanEnabled() {
		return $this->isOpt( 'enable_core_file_integrity_scan', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isWpvulnEnabled() {
		return $this->isPremium() && !$this->isOpt( 'enable_wpvuln_scan', 'disabled' );
	}

	/**
	 * @return bool
	 */
	public function isWpvulnAutoupdatesEnabled() {
		return $this->isOpt( 'wpvuln_scan_autoupdate', 'Y' );
	}

	/**
	 * @return int
	 */
	public function getScanFrequency() {
		return (int)$this->getOpt( 'scan_frequency', 1 );
	}

	/**
	 * @return string[]
	 */
	public function getScanSlugs() {
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
	 * @return array
	 */
	public function getUfcFileExclusions() {
		$aExclusions = $this->getOpt( 'ufc_exclusions', [] );
		if ( !is_array( $aExclusions ) ) {
			$aExclusions = [];
		}
		return $aExclusions;
	}

	/**
	 * Provides an array where the key is the root dir, and the value is the specific file types.
	 * An empty array means all files.
	 * @return string[]
	 */
	public function getUfcScanDirectories() {
		$aDirs = [
			path_join( ABSPATH, 'wp-admin' )    => [],
			path_join( ABSPATH, 'wp-includes' ) => []
		];

		if ( $this->isUfcScanUploads() ) {
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

	/**
	 * @return string
	 */
	public function isUfcDeleteFiles() {
		return in_array( $this->getUnrecognisedFileScannerOption(), [
			'enabled_delete_only',
			'enabled_delete_report'
		] );
	}

	/**
	 * @return bool
	 */
	public function isUfcEnabled() {
		return ( $this->getUnrecognisedFileScannerOption() != 'disabled' );
	}

	/**
	 * @return string
	 */
	public function isUfcSendReport() {
		return in_array( $this->getUnrecognisedFileScannerOption(), [
			'enabled_report_only',
			'enabled_delete_report'
		] );
	}

	/**
	 * @return bool
	 */
	public function isUfcScanUploads() {
		return $this->isOpt( 'ufc_scan_uploads', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isWcfScanAutoRepair() {
		return $this->isOpt( 'attempt_auto_file_repair', 'Y' );
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

	/**
	 * @return bool
	 */
	public function isScanCron() {
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