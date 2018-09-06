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
//		$this->cron_dailyChecksumScan();

		if ( $this->loadWpUsers()->isUserAdmin() ) {
			$oDp = $this->loadDP();

			switch ( $oDp->query( 'shield_action' ) ) {

				case 'repair_file':
					$sPath = '/'.trim( $oDp->query( 'repair_file_path' ) ); // "/" prevents esc_url() from prepending http.
					$sMd5FilePath = urldecode( esc_url( $sPath ) );
					if ( !empty( $sMd5FilePath ) ) {
						if ( $this->repairCoreFile( $sMd5FilePath ) ) {
							$this->getMod()->setFlashAdminNotice( _wpsf__( 'File was successfully replaced with an original from WordPress.org' ) );
						}
						else {
							$this->getMod()->setFlashAdminNotice( _wpsf__( 'File was not replaced' ), true );
						}
					}
			}
		}
	}

	protected function setupChecksumCron() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
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
		$oFO = $this->getMod();
		$this->loadWpCronProcessor()->deleteCronJob( $oFO->getWcfCronName() );
	}

	/**
	 * @param bool $bAutoRepair
	 * @return array
	 */
	public function doChecksumScan( $bAutoRepair = false ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$aChecksumData = $this->loadWp()->getCoreChecksums();

		if ( empty( $aChecksumData ) ) {
			return array();
		}

		$aDiscoveredFiles = array(
			'checksum_mismatch' => array(),
			'missing'           => array(),
		);

		$aAutoFixIndexFiles = $this->getMod()->getDef( 'corechecksum_autofix' );
		if ( empty( $aAutoFixIndexFiles ) ) {
			$aAutoFixIndexFiles = array();
		}

		$sFullExclusionsPattern = '#('.implode( '|', $this->getFullExclusions() ).')#i';
		$sMissingOnlyExclusionsPattern = '#('.implode( '|', $this->getMissingOnlyExclusions() ).')#i';

		$bProblemFound = false;
		$oFS = $this->loadFS();
		foreach ( $aChecksumData as $sMd5FilePath => $sWpOrgChecksum ) {
			if ( preg_match( $sFullExclusionsPattern, $sMd5FilePath ) ) {
				continue;
			}

			$bRepairThis = false;
			$sFullPath = $this->convertMd5FilePathToActual( $sMd5FilePath );

			if ( $oFS->isFile( $sFullPath ) ) {
				if ( $this->compareFileChecksums( $sWpOrgChecksum, $sFullPath ) ) {
					$bProblemFound = true;
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
				$bProblemFound = true;
				$aDiscoveredFiles[ 'missing' ][] = $sMd5FilePath;
				$bRepairThis = $bAutoRepair;
			}

			if ( $bRepairThis ) {
				$this->repairCoreFile( $sMd5FilePath );
			}
		}

		( $bProblemFound && !$bAutoRepair ) ? $oFO->setLastScanProblemAt( 'wcf' ) : $oFO->clearLastScanProblemAt( 'wcf' );
		$oFO->setLastScanAt( 'wcf' );

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
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$bOptionRepair = $oFO->isWcfScanAutoRepair() || ( $this->loadDP()->query( 'checksum_repair' ) == 1 );

		$aFiles = $this->doChecksumScan( $bOptionRepair );
		if ( !empty( $aFiles[ 'checksum_mismatch' ] ) || !empty( $aFiles[ 'missing' ] ) ) {
			$this->emailResults( $aFiles );
		}
	}

	/**
	 * @return array
	 */
	protected function getFullExclusions() {
		$aExclusions = $this->getMod()->getDef( 'corechecksum_exclusions' );
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
		$aExclusions = $this->getMod()->getDef( 'corechecksum_exclusions_missing_only' );
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
			$sRootUrl = $this->getMod()->getDef( 'url_wordress_core_svn_il8n' ).$sLocale;
		}
		else {
			$sRootUrl = $this->getMod()->getDef( 'url_wordress_core_svn' );
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
	 * @param array[][] $aFiles
	 */
	protected function emailResults( $aFiles ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$this->getEmailProcessor()
			 ->sendEmailWithWrap(
				 $sTo,
				 sprintf( '[%s] %s', _wpsf__( 'Warning' ), _wpsf__( 'Modified Core WordPress Files Discovered' ) ),
				 $this->buildEmailBodyFromFiles( $aFiles )
			 );

		$this->addToAuditEntry(
			sprintf( _wpsf__( 'Sent Checksum Scan Notification email alert to: %s' ), $sTo )
		);
	}

	/**
	 * @param array $aFiles
	 * @return array
	 */
	private function buildEmailBodyFromFiles( $aFiles ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$sName = $this->getController()->getHumanName();
		$sHomeUrl = $this->loadWp()->getHomeUrl();

		$aContent = array(
			sprintf( _wpsf__( "The %s Core File Scanner found files with potential problems." ), $sName ),
			sprintf( '%s: %s', _wpsf__( 'Site URL' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
			''
		);

		if ( $oFO->isWcfScanAutoRepair() || $oFO->isIncludeFileLists() || !$oFO->canRunWizards() ) {
			$aContent = $this->buildListOfFilesForEmail( $aFiles );
			$aContent[] = '';

			if ( $oFO->isWcfScanAutoRepair() ) {
				$aContent[] = '<strong>'.sprintf( _wpsf__( "%s has already attempted to repair the files." ), $sName ).'</strong>'
							  .' '._wpsf__( 'But, you should always check these files to ensure everything is as you expect.' );
			}
			else {
				$aContent[] = _wpsf__( 'You should review these files and replace them with official versions if required.' );
				$aContent[] = _wpsf__( 'Alternatively you can have the plugin attempt to repair/replace these files automatically.' )
							  .' [<a href="https://icwp.io/moreinfochecksum">'._wpsf__( 'More Info' ).']</a>';
			}
			$aContent[] = '';
		}

		if ( $oFO->canRunWizards() ) {
			$aContent[] = _wpsf__( 'We recommend you run the scanner to review your site' ).':';
			$aContent[] = sprintf( '<a href="%s" target="_blank" style="%s">%s →</a>',
				$oFO->getUrl_Wizard( 'wcf' ),
				'border:1px solid;padding:20px;line-height:19px;margin:10px 20px;display:inline-block;text-align:center;width:290px;font-size:18px;',
				_wpsf__( 'Run Scanner' )
			);
			$aContent[] = '';
		}

		if ( !$oFO->getConn()->isRelabelled() ) {
			$aContent[] = '[ <a href="https://icwp.io/moreinfochecksum">'._wpsf__( 'More Info On This Scanner' ).' ]</a>';
		}

		return $aContent;
	}

	/**
	 * @param array $aFiles
	 * @return array
	 */
	private function buildEmailBody_Legacy( $aFiles ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$sName = $this->getController()->getHumanName();

		$aContent = $this->buildListOfFilesForEmail( $aFiles );
		$aContent[] = '';

		$aContent[] = '';

		return $aContent;
	}

	/**
	 * @param array $aFiles
	 * @return array
	 */
	private function buildListOfFilesForEmail( $aFiles ) {
		$aContent = array();

		if ( !empty( $aFiles[ 'checksum_mismatch' ] ) ) {
			$aContent[] = _wpsf__( "The contents of the core files listed below don't match official WordPress files:" );
			foreach ( $aFiles[ 'checksum_mismatch' ] as $sFile ) {
				$aContent[] = ' - '.$sFile.$this->getFileRepairLink( $sFile );
			}
		}
		if ( !empty( $aFiles[ 'missing' ] ) ) {
			$aContent[] = _wpsf__( 'The WordPress Core Files listed below are missing:' );
			foreach ( $aFiles[ 'missing' ] as $sFile ) {
				$aContent[] = ' - '.$sFile.$this->getFileRepairLink( $sFile );
			}
		}
		return $aContent;
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
			$this->getMod()->getDef( 'url_wordress_core_svn' )
			.'tags/'.$this->loadWp()->getVersion().'/'.$sFile,
			_wpsf__( 'WordPress.org source file' )
		);
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		$oFO = $this->getMod();
		return $oFO->prefixOptionKey( $oFO->getDef( 'corechecksum_cron_name' ) );
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