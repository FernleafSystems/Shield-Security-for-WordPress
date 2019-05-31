<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Mal extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'mal';

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->isMalScanEnabled();
	}

	/**
	 * @param Shield\Scans\Mal\ResultsSet $oResults
	 * @return Shield\Databases\Scanner\EntryVO[]
	 */
	protected function convertResultsToVos( $oResults ) {
		return ( new Shield\Scans\Mal\ConvertResultsToVos() )->convert( $oResults );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Shield\Scans\Mal\ResultsSet
	 */
	protected function convertVosToResults( $aVos ) {
		return ( new Shield\Scans\Mal\ConvertVosToResults() )->convert( $aVos );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO $oVo
	 * @return Shield\Scans\Mal\ResultItem
	 */
	protected function convertVoToResultItem( $oVo ) {
		return ( new Shield\Scans\Mal\ConvertVosToResults() )->convertItem( $oVo );
	}

	/**
	 * @return Shield\Scans\Mal\Repair|mixed
	 */
	protected function getRepairer() {
		return ( new Shield\Scans\Mal\Repair() )->setMod( $this->getMod() );
	}

	/**
	 * @return Shield\Scans\Mal\Scanner
	 */
	protected function getScanner() {
		return ( new Shield\Scans\Mal\Scanner() )->setMalSigs( $this->getMalSignatures() );
	}

	/**
	 * @return string[]
	 * @throws \Exception
	 */
	private function getMalSignatures() {
		$oWpFs = Services::WpFs();
		$sFile = $this->getCon()->getPluginCachePath( 'malsigs.txt' );
		if ( $oWpFs->exists( $sFile ) ) {
			$aSigs = explode( "\n", \LZCompressor\LZString::decompress( base64_decode( $oWpFs->getFileContent( $sFile ) ) ) );
		}
		else {
			$aSigs = array_filter(
				array_map( 'trim',
					explode( "\n", Services::HttpRequest()->getContent( $this->getMod()->getDef( 'url_mal_sigs' ) ) )
				),
				function ( $sLine ) {
					return ( ( strpos( $sLine, '#' ) !== 0 ) && strlen( $sLine ) > 0 );
				}
			);

			$oWpFs->putFileContent( $sFile, base64_encode( \LZCompressor\LZString::compress( implode( "\n", $aSigs ) ) ) );
		}
		return $aSigs;
	}

	/**
	 * @param Shield\Scans\Mal\ResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	protected function itemRepair( $oItem ) {
		$this->getRepairer()->repairItem( $oItem );
		$this->doStatIncrement( 'file.malware.replaced' );
		return true;
	}

	/**
	 * @param Shield\Scans\Mal\ResultsSet $oRes
	 */
	protected function runCronAutoRepair( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isMalScanAutoRepair() ) {
			$this->getRepairer()
				 ->repairResultsSet( $oRes );
		}
	}

	/**
	 * @param Shield\Scans\Mal\ResultsSet $oRes
	 * @return bool
	 */
	protected function runCronUserNotify( $oRes ) {
		$this->emailResults( $oRes );
		return true;
	}

	/**
	 * @param Shield\Scans\Mal\ResultsSet $oResults
	 */
	protected function emailResults( $oResults ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$this->getEmailProcessor()
			 ->sendEmailWithWrap(
				 $sTo,
				 sprintf( '[%s] %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Modified Core WordPress Files Discovered', 'wp-simple-firewall' ) ),
				 $this->buildEmailBodyFromFiles( $oResults )
			 );

		$this->addToAuditEntry(
			sprintf( __( 'Sent Checksum Scan Notification email alert to: %s', 'wp-simple-firewall' ), $sTo )
		);
	}

	/**
	 * @param Shield\Scans\Mal\ResultsSet $oResults
	 * @return array
	 */
	private function buildEmailBodyFromFiles( $oResults ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$sName = $this->getCon()->getHumanName();
		$sHomeUrl = Services::WpGeneral()->getHomeUrl();

		$aContent = [
			sprintf( __( "The %s Core File Scanner found files with potential problems.", 'wp-simple-firewall' ), $sName ),
			sprintf( '%s: %s', __( 'Site URL', 'wp-simple-firewall' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
		];

		if ( $oFO->isMalScanAutoRepair() || $oFO->isIncludeFileLists() ) {
			$aContent = array_merge( $aContent, $this->buildListOfFilesForEmail( $oResults ) );
			$aContent[] = '';

			if ( $oFO->isMalScanAutoRepair() ) {
				$aContent[] = '<strong>'.sprintf( __( "%s has already attempted to repair the files.", 'wp-simple-firewall' ), $sName ).'</strong>'
							  .' '.__( 'But, you should always check these files to ensure everything is as you expect.', 'wp-simple-firewall' );
			}
			else {
				$aContent[] = __( 'You should review these files and replace them with official versions if required.', 'wp-simple-firewall' );
				$aContent[] = __( 'Alternatively you can have the plugin attempt to repair/replace these files automatically.', 'wp-simple-firewall' )
							  .' [<a href="https://icwp.io/moreinfochecksum">'.__( 'More Info', 'wp-simple-firewall' ).']</a>';
			}
		}

		$aContent[] = '';
		$aContent[] = __( 'We recommend you run the scanner to review your site', 'wp-simple-firewall' ).':';
		$aContent[] = $this->getScannerButtonForEmail();

		if ( !$this->getCon()->isRelabelled() ) {
			$aContent[] = '';
			$aContent[] = '[ <a href="https://icwp.io/moreinfochecksum">'.__( 'More Info On This Scanner', 'wp-simple-firewall' ).' ]</a>';
		}

		return $aContent;
	}

	/**
	 * @param Shield\Scans\Mal\ResultsSet $oResult
	 * @return array
	 */
	private function buildListOfFilesForEmail( $oResult ) {
		$aContent = [ '' ];
		$aContent[] = __( 'The following files contain suspected malware:', 'wp-simple-firewall' );
		foreach ( $oResult->getAllItems() as $oItem ) {
			/** @var Shield\Scans\Mal\ResultItem $oItem */
			$aContent[] = ' - '.$oItem->path_fragment;
		}
		return $aContent;
	}
}