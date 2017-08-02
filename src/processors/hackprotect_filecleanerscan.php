<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_FileCleanerScan', false ) ) {
	return;
}

require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'base_wpsf.php' );

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
			$oDp = $this->loadDataProcessor();

			if ( $oDp->FetchGet( 'force_filecleanscan' ) == 1 ) {
				$this->cron_dailyFileCleanerScan();
			}
			else {
				$sAction = $oDp->FetchGet( 'shield_action' );
				switch ( $sAction ) {

					case 'delete_unrecognised_file':
						$sPath = '/' . trim( $oDp->FetchGet( 'repair_file_path' ) ); // "/" prevents esc_url() from prepending http.
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
	 * @param bool $bAutoDelete
	 * @return array
	 */
	public function doFileCleanerScan( $bAutoDelete ) {
		$aOutOfPlaceFiles = array();

		// First look for anything out of place in the Core folders.
		foreach ( array( 'wp-admin', 'wp-includes' ) as $sMainFolder ) {
			$aOutOfPlaceFiles = array_merge( $aOutOfPlaceFiles, $this->scanCoreDir( path_join( ABSPATH, $sMainFolder ) ) );
		}

		if ( $bAutoDelete ) {
			foreach ( $aOutOfPlaceFiles as $sFilePath ) {
				$this->loadFS()->deleteFile( $sFilePath );
			}
		}

		return $aOutOfPlaceFiles;
	}

	/**
	 * @param string $sRootDir
	 * @return array
	 */
	protected function scanCoreDir( $sRootDir ) {
		$aOddFiles = array();

		$oFilter = new CleanerRecursiveFilterIterator( new RecursiveDirectoryIterator( $sRootDir ) );
		$oRecursiveIterator = new RecursiveIteratorIterator( $oFilter );
		foreach ( $oRecursiveIterator as $oFsItem ) { /** @var SplFileInfo $oFsItem */

			if ( $oFsItem->isFile() && !$this->isExcluded( $oFsItem ) ) {
				if ( !$this->isCoreFile( $oFsItem ) ) {
					$aOddFiles[] = $oFsItem->getPathname();
				}
			}
		}

		return $aOddFiles;
	}

	/**
	 * @return array
	 */
	protected function scanUploadsDir() {
		$aOddFiles = array();

		$sUploadsDir = path_join( WP_CONTENT_DIR, 'uploads' );
		$oFilter = new CleanerRecursiveFilterIterator( new RecursiveDirectoryIterator( $sUploadsDir ) );
		$oRecursiveIterator = new RecursiveIteratorIterator( $oFilter );

		$sBadExtensionsReg = '#^' . implode( '|', array( 'js', 'php', 'php5' ) ) . '$#i';
		foreach ( $oRecursiveIterator as $oFsItem ) {
			/** @var SplFileInfo $oFsItem */

			if ( !$oFsItem->isDir() && !$this->isExcluded( $oFsItem ) ) {

				if ( preg_match( $sBadExtensionsReg, $oFsItem->getExtension() ) ) {
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
		return in_array( str_replace( ABSPATH, '', $oFsItem->getPathname() ), $this->getCoreFiles() );
	}

	/**
	 * @param SplFileInfo $oFile
	 * @return bool
	 */
	protected function isExcluded( $oFile ) {
		return in_array( $oFile->getFilename(), $this->getFeature()->getDefinition( 'exclusions_core_file_cleaner' ) );
	}

	public function cron_dailyFileCleanerScan() {
		if ( doing_action( 'wp_maybe_auto_update' ) || did_action( 'wp_maybe_auto_update' ) ) {
			return;
		}

		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		$aDiscoveredFiles = $this->doFileCleanerScan( $oFO->isUnrecognisedFileScannerDeleteFiles() );
		if ( $oFO->isUnrecognisedFileScannerSendReport() && !empty( $aDiscoveredFiles ) ) {
			$this->sendEmailNotification( $aDiscoveredFiles );
		}
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

		$oWp = $this->loadWpFunctions();
		$sHomeUrl = $oWp->getHomeUrl();
		$aContent = array(
			sprintf( _wpsf__( '%s has detected files on your site which are not welcome.' ), $this->getController()
																								  ->getHumanName() ),
			_wpsf__( 'This is part of the Hack Protection module for the WordPress Unrecognised File Scanner.' )
			. ' [<a href="http://icwp.io/shieldmoreinfounrecognised">' . _wpsf__( 'More Info' ) . ']</a>',
			sprintf( _wpsf__( 'Site Home URL - %s' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
			'',
			_wpsf__( 'Details for the problem files are below:' ),
		);

		if ( !empty( $aFiles ) ) {
			$aContent[] = '';
			$aContent[] = _wpsf__( 'The following files do not match the official WordPress.org Core Files:' );
			foreach ( $aFiles as $sFile ) {
				$aContent[] = ' - ' . $sFile;
			}
		}

		$aContent[] = '';
		if ( $oFO->isUnrecognisedFileScannerDeleteFiles() ) {
			$aContent[] = _wpsf__( 'We have already attempted to delete these files based on your current settings.' )
				. ' ' . _wpsf__( 'But, you should always check these files to ensure everything is as you expect.' );
		}
		else {
			$aContent[] = _wpsf__( 'You should review these files and replace them with official versions if required.' );
			$aContent[] = _wpsf__( 'Alternatively you can have the plugin attempt to delete these files automatically.' )
				. ' [<a href="http://icwp.io/shieldmoreinfounrecognised">' . _wpsf__( 'More Info' ) . ']</a>';
		}

		$sRecipient = $this->getPluginDefaultRecipientAddress();
		$sEmailSubject = sprintf( _wpsf__( 'Warning - %s' ), _wpsf__( 'Unwanted WordPress Files(s) Detected.' ) );
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
			$this->aCoreFiles = array_keys( $this->loadWpFunctions()->getCoreChecksums() );
		}
		return $this->aCoreFiles;
	}

	/**
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
				$this->loadWpFunctions()->getUrl_WpAdmin()
			),
			_wpsf__( 'Repair file now' ),
			$this->getFeature()->getDefinition( 'url_wordress_core_svn' ) . 'tags/' . $this->loadWpFunctions()
																						   ->getWordpressVersion() . '/' . $sFile,
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
}

class CleanerRecursiveFilterIterator extends \RecursiveFilterIterator {

	public function accept() {
		/** @var SplFileInfo $oCurrent */
		$oCurrent = $this->current();
		$sFileName = $oCurrent->getFilename();

		$bRecurse = true; // I.e. consume the file.

		if ( in_array( $sFileName, array( '.', '..' ) ) ) {
			$bRecurse = false;
		}

		return $bRecurse;
	}
}