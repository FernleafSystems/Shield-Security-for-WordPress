<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_FileCleanerScan', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

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
			$oDp = $this->loadDP();

			if ( $oDp->query( 'force_filecleanscan' ) == 1 ) {
				$this->runScan();
			}
			else {
				$sAction = $oDp->query( 'shield_action' );
				switch ( $sAction ) {
					case 'delete_unrecognised_file':
						$sPath = '/'.trim( $oDp->FetchGet( 'repair_file_path' ) ); // "/" prevents esc_url() from prepending http.
				}
			}
		}
	}

	protected function setupChecksumCron() {
		$this->loadWpCronProcessor()
			 ->setRecurrence( 'daily' )
			 ->createCronJob(
				 $this->getCronName(),
				 array( $this, 'cron_dailyFileCleanerScan' )
			 );
		add_action( $this->getFeature()->prefix( 'delete_plugin' ), array( $this, 'deleteCron' ) );
	}

	/**
	 */
	public function deleteCron() {
		$this->loadWpCronProcessor()->deleteCronJob( $this->getCronName() );
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
		$oFO = $this->getFeature();
		$oFS = $this->loadFS();

		$sFileName = $oFile->getFilename();
		$sFilePath = $oFS->normalizeFilePathDS( $oFile->getPathname() );

		$bExcluded = false;

		foreach ( $oFO->getUfcFileExclusions() as $sExclusion ) {
			$sExclusion = $oFS->normalizeFilePathDS( $sExclusion );

			if ( preg_match( '/^#(.+)#$/', $sExclusion, $aMatches ) ) { // it's regex
				$bExcluded = @preg_match( $sExclusion, $sFilePath );
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
		$oFO = $this->getFeature();
		if ( $oFO->isUfsEnabled() ) {
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
		$oFO = $this->getFeature();
		$aDiscoveredFiles = $this->discoverFiles();
		if ( !empty( $aDiscoveredFiles ) ) {
			if ( $oFO->isUfsDeleteFiles() ) {
				$this->deleteFiles( $aDiscoveredFiles );
			}
			if ( $oFO->isUfsSendReport() ) {
				$this->sendEmailNotification( $aDiscoveredFiles );
			}
		}
	}

	/**
	 * @return array
	 */
	public function discoverFiles() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();

		$aDiscoveredFiles = $this->scanCore();
		if ( $oFO->isUfsScanUploads() ) {
			$aDiscoveredFiles = array_merge( $aDiscoveredFiles, $this->scanUploads() );
		}
		return $aDiscoveredFiles;
	}

	/**
	 * @param array $aFiles
	 * @return bool
	 */
	protected function sendEmailNotification( $aFiles ) {
		if ( empty( $aFiles ) ) {
			return true;
		}
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		$sHomeUrl = $this->loadWp()->getHomeUrl();
		$aContent = array(
			sprintf( _wpsf__( '%s detected files on your site which are not recognised.' ), $this->getController()
																								 ->getHumanName() ),
			_wpsf__( 'This is part of the Hack Protection module for the WordPress Unrecognised File Scanner.' )
			.' [<a href="http://icwp.io/shieldmoreinfounrecognised">'._wpsf__( 'More Info' ).']</a>',
			'',
			sprintf( _wpsf__( 'Site Home URL - %s' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
			_wpsf__( 'The following files are considered "unrecognised" and should be examined:' ),
			''
		);

		foreach ( $aFiles as $sFile ) {
			$aContent[] = ' - '.$sFile;
		}

		$aContent[] = '';
		$aContent[] = sprintf( '<a href="%s" target="_blank" style="%s">%s →</a>',
			$oFO->getUrl_Wizard( 'ufc' ),
			'border:1px solid;padding:20px;line-height:19px;margin:10px 20px;display:inline-block;text-align:center;width:290px;font-size:18px;',
			_wpsf__( 'Run the scanner manually' )
		);
		$aContent[] = '';

		if ( $oFO->isUfsDeleteFiles() ) {
			$aContent[] = _wpsf__( 'We have already attempted to delete these files based on your current settings.' )
						  .' '._wpsf__( 'But, you should always check these files to ensure everything is as you expect.' );
		}
		else {
			$aContent[] = _wpsf__( 'You should review these files and remove them if required.' );
			$aContent[] = _wpsf__( 'You can now add these file names to your exclusion list to no longer be warned about them.' );
			$aContent[] = _wpsf__( 'Alternatively you can have the plugin attempt to delete these files automatically.' )
						  .' [<a href="http://icwp.io/shieldmoreinfounrecognised">'._wpsf__( 'More Info' ).']</a>';
		}

		$sRecipient = $this->getPluginDefaultRecipientAddress();
		$sEmailSubject = sprintf( _wpsf__( 'Warning - %s' ), _wpsf__( 'Unrecognised WordPress Files(s) Detected.' ) );
		$bSendSuccess = $this->getEmailProcessor()->sendEmailTo( $sRecipient, $sEmailSubject, $aContent );

		if ( $bSendSuccess ) {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Successfully sent Unrecognised File Scan notification email alert to: %s' ), $sRecipient ) );
		}
		else {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Failed to send Unrecognised File Scan notification email alert to: %s' ), $sRecipient ) );
		}
		return $bSendSuccess;
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
		return $oFO->prefixOptionKey( $oFO->getDefinition( 'unrecognisedscan_cron_name' ) );
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