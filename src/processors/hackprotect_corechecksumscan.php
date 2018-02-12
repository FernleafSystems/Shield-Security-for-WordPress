<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_CoreChecksumScan', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_HackProtect_CoreChecksumScan extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		$this->setupChecksumCron();

		if ( $this->loadWpUsers()->isUserAdmin() ) {
			$oDp = $this->loadDataProcessor();

			if ( $oDp->FetchGet( 'force_checksumscan' ) == 1 ) {
				$this->cron_dailyChecksumScan();
			}
			else {
				$sAction = $oDp->FetchGet( 'shield_action' );
				switch ( $sAction ) {

					case 'repair_file':
						$sPath = '/'.trim( $oDp->FetchGet( 'repair_file_path' ) ); // "/" prevents esc_url() from prepending http.
						$sMd5FilePath = urldecode( esc_url( $sPath ) );
						if ( !empty( $sMd5FilePath ) ) {
							if ( $this->repairCoreFile( $sMd5FilePath ) ) {
								$this->loadAdminNoticesProcessor()
									 ->addFlashMessage(
										 _wpsf__( 'File was successfully replaced with an original from WordPress.org' )
									 );
							}
							else {
								$this->loadAdminNoticesProcessor()
									 ->addFlashMessage(
										 _wpsf__( 'File was not replaced' )
									 );
							}
						}
				}
			}
		}
	}

	protected function setupChecksumCron() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		$this->loadWpCronProcessor()
			 ->setRecurrence( $this->prefix( sprintf( 'per-day-%s', $oFO->getScanFrequency() ) ) )
			 ->createCronJob(
				 $oFO->getWcfCronName(),
				 array( $this, 'cron_dailyChecksumScan' )
			 );
		add_action( $oFO->prefix( 'delete_plugin' ), array( $this, 'deleteCron' ) );
	}

	/**
	 */
	public function deleteCron() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		$this->loadWpCronProcessor()->deleteCronJob( $oFO->getWcfCronName() );
	}

	/**
	 * @param bool $bAutoRepair
	 * @return array
	 */
	public function doChecksumScan( $bAutoRepair ) {
		$aChecksumData = $this->loadWp()->getCoreChecksums();

		if ( empty( $aChecksumData ) ) {
			return array();
		}

		$aDiscoveredFiles = array(
			'checksum_mismatch' => array(),
			'missing'           => array(),
		);

		$aAutoFixIndexFiles = $this->getFeature()->getDefinition( 'corechecksum_autofix' );
		if ( empty( $aAutoFixIndexFiles ) ) {
			$aAutoFixIndexFiles = array();
		}

		$sFullExclusionsPattern = '#('.implode( '|', $this->getFullExclusions() ).')#i';
		$sMissingOnlyExclusionsPattern = '#('.implode( '|', $this->getMissingOnlyExclusions() ).')#i';

		$oFS = $this->loadFS();
		foreach ( $aChecksumData as $sMd5FilePath => $sWpOrgChecksum ) {
			if ( preg_match( $sFullExclusionsPattern, $sMd5FilePath ) ) {
				continue;
			}

			$bRepairThis = false;
			$sFullPath = $this->convertMd5FilePathToActual( $sMd5FilePath );

			if ( $oFS->isFile( $sFullPath ) ) {
				if ( $this->compareFileChecksums( $sWpOrgChecksum, $sFullPath ) ) {
					if ( in_array( $sMd5FilePath, $aAutoFixIndexFiles ) ) {
						$bRepairThis = true;
					}
					else {
						$aDiscoveredFiles[ 'checksum_mismatch' ][] = $sMd5FilePath;
						$bRepairThis = $bAutoRepair;
					}
				}
			}
			else if ( !preg_match( $sMissingOnlyExclusionsPattern, $sMd5FilePath ) ) {
				// If the file is missing and it's not in the missing-only exclusions
				$aDiscoveredFiles[ 'missing' ][] = $sMd5FilePath;
				$bRepairThis = $bAutoRepair;
			}

			if ( $bRepairThis ) {
				$this->repairCoreFile( $sMd5FilePath );
			}
		}

		return $aDiscoveredFiles;
	}

	/**
	 * @param string $sWpOrgChecksum
	 * @param string $sFullPath
	 * @return bool true if a difference is found, false otherwise
	 */
	protected function compareFileChecksums( $sWpOrgChecksum, $sFullPath ) {

		$bDifferenceFound = $sWpOrgChecksum != md5_file( $sFullPath );
		if ( $bDifferenceFound && strpos( $sFullPath, '.php' ) > 0 ) {
			$sUnixConversion = str_replace( array( "\r\n", "\r" ), "\n", file_get_contents( $sFullPath ) );
			$bDifferenceFound = $sWpOrgChecksum != md5( $sUnixConversion );
		}
		return $bDifferenceFound;
	}

	public function cron_dailyChecksumScan() {

		if ( doing_action( 'wp_maybe_auto_update' ) || did_action( 'wp_maybe_auto_update' ) ) {
			return;
		}

		$bOptionRepair = $this->getIsOption( 'attempt_auto_file_repair', 'Y' )
						 || ( $this->loadDP()->query( 'checksum_repair' ) == 1 );

		$aDiscoveredFiles = $this->doChecksumScan( $bOptionRepair );
		if ( !empty( $aDiscoveredFiles[ 'checksum_mismatch' ] ) || !empty( $aDiscoveredFiles[ 'missing' ] ) ) {
			$this->sendChecksumErrorNotification( $aDiscoveredFiles );
		}
	}

	/**
	 * @return array
	 */
	protected function getFullExclusions() {
		$aExclusions = $this->getFeature()->getDefinition( 'corechecksum_exclusions' );
		if ( empty( $aExclusions ) || !is_array( $aExclusions ) ) {
			$aExclusions = array();
		}
		foreach ( $aExclusions as $nKey => $sExclusion ) {
			$aExclusions[ $nKey ] = preg_quote( $sExclusion, '#' );
		}

		// Flywheel specific mods
		if ( defined( 'FLYWHEEL_PLUGIN_DIR' ) ) {
			$aExclusions[] = 'wp-settings.php';
			$aExclusions[] = 'wp-admin/includes/upgrade.php';
		}

		return $aExclusions;
	}

	/**
	 * @return array
	 */
	protected function getMissingOnlyExclusions() {
		$aExclusions = $this->getFeature()->getDefinition( 'corechecksum_exclusions_missing_only' );
		if ( empty( $aExclusions ) || !is_array( $aExclusions ) ) {
			$aExclusions = array();
		}
		foreach ( $aExclusions as $nKey => $sExclusion ) {
			$aExclusions[ $nKey ] = preg_quote( $sExclusion, '#' );
		}
		return $aExclusions;
	}

	/**
	 * @param string $sPath
	 * @param bool   $bUseLocale
	 * @return string
	 */
	protected function retrieveCoreFileContent( $sPath, $bUseLocale = true ) {
		$sLocale = $this->loadWp()->getLocale( true );
		$bUseInternational = $bUseLocale && ( $sLocale != 'en_US' );
		if ( $bUseInternational ) {
			$sRootUrl = $this->getFeature()->getDefinition( 'url_wordress_core_svn_il8n' ).$sLocale;
		}
		else {
			$sRootUrl = $this->getFeature()->getDefinition( 'url_wordress_core_svn' );
		}
		$sFileUrl = sprintf(
			'%s/tags/%s/%s',
			$sRootUrl,
			$this->loadWp()->getVersion(),
			( $bUseInternational ? 'dist/' : '' ).$sPath
		);

		$sContent = (string)$this->loadFS()->getUrlContent( $sFileUrl );
		if ( $bUseInternational && empty( $sContent ) ) {
			$sContent = $this->retrieveCoreFileContent( $sPath, false );
		} // we'll try international retrieval and if it fails, we resort to en_US.
		return $sContent;
	}

	/**
	 * @param string $sMd5FilePath
	 * @return bool
	 */
	protected function repairCoreFile( $sMd5FilePath ) {
		$this->doStatIncrement( 'file.corechecksum.replaced' );

		$sMd5FilePath = ltrim( $sMd5FilePath, '/' ); // ltrim() ensures we haven't received an absolute path. e.g. replace file
		$sOfficialContent = $this->retrieveCoreFileContent( $sMd5FilePath );
		if ( !empty( $sOfficialContent ) ) {
			return $this->loadFS()
						->putFileContent( $this->convertMd5FilePathToActual( $sMd5FilePath ), $sOfficialContent );
		}
		return false;
	}

	/**
	 * @param array $aFiles
	 * @return bool
	 */
	protected function sendChecksumErrorNotification( $aFiles ) {
		if ( empty( $aFiles ) && empty( $aFiles[ 'missing' ] ) && empty( $aFiles[ 'checksum_mismatch' ] ) ) {
			return true;
		}

		$sHomeUrl = $this->loadWp()->getHomeUrl();
		$aContent = array(
			sprintf( _wpsf__( '%s has detected files on your site with potential problems.' ), $this->getController()
																									->getHumanName() ),
			_wpsf__( 'This is sent from the WordPress Core File Scanner, part of the Hack Guard module.' )
			.' [<a href="http://icwp.io/moreinfochecksum">'._wpsf__( 'More Info' ).']</a>',
			sprintf( _wpsf__( 'Site Home URL - %s' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
			'',
			_wpsf__( 'Details for the problem files are below:' ),
		);

		if ( !empty( $aFiles[ 'checksum_mismatch' ] ) ) {
			$aContent[] = '';
			$aContent[] = _wpsf__( 'The MD5 Checksum Hashes for following core files do not match the official WordPress.org Checksum Hashes:' );
			foreach ( $aFiles[ 'checksum_mismatch' ] as $sFile ) {
				$aContent[] = ' - '.$sFile.$this->getFileRepairLink( $sFile );
			}
		}
		if ( !empty( $aFiles[ 'missing' ] ) ) {
			$aContent[] = '';
			$aContent[] = _wpsf__( 'The following official WordPress core files are missing from your site:' );
			foreach ( $aFiles[ 'missing' ] as $sFile ) {
				$aContent[] = ' - '.$sFile.$this->getFileRepairLink( $sFile );
			}
		}

		$aContent[] = '';
		if ( $this->getIsOption( 'attempt_auto_file_repair', 'Y' ) ) {
			$aContent[] = _wpsf__( 'We have already attempted to repair these files based on your current settings.' )
						  .' '._wpsf__( 'But, you should always check these files to ensure everything is as you expect.' );
		}
		else {
			$aContent[] = _wpsf__( 'You should review these files and replace them with official versions if required.' );
			$aContent[] = _wpsf__( 'Alternatively you can have the plugin attempt to repair/replace these files automatically.' )
						  .' [<a href="http://icwp.io/moreinfochecksum">'._wpsf__( 'More Info' ).']</a>';
		}

		$aContent[] = '';

		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->canRunWizards() ) {
			$aContent[] = sprintf( '<a href="%s" target="_blank" style="%s">%s →</a>',
				$oFO->getUrl_Wizard( 'wcf' ),
				'border:1px solid;padding:20px;line-height:19px;margin:10px 20px;display:inline-block;text-align:center;width:290px;font-size:18px;',
				_wpsf__( 'Run the scanner manually' )
			);
			$aContent[] = '';
		}

		$sRecipient = $this->getPluginDefaultRecipientAddress();
		$sEmailSubject = sprintf( _wpsf__( 'Warning - %s' ), _wpsf__( 'Core WordPress Files(s) Discovered That May Have Been Modified.' ) );
		$bSendSuccess = $this->getEmailProcessor()->sendEmailTo( $sRecipient, $sEmailSubject, $aContent );

		if ( $bSendSuccess ) {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Successfully sent Checksum Scan Notification email alert to: %s' ), $sRecipient ) );
		}
		else {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Failed to send Checksum Scan Notification email alert to: %s' ), $sRecipient ) );
		}
		return $bSendSuccess;
	}

	/**
	 * @param string $sFile
	 * @return string
	 */
	protected function getFileRepairLink( $sFile ) {
		return sprintf( ' ( <a href="%s">%s</a> / <a href="%s">%s</a> )',
			add_query_arg(
				array(
					'shield_action'    => 'repair_file',
					'repair_file_path' => urlencode( $sFile )
				),
				$this->loadWp()->getUrl_WpAdmin()
			),
			_wpsf__( 'Repair file now' ),
			$this->getFeature()->getDefinition( 'url_wordress_core_svn' ).'tags/'.$this->loadWp()
																					   ->getVersion().'/'.$sFile,
			_wpsf__( 'WordPress.org source file' )
		);
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		$oFO = $this->getFeature();
		return $oFO->prefixOptionKey( $oFO->getDefinition( 'corechecksum_cron_name' ) );
	}

	private function convertMd5FilePathToActual( $sMd5FilePath ) {
		if ( strpos( $sMd5FilePath, 'wp-content/' ) === 0 ) {
			$sFullPath = path_join( WP_CONTENT_DIR, str_replace( 'wp-content/', '', $sMd5FilePath ) );
		}
		else {
			$sFullPath = ABSPATH.$sMd5FilePath;
		}
		return $sFullPath;
	}
}