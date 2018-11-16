<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Ufc', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/hackprotect_scan_base.php' );

use \FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ICWP_WPSF_Processor_HackProtect_Ufc extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'ufc';

	/**
	 */
	public function run() {
		parent::run();

		if ( $this->loadWpUsers()->isUserAdmin() ) {
			$oReq = $this->loadRequest();

			switch ( $oReq->query( 'shield_action' ) ) {
				case 'delete_unrecognised_file':
					$sPath = '/'.$oReq->query( 'repair_file_path' ); // "/" prevents esc_url() from prepending http.
					break;
			}
		}
	}

	/**
	 * @param Scans\UnrecognisedCore\ResultsSet $oResults
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[]
	 */
	protected function convertResultsToVos( $oResults ) {
		return ( new Scans\UnrecognisedCore\ConvertResultsToVos() )->convert( $oResults );
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Scans\UnrecognisedCore\ResultsSet
	 */
	protected function convertVosToResults( $aVos ) {
		return ( new Scans\UnrecognisedCore\ConvertVosToResults() )->convert( $aVos );
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO $oVo
	 * @return Scans\UnrecognisedCore\ResultItem
	 */
	protected function convertVoToResultItem( $oVo ) {
		return ( new Scans\UnrecognisedCore\ConvertVosToResults() )->convertItem( $oVo );
	}

	/**
	 * @return Scans\UnrecognisedCore\Repair
	 */
	protected function getRepairer() {
		return new Scans\UnrecognisedCore\Repair();
	}

	/**
	 * @return Scans\UnrecognisedCore\Scanner
	 */
	protected function getScanner() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$oScanner = ( new Scans\UnrecognisedCore\Scanner() )
			->setExclusions( $oFO->getUfcFileExclusions() );

		if ( $oFO->isUfsScanUploads() ) {
			$sUploadsDir = $this->loadWp()->getDirUploads();
			if ( !empty( $sUploadsDir ) ) {
				$oScanner->addScanDirector( $sUploadsDir )
						 ->addDirSpecificFileTypes(
							 $sUploadsDir,
							 [
								 'php',
								 'php5',
								 'js',
							 ]
						 );
			}
		}
		return $oScanner;
	}

	public function cron_dailyFileCleanerScan() {
		if ( doing_action( 'wp_maybe_auto_update' ) || did_action( 'wp_maybe_auto_update' ) ) {
			return;
		}
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isUfcEnabled() ) {
			/** @var Scans\UnrecognisedCore\ResultsSet $oRes */
			$oRes = $oFO->isUfcDeleteFiles() ? $this->doScanAndFullRepair() : $this->doScan();
			if ( $oRes->hasItems() && $oFO->isUfsSendReport() ) {
				$this->emailResults( $oRes->getItemsPathsFull() );
			}
		}
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
				$oIt = ( new Scans\UnrecognisedCore\ConvertVosToResults() )->convertItem( $oEntry );
				$aE = $oEntry->getRawData();
				$aE[ 'path' ] = $oIt->path_fragment;
				$aE[ 'status' ] = 'Unrecognised File';
				$aE[ 'ignored' ] = ( $oEntry->ignored_at > 0 && $nTs > $oEntry->ignored_at ) ? 'Yes' : 'No';
				$aE[ 'created_at' ] = $oCarbon->setTimestamp( $oEntry->getCreatedAt() )->diffForHumans()
									  .'<br/><small>'.$oWp->getTimeStringForDisplay( $oEntry->getCreatedAt() ).'</small>';
				$aEntries[ $nKey ] = $aE;
			}
		}
		return $aEntries;
	}

	/**
	 * @return ScanTableUfc
	 */
	protected function getTableRenderer() {
		$this->requireCommonLib( 'Components/Tables/ScanTableUfc.php' );
		return new ScanTableUfc();
	}

	/**
	 * @param $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function deleteItem( $sItemId ) {
		return $this->repairItem( $sItemId );
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

		( new Scans\UnrecognisedCore\Repair() )->repairItem( $oItem );
		$this->doStatIncrement( 'file.corechecksum.replaced' );

		return true;
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
	 * @return callable
	 */
	protected function getCronCallback() {
		return array( $this, 'cron_dailyFileCleanerScan' );
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->getUfcCronName();
	}
}