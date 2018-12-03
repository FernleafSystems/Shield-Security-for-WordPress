<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Ufc', false ) ) {
	return;
}

require_once( __DIR__.'/hackprotect_scan_base.php' );

use \FernleafSystems\Wordpress\Plugin\Shield;

class ICWP_WPSF_Processor_HackProtect_Ufc extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'ufc';

	/**
	 * @param Shield\Scans\UnrecognisedCore\ResultsSet $oResults
	 * @return Shield\Databases\Scanner\EntryVO[]
	 */
	protected function convertResultsToVos( $oResults ) {
		return ( new Shield\Scans\UnrecognisedCore\ConvertResultsToVos() )->convert( $oResults );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Shield\Scans\UnrecognisedCore\ResultsSet
	 */
	protected function convertVosToResults( $aVos ) {
		return ( new Shield\Scans\UnrecognisedCore\ConvertVosToResults() )->convert( $aVos );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO $oVo
	 * @return Shield\Scans\UnrecognisedCore\ResultItem
	 */
	protected function convertVoToResultItem( $oVo ) {
		return ( new Shield\Scans\UnrecognisedCore\ConvertVosToResults() )->convertItem( $oVo );
	}

	/**
	 * @return Shield\Scans\UnrecognisedCore\Repair
	 */
	protected function getRepairer() {
		return new Shield\Scans\UnrecognisedCore\Repair();
	}

	/**
	 * @return Shield\Scans\UnrecognisedCore\Scanner
	 */
	protected function getScanner() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$oScanner = ( new Shield\Scans\UnrecognisedCore\Scanner() )
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
		/** @var Shield\Databases\Scanner\EntryVO $oEntry */
		$oEntry = $this->getScannerDb()
					   ->getDbHandler()
					   ->getQuerySelector()
					   ->byId( $sItemId );
		if ( empty( $oEntry ) ) {
			throw new Exception( 'Item could not be found for repair.' );
		}
		$oItem = $this->convertVoToResultItem( $oEntry );

		( new Shield\Scans\UnrecognisedCore\Repair() )->repairItem( $oItem );
		$this->doStatIncrement( 'file.corechecksum.replaced' ); //TODO

		return true;
	}

	/**
	 * @param Shield\Scans\UnrecognisedCore\ResultsSet $oRes
	 */
	protected function runCronAutoRepair( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isUfcDeleteFiles() ) {
			$this->getRepairer()->repairResultsSet( $oRes );
		}
	}

	/**
	 * @param Shield\Scans\UnrecognisedCore\ResultsSet $oRes
	 * @return bool - true if user notified
	 */
	protected function runCronUserNotify( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$bSend = $oFO->isUfcSendReport();
		if ( $bSend ) {
			$this->emailResults( $oRes );
		}
		return $bSend;
	}

	/**
	 * @param Shield\Scans\UnrecognisedCore\ResultsSet $oRes
	 */
	protected function emailResults( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$this->getEmailProcessor()
			 ->sendEmailWithWrap(
				 $sTo,
				 sprintf( '%s - %s', _wpsf__( 'Warning' ), _wpsf__( 'Unrecognised WordPress Files Detected' ) ),
				 $this->buildEmailBodyFromFiles( $oRes->getItemsPathsFull() )
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
			'',
			sprintf( '%s: %s', _wpsf__( 'Site URL' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
		);

		if ( $oFO->isUfcDeleteFiles() || $oFO->isIncludeFileLists() ) {
			$aContent[] = _wpsf__( 'Files discovered' ).':';
			foreach ( $aFiles as $sFile ) {
				$aContent[] = ' - '.$sFile;
			}
			$aContent[] = '';

			if ( $oFO->isUfcDeleteFiles() ) {
				$aContent[] = sprintf( _wpsf__( '%s has attempted to delete these files based on your current settings.' ), $sName );
				$aContent[] = '';
			}
		}

		$aContent[] = _wpsf__( 'We recommend you run the scanner to review your site' ).':';
		$aContent[] = $this->getScannerButtonForEmail();
		$aContent[] = '';

		if ( !$oFO->getConn()->isRelabelled() ) {
			$aContent[] = sprintf( '[ <a href="https://icwp.io/moreinfoufc">%s</a> ]', _wpsf__( 'More Info On This Scanner' ) );
		}

		return $aContent;
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