<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_FileCleanerScan', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_HackProtect_FileCleanerScan extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var string[]
	 */
	protected $aCoreFiles;

	/**
	 */
	public function run() {
		$this->setupChecksumCron();

		if ( $this->loadWpUsers()->isUserAdmin() ) {
			$oReq = $this->loadRequest();

			switch ( $oReq->query( 'shield_action' ) ) {
				case 'delete_unrecognised_file':
					$sPath = '/'.$oReq->query( 'repair_file_path' ); // "/" prevents esc_url() from prepending http.
					break;
			}
		}
	}

	protected function setupChecksumCron() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$this->loadWpCronProcessor()
			 ->setRecurrence( $this->prefix( sprintf( 'per-day-%s', $oFO->getScanFrequency() ) ) )
			 ->createCronJob(
				 $oFO->getUfcCronName(),
				 array( $this, 'cron_dailyFileCleanerScan' )
			 );
		add_action( $oFO->prefix( 'delete_plugin' ), array( $this, 'deleteCron' ) );
	}

	/**
	 */
	public function deleteCron() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$this->loadWpCronProcessor()->deleteCronJob( $oFO->getUfcCronName() );
	}

	/**
	 * @return array
	 */
	protected function scanCore() {
		$aOutOfPlaceFiles = array();
		// Check that we can get the core files since if it's empty, we'd delete everything
		if ( count( $this->getCoreFiles() ) > 1000 ) { // 1000 as a basic sanity check
			foreach ( array( 'wp-admin', 'wp-includes' ) as $sMainFolder ) {
				$aOutOfPlaceFiles = array_merge( $aOutOfPlaceFiles, $this->scanCoreDir( path_join( ABSPATH, $sMainFolder ) ) );
			}
		}
		return $aOutOfPlaceFiles;
	}

	/**
	 * @return array
	 */
	protected function scanUploads() {
		$aOddFiles = array();

		$sUploadsDir = $this->loadWp()->getDirUploads();
		if ( !empty( $sUploadsDir ) ) {
			$oFilter = new CleanerRecursiveFilterIterator( new RecursiveDirectoryIterator( $sUploadsDir ) );
			$oRecursiveIterator = new RecursiveIteratorIterator( $oFilter );

			$sBadExtensionsReg = '#^'.implode( '|', array( 'js', 'php', 'php5' ) ).'$#i';
			foreach ( $oRecursiveIterator as $oFsItem ) {
				/** @var SplFileInfo $oFsItem */

				if ( !$oFsItem->isDir() && !$this->isExcluded( $oFsItem ) ) {
					if ( preg_match( $sBadExtensionsReg, $oFsItem->getExtension() ) ) {
						$aOddFiles[] = $oFsItem->getPathname();
					}
				}
			}
		}
		return $aOddFiles;
	}

	/**
	 * @param string[] $aFilesToDelete
	 */
	protected function deleteFiles( $aFilesToDelete ) {
		foreach ( $aFilesToDelete as $sFilePath ) {
			$this->loadFS()->deleteFile( $sFilePath );
		}

		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$oFO->clearLastScanProblemAt( 'ufc' );
	}

	/**
	 * @param string $sRootDir
	 * @return string[]
	 */
	protected function scanCoreDir( $sRootDir ) {
		$aOddFiles = array();

		$oFilter = new CleanerRecursiveFilterIterator( new RecursiveDirectoryIterator( $sRootDir ) );
		$oRecursiveIterator = new RecursiveIteratorIterator( $oFilter );
		foreach ( $oRecursiveIterator as $oFsItem ) {
			/** @var SplFileInfo $oFsItem */

			if ( $oFsItem->isFile() && !$this->isExcluded( $oFsItem ) ) {
				if ( !$this->isCoreFile( $oFsItem ) ) {
					$aOddFiles[] = $oFsItem->getPathname();
				}
			}
		}

		return $aOddFiles;
	}

	/**
	 * @param SplFileInfo $oFsItem
	 * @return bool
	 */
	protected function isCoreFile( $oFsItem ) {
		// We rtrim the '/' to prevent mixup with windows and deal only in SYSTEM-generated paths first.
		$sPathNoAbs = ltrim( str_replace( rtrim( ABSPATH, '/' ), '', $oFsItem->getPathname() ), "\/" );
		return in_array( $this->loadFS()->normalizeFilePathDS( $sPathNoAbs ), $this->getCoreFiles() );
	}

	/**
	 * @param SplFileInfo $oFile
	 * @return bool
	 */
	protected function isExcluded( $oFile ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$oFS = $this->loadFS();

		$sFileName = $oFile->getFilename();
		$sFilePath = $oFS->normalizeFilePathDS( $oFile->getPathname() );

		$bExcluded = false;

		foreach ( $oFO->getUfcFileExclusions() as $sExclusion ) {
			$sExclusion = $oFS->normalizeFilePathDS( $sExclusion );

			if ( preg_match( '/^#(.+)#$/', $sExclusion, $aMatches ) ) { // it's regex
				$bExcluded = @preg_match( stripslashes( $sExclusion ), $sFilePath );
			}
			else if ( strpos( $sExclusion, '/' ) === false ) { // filename only
				$bExcluded = ( $sFileName == $sExclusion );
			}
			else {
				$bExcluded = strpos( $sFilePath, $sExclusion );
			}

			if ( $bExcluded ) {
				break;
			}
		}
		return $bExcluded;
	}

	public function cron_dailyFileCleanerScan() {
		if ( doing_action( 'wp_maybe_auto_update' ) || did_action( 'wp_maybe_auto_update' ) ) {
			return;
		}
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isUfcEnabled() ) {
			try {
				$this->runScan(); // The file scanning part can exception with permission & exists
			}
			catch ( Exception $oE ) {
				// TODO
			}
		}
	}

	/**
	 */
	public function runScan() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$aDiscoveredFiles = $this->discoverFiles();
		if ( !empty( $aDiscoveredFiles ) ) {
			if ( $oFO->isUfcDeleteFiles() ) {
				$this->deleteFiles( $aDiscoveredFiles );
			}
			if ( $oFO->isUfsSendReport() ) {
				$this->emailResults( $aDiscoveredFiles );
			}
		}
	}

	/**
	 * @return array
	 */
	public function discoverFiles() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$aDiscovered = $this->scanCore();
		if ( $oFO->isUfsScanUploads() ) {
			$aDiscovered = array_merge( $aDiscovered, $this->scanUploads() );
		}

		empty( $aDiscovered ) ? $oFO->clearLastScanProblemAt( 'ufc' ) : $oFO->setLastScanProblemAt( 'ufc' );
		$oFO->setLastScanAt( 'ufc' );

		return $aDiscovered;
	}

	/**
	 * @param array $aFiles
	 */
	protected function emailResults( $aFiles ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$this->getEmailProcessor()
			 ->sendEmailWithWrap(
				 $sTo,
				 sprintf( '[%s] %s', _wpsf__( 'Warning' ), _wpsf__( 'Unrecognised WordPress Files Detected' ) ),
				 $this->buildEmailBodyFromFiles( $aFiles )
			 );

		$this->addToAuditEntry(
			sprintf( _wpsf__( 'Sent Unrecognised File Scan Notification email alert to: %s' ), $sTo )
		);
	}

	/**
	 * The newer approach is to only enumerate files if they were deleted
	 * @param string[] $aFiles
	 * @return string[]
	 */
	private function buildEmailBodyFromFiles( $aFiles ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$sName = $this->getController()->getHumanName();
		$sHomeUrl = $this->loadWp()->getHomeUrl();

		$aContent = array(
			sprintf( _wpsf__( 'The %s Unrecognised File Scanner found files which you need to review.' ), $sName ),
			sprintf( '%s: %s', _wpsf__( 'Site URL' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
			''
		);

		if ( $oFO->isUfcDeleteFiles() || $oFO->isIncludeFileLists() || !$oFO->canRunWizards() ) {
			$aContent[] = _wpsf__( 'Files that were discovered' ).':';
			foreach ( $aFiles as $sFile ) {
				$aContent[] = ' - '.$sFile;
			}
			$aContent[] = '';

			if ( $oFO->isUfcDeleteFiles() ) {
				$aContent[] = sprintf( _wpsf__( '%s has attempted to delete these files based on your current settings.' ), $sName );
				$aContent[] = '';
			}
		}

		if ( $oFO->canRunWizards() ) {
			$aContent[] = _wpsf__( 'We recommend you run the scanner to review your site' ).':';
			$aContent[] = sprintf( '<a href="%s" target="_blank" style="%s">%s →</a>',
				$oFO->getUrl_Wizard( 'ufc' ),
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
	 * @return string[]
	 */
	protected function getCoreFiles() {
		if ( empty( $this->aCoreFiles ) ) {
			$this->aCoreFiles = array_keys( $this->loadWp()->getCoreChecksums() );
		}
		return $this->aCoreFiles;
	}

	/**
	 * TODO
	 * @param string $sFile
	 * @return string
	 */
	protected function getFileDeleteLink( $sFile ) {
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
}

class CleanerRecursiveFilterIterator extends RecursiveFilterIterator {

	public function accept() {
		/** @var SplFileInfo $oCurrent */
		$oCurrent = $this->current();

		$bRecurse = true; // I.e. consume the file.

		if ( in_array( $oCurrent->getFilename(), array( '.', '..' ) ) ) {
			$bRecurse = false;
		}

		return $bRecurse;
	}
}