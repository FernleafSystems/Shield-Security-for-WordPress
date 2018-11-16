<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Wcf', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/hackprotect_scan_base.php' );

use \FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ICWP_WPSF_Processor_HackProtect_Wcf extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'wcf';

	/**
	 */
	public function run() {
		parent::run();

		if ( $this->loadWpUsers()->isUserAdmin() ) {
			$oReq = $this->loadRequest();

			switch ( $oReq->query( 'shield_action' ) ) {

				case 'repair_file':
					$sPath = '/'.trim( $oReq->query( 'repair_file_path' ) ); // "/" prevents esc_url() from prepending http.
					$sMd5FilePath = urldecode( esc_url( $sPath ) );
					if ( !empty( $sMd5FilePath ) ) {
						if ( $this->repairCoreFile( $sMd5FilePath ) ) {
							$this->getMod()
								 ->setFlashAdminNotice( _wpsf__( 'File was successfully replaced with an original from WordPress.org' ) );
						}
						else {
							$this->getMod()->setFlashAdminNotice( _wpsf__( 'File was not replaced' ), true );
						}
					}
			}
		}
	}

	/**
	 * @param Scans\WpCore\ResultsSet $oResults
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[]
	 */
	protected function convertResultsToVos( $oResults ) {
		return ( new Scans\WpCore\ConvertResultsToVos() )->convert( $oResults );
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Scans\WpCore\ResultsSet
	 */
	protected function convertVosToResults( $aVos ) {
		return ( new Scans\WpCore\ConvertVosToResults() )->convert( $aVos );
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO $oVo
	 * @return Scans\WpCore\ResultItem
	 */
	protected function convertVoToResultItem( $oVo ) {
		return ( new Scans\WpCore\ConvertVosToResults() )->convertItem( $oVo );
	}

	/**
	 * @return Scans\WpCore\Repair|mixed
	 */
	protected function getRepairer() {
		return new Scans\WpCore\Repair();
	}

	/**
	 * TODO:
	 * $aAutoFixIndexFiles = $this->getMod()->getDef( 'corechecksum_autofix' );
	 * if ( empty( $aAutoFixIndexFiles ) ) {
	 * $aAutoFixIndexFiles = array();
	 */

	/**
	 * @return Scans\WpCore\Scanner
	 */
	protected function getScanner() {
		return ( new Scans\WpCore\Scanner() )
			->setExclusions( $this->getFullExclusions() )
			->setMissingExclusions( $this->getMissingOnlyExclusions() );
	}

	public function cron_dailyChecksumScan() {
		if ( doing_action( 'wp_maybe_auto_update' ) || did_action( 'wp_maybe_auto_update' ) ) {
			return;
		}
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$bOptionRepair = $oFO->isWcfScanAutoRepair() || ( $this->loadRequest()->query( 'checksum_repair' ) == 1 );

		$oResult = $bOptionRepair ? $this->doScanAndFullRepair() : $this->doScan();
		if ( $oResult->hasItems() ) {
			$this->emailResults( $oResult );
		}
	}

	/**
	 * @return array
	 */
	protected function getFullExclusions() {
		$aExclusions = $this->getMod()->getDef( 'corechecksum_exclusions' );
		$aExclusions = is_array( $aExclusions ) ? $aExclusions : array();

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
		return is_array( $aExclusions ) ? $aExclusions : array();
	}

	/**
	 * Move to table
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aEntries
	 * @return array
	 */
	public function formatEntriesForDisplay( $aEntries ) {
		if ( is_array( $aEntries ) ) {
			$oWp = $this->loadWp();
			$oCarbon = new \Carbon\Carbon();

			$nTs = $this->loadRequest()->ts();
			foreach ( $aEntries as $nKey => $oEntry ) {
				$oIt = ( new Scans\WpCore\ConvertVosToResults() )->convertItem( $oEntry );
				$aE = $oEntry->getRawData();
				$aE[ 'path' ] = $oIt->path_fragment;
				$aE[ 'status' ] = $oIt->is_checksumfail ? 'Modified' : ( $oIt->is_missing ? 'Missing' : 'Unknown' );
				$aE[ 'ignored' ] = ( $oEntry->ignored_at > 0 && $nTs > $oEntry->ignored_at ) ? 'Yes' : 'No';
				$aE[ 'created_at' ] = $oCarbon->setTimestamp( $oEntry->getCreatedAt() )->diffForHumans()
									  .'<br/><small>'.$oWp->getTimeStringForDisplay( $oEntry->getCreatedAt() ).'</small>';
				$aEntries[ $nKey ] = $aE;
			}
		}
		return $aEntries;
	}

	/**
	 * @return ScanTableWcf
	 */
	protected function getTableRenderer() {
		$this->requireCommonLib( 'Components/Tables/ScanTableWcf.php' );
		return new ScanTableWcf();
	}

	/**
	 * @param $sItemId - database row ID
	 * @return bool
	 * @throws Exception
	 */
	protected function repairItem( $sItemId ) {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO $oEntry */
		$oEntry = $this->getScannerDb()
					   ->getQuerySelector()
					   ->byId( $sItemId );
		if ( empty( $oEntry ) ) {
			throw new Exception( 'Item could not be found for repair.' );
		}
		$oItem = $this->convertVoToResultItem( $oEntry );

		( new Scans\WpCore\Repair() )->repairItem( $oItem );
		$this->doStatIncrement( 'file.corechecksum.replaced' );

		return true;
	}

	/**
	 * @param Scans\WpCore\ResultsSet $oResults
	 */
	protected function emailResults( $oResults ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$this->getEmailProcessor()
			 ->sendEmailWithWrap(
				 $sTo,
				 sprintf( '[%s] %s', _wpsf__( 'Warning' ), _wpsf__( 'Modified Core WordPress Files Discovered' ) ),
				 $this->buildEmailBodyFromFiles( $oResults )
			 );

		$this->addToAuditEntry(
			sprintf( _wpsf__( 'Sent Checksum Scan Notification email alert to: %s' ), $sTo )
		);
	}

	/**
	 * @param Scans\WpCore\ResultsSet $oResults
	 * @return array
	 */
	private function buildEmailBodyFromFiles( $oResults ) {
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
			$aContent = $this->buildListOfFilesForEmail( $oResults );
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
	 * @param Scans\WpCore\ResultsSet $oResult
	 * @return array
	 */
	private function buildListOfFilesForEmail( $oResult ) {
		$aContent = array();

		if ( $oResult->hasChecksumFailed() ) {
			$aContent[] = _wpsf__( "The contents of the core files listed below don't match official WordPress files:" );
			foreach ( $oResult->getChecksumFailedPaths() as $sFile ) {
				$aContent[] = ' - '.$sFile.$this->getFileRepairLink( $sFile );
			}
		}
		if ( $oResult->hasMissing() ) {
			$aContent[] = _wpsf__( 'The WordPress Core Files listed below are missing:' );
			foreach ( $oResult->getMissingPaths() as $sFile ) {
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
	 * @return callable
	 */
	protected function getCronCallback() {
		return array( $this, 'cron_dailyChecksumScan' );
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->getWcfCronName();
	}
}