<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Mal extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'mal';

	/**
	 */
	public function run() {
		if ( isset( $_GET[ 'testscan' ] ) ) {
			$this->doAsyncScan();
			die();
		}
		parent::run();
	}

	/**
	 * @return bool
	 */
	public function doAsyncScan() {

		$oAction = new Shield\Scans\Mal\MalScanActionVO();
		$oAction->id = 'malware_scan';
		try {
			( new Shield\Scans\Mal\MalScanLauncher() )
				->setMod( $this->getMod() )
				->setTmpDir( $this->getCon()->getPluginCachePath( '' ) )
				->setAction( $oAction )
				->run();
		}
		catch ( \Exception $oE ) {
			return false;
		}

		if ( $oAction->ts_finish > 0 ) {
			$oResults = new Shield\Scans\Mal\ResultsSet();
			if ( $oAction->ts_start == $oAction->ts_finish ) {
				// Means that no files were found in the file build map
			}
			else if ( !empty( $oAction->results ) ) {
				foreach ( $oAction->results as $aRes ) {
					$oResults->addItem( ( new Shield\Scans\Mal\ResultItem() )->applyFromArray( $aRes ) );
				}
				$this->updateScanResultsStore( $oResults );
			}

			$this->getCon()->fireEvent( static::SCAN_SLUG.'_scan_run' );
			if ( $oResults->countItems() ) {
				$this->getCon()->fireEvent( static::SCAN_SLUG.'_scan_found' );
			}
		}
		else {
			Services::HttpRequest()
					->get(
						add_query_arg( [ 'testscan' => 1 ], Services::WpGeneral()->getHomeUrl() ),
						[
							'blocking' => true,
						]
					);
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function isAvailable() {
		return $this->getMod()->isPremium();
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oFO */
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
	 * @return Shield\Scans\Mal\Repair
	 */
	protected function getRepairer() {
		return ( new Shield\Scans\Mal\Repair() )->setMod( $this->getMod() );
	}

	/**
	 * @return Shield\Scans\Mal\Scanner
	 */
	protected function getScanner() {
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		return ( new Shield\Scans\Mal\Scanner() )
			->setMalSigsSimple( $oOpts->getMalSignaturesSimple() )
			->setMalSigsRegex( $oOpts->getMalSignaturesRegex() )
			->setWhitelistedPaths( $oOpts->getMalwareWhitelistPaths() );
	}

	/**
	 * @param Shield\Scans\Mal\ResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	protected function itemRepair( $oItem ) {
		$bSuccess = $this->getRepairer()->repairItem( $oItem );
		$this->getCon()->fireEvent(
			static::SCAN_SLUG.'_item_repair_'.( $bSuccess ? 'success' : 'fail' ),
			[ 'audit' => [ 'fragment' => $oItem->path_fragment ] ]
		);
		return $bSuccess;
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

		$this->getCon()->fireEvent(
			'mal_alert_sent',
			[
				'audit' => [
					'to'  => $sTo,
					'via' => 'email',
				]
			]
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