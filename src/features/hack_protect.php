<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_HackProtect', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_HackProtect extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return string
	 */
	public function getUnrecognisedFileScannerOption() {
		return $this->getOpt( 'enable_unrecognised_file_cleaner_scan', 'disabled' );
	}

	/**
	 * @param string $sOption
	 * @return $this
	 */
	public function setUfcOption( $sOption ) {
		return $this->setOpt( 'enable_unrecognised_file_cleaner_scan', $sOption );
	}

	/**
	 * @return array
	 */
	public function getUfcFileExclusions() {
		$aExclusions = $this->getOpt( 'ufc_exclusions', array() );
		if ( empty( $aExclusions ) || !is_array( $aExclusions ) ) {
			$aExclusions = array();
		}
		return $aExclusions;
	}

	/**
	 * @param array $aExclusions
	 * @return $this
	 */
	public function setUfcFileExclusions( $aExclusions ) {
		if ( !is_array( $aExclusions ) ) {
			$aExclusions = array();
		}
		return $this->setOpt( 'ufc_exclusions', array_filter( array_map( 'trim', $aExclusions ) ) );
	}

	/**
	 */
	protected function doExtraSubmitProcessing() {
		$this->cleanFileExclusions();
	}

	/**
	 * @return $this
	 */
	protected function cleanFileExclusions() {
		$aExclusions = array();

		$oFS = $this->loadFS();
		foreach ( $this->getUfcFileExclusions() as $nKey => $sExclusion ) {
			$sExclusion = $oFS->normalizeFilePathDS( trim( $sExclusion ) );

			if ( preg_match( '/^#(.+)#$/', $sExclusion, $aMatches ) ) { // it's regex
				// ignore it
			}
			else if ( strpos( $sExclusion, '/' ) === false ) { // filename only
				$sExclusion = trim( preg_replace( '#[^\.0-9a-z_-]#i', '', $sExclusion ) );
			}

			if ( !empty( $sExclusion ) ) {
				$aExclusions[] = $sExclusion;
			}
		}

		return $this->setOpt( 'ufc_exclusions', array_unique( $aExclusions ) );
	}

	/**
	 * @return string
	 */
	public function isUfsDeleteFiles() {
		return in_array( $this->getUnrecognisedFileScannerOption(), array(
			'enabled_delete_only',
			'enabled_delete_report'
		) );
	}

	/**
	 * @return bool
	 */
	public function isUfsEnabled() {
		return ( $this->getUnrecognisedFileScannerOption() != 'disabled' );
	}

	/**
	 * @return bool
	 */
	public function isUfsScanUploads() {
		return $this->getOptIs( 'ufc_scan_uploads', 'Y' );
	}

	/**
	 * @return string
	 */
	public function isUfsSendReport() {
		return in_array( $this->getUnrecognisedFileScannerOption(), array(
			'enabled_report_only',
			'enabled_delete_report'
		) );
	}

	/**
	 * @return bool
	 */
	public function isWcfScanAutoRepair() {
		return $this->getOptIs( 'attempt_auto_file_repair', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isWcfScanEnabled() {
		return $this->getOptIs( 'enable_core_file_integrity_scan', 'Y' );
	}

	/**
	 * @param bool $bEnabled
	 * @return $this
	 */
	public function setWcfScanEnabled( $bEnabled ) {
		return $this->setOpt( 'enable_core_file_integrity_scan', $bEnabled ? 'Y' : 'N' );
	}

	/**
	 * @param bool $bEnabled
	 * @return $this
	 */
	public function setWcfScanAutoRepair( $bEnabled ) {
		return $this->setOpt( 'attempt_auto_file_repair', $bEnabled ? 'Y' : 'N' );
	}

	/**
	 * @param int $nId
	 * @return $this
	 */
	public function addWpvulnNotifiedId( $nId ) {
		if ( !$this->isWpvulnIdAlreadyNotified( $nId ) ) {
			$aIds = $this->getWpvulnNotifiedIds();
			$aIds[] = (int)$nId;
			$this->setOpt( 'wpvuln_notified_ids', $aIds );
		}
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isWpvulnEnabled() {
		return $this->isPremium() && !$this->getOptIs( 'enable_wpvuln_scan', 'disabled' );
	}

	/**
	 * @return array
	 */
	public function getWpvulnNotifiedIds() {
		$a = $this->getOpt( 'wpvuln_notified_ids', array() );
		return is_array( $a ) ? $a : array();
	}

	/**
	 * @param int $nId
	 * @return bool
	 */
	public function isWpvulnIdAlreadyNotified( $nId ) {
		return in_array( $nId, $this->getWpvulnNotifiedIds() );
	}

	/**
	 * @return bool
	 */
	public function isWpvulnSendEmail() {
		return $this->isWpvulnEnabled() && $this->getOptIs( 'enable_wpvuln_scan', 'enabled_email' );
	}

	/**
	 * @return bool
	 */
	public function isWpvulnAutoupdatesEnabled() {
		return $this->isWpvulnEnabled() && $this->getOptIs( 'wpvuln_scan_autoupdate', 'Y' );
	}

	/**
	 * @return mixed
	 */
	public function getWpvulnPluginsHighlightOption() {
		return $this->isWpvulnEnabled() ? $this->getOpt( 'wpvuln_scan_display' ) : 'disabled';
	}

	/**
	 * @return bool
	 */
	public function isWpvulnPluginsHighlightEnabled() {
		$sOpt = $this->getWpvulnPluginsHighlightOption();
		return ( $sOpt != 'disabled' ) && $this->loadWpUsers()->isUserAdmin()
			   && ( ( $sOpt != 'enabled_securityadmin' ) || $this->getConn()->getHasPermissionToManage() );
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_hack_protection_tools' :
				$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'The Hack Protection system is a set of tools to warn you and protect you against hacks on your site.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Hack Protection' ) ) )
				);
				$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_wpvuln_scan' :
				$sTitle = _wpsf__( 'Vulnerabilities Scanner' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Regularly scan your WordPress plugins and themes for known security vulnerabilities.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Plugin Vulnerabilities Scanner' ) ) ),
					_wpsf__( 'Ensure this is turned on and you will always know if any of your assets have known security vulnerabilities.' )
				);
				$sTitleShort = _wpsf__( 'Vulnerabilities Scanner' );
				break;

			case 'section_plugin_vulnerabilities_scan' :
				$sTitle = _wpsf__( 'Plugin Vulnerabilities Scanner' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Regularly scan your plugins against a database of known vulnerabilities.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Plugin Vulnerabilities Scanner' ) ) )
				);
				$sTitleShort = _wpsf__( 'Plugin Vulnerabilities' );
				break;

			case 'section_core_file_integrity_scan' :
				$sTitle = _wpsf__( 'Core File Integrity Scanner' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Regularly scan your WordPress core files for changes compared to official WordPress files.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Core File Integrity Scanner' ) ) )
				);
				$sTitleShort = _wpsf__( 'Core File Scanner' );
				break;

			case 'section_unrecognised_file_scan' :
				$sTitle = _wpsf__( 'Unrecognised Files Scanner' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( "Regularly scan your WordPress core folders for files that don't belong." ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Unrecognised Files Scanner' ) ) )
				);
				$sTitleShort = _wpsf__( 'Unrecognised Files Scanner' );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {
		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_hack_protect' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
				break;

			case 'enable_plugin_vulnerabilities_scan' :
				$sName = _wpsf__( 'Plugin Vulnerabilities Scanner' );
				$sSummary = sprintf( _wpsf__( 'Daily Cron - %s' ), _wpsf__( 'Scans Plugins For Known Vulnerabilities' ) );
				$sDescription = _wpsf__( 'Runs a scan of all your plugins against a database of known WordPress plugin vulnerabilities.' );
				break;

			case 'enable_wpvuln_scan' :
				$sName = _wpsf__( 'Vulnerability Scanner' );
				$sSummary = _wpsf__( 'Enable The Vulnerability Scanner' );
				$sDescription = _wpsf__( 'Runs a scan of all your plugins against a database of known WordPress vulnerabilities.' );
				break;

			case 'wpvuln_scan_autoupdate' :
				$sName = _wpsf__( 'Automatic Updates' );
				$sSummary = _wpsf__( 'Apply Updates Automatically To Vulnerable Plugins' );
				$sDescription = _wpsf__( 'When an update becomes available, automatically apply updates to items with known vulnerabilities.' );
				break;

			case 'wpvuln_scan_display' :
				$sName = _wpsf__( 'Highlight Plugins' );
				$sSummary = _wpsf__( 'Highlight Vulnerable Plugins Upon Display' );
				$sDescription = _wpsf__( 'Vulnerable plugins will be highlighted on the main plugins page.' );
				break;

			case 'enable_core_file_integrity_scan' :
				$sName = _wpsf__( 'Core File Scanner' );
				$sSummary = sprintf( _wpsf__( 'Daily Cron - %s' ), _wpsf__( 'Scans WordPress Core Files For Alterations' ) );
				$sDescription = _wpsf__( 'Compares all WordPress core files on your site against the official WordPress files.' )
								.'<br />'._wpsf__( 'WordPress Core files should never be altered for any reason.' );
				break;

			case 'attempt_auto_file_repair' :
				$sName = _wpsf__( 'Auto Repair' );
				$sSummary = _wpsf__( 'Automatically Repair WordPress Core Files That Have Been Altered' );
				$sDescription = _wpsf__( 'Attempts to automatically repair WordPress Core files with the official WordPress file data, for files that have been altered or are missing.' );
				break;

			case 'enable_unrecognised_file_cleaner_scan' :
				$sName = _wpsf__( 'Unrecognised Files Scanner' );
				$sSummary = _wpsf__( 'Daily Scan For Unrecognised Files In Core Directories' );
				$sDescription = _wpsf__( 'Scans for, and automatically deletes, any files in your core WordPress folders that are not part of your WordPress installation.' );
				break;

			case 'ufc_scan_uploads' :
				$sName = _wpsf__( 'Scan Uploads' );
				$sSummary = _wpsf__( 'Scan Uploads Folder For PHP and Javascript' );
				$sDescription = sprintf( _wpsf__( 'Warning - %s' ), _wpsf__( 'Take care when turning on this option - if you are unsure, leave it disabled.' ) )
								.'<br />'._wpsf__( 'The Uploads folder is primarily for media, but could be used to store nefarious files.' );
				break;

			case 'ufc_exclusions' :
				$sName = _wpsf__( 'File Exclusions' );
				$sSummary = _wpsf__( 'Provide A List Of Files To Be Excluded From The Scan' );
				$sDefaults = implode( ', ', $this->getOptionsVo()->getOptDefault( 'ufc_exclusions' ) );
				$sDescription = _wpsf__( 'Take a new line for each file you wish to exclude from the scan.' )
								.'<br/><strong>'._wpsf__( 'No commas are necessary.' ).'</strong>'
								.'<br/>'.sprintf( 'Default: %s', $sDefaults );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}