<?php

use \FernleafSystems\Wordpress\Plugin\Shield;

class ICWP_WPSF_Processor_HackProtect_Apc extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'apc';

	/**
	 */
	public function run() {
		parent::run();
		add_action( 'deleted_plugin', [ $this, 'onDeletedPlugin' ], 10, 0 );
	}

	public function onDeletedPlugin() {
		$this->doScan();
	}

	/**
	 * @param Shield\Scans\Apc\ResultsSet $oResults
	 * @return Shield\Databases\Scanner\EntryVO[]
	 */
	protected function convertResultsToVos( $oResults ) {
		return ( new Shield\Scans\Apc\ConvertResultsToVos() )->convert( $oResults );
	}

	/**
	 * @param mixed|Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Shield\Scans\Apc\ResultsSet
	 */
	protected function convertVosToResults( $aVos ) {
		return ( new Shield\Scans\Apc\ConvertVosToResults() )->convert( $aVos );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO $oVo
	 * @return Shield\Scans\Apc\ResultItem
	 */
	protected function convertVoToResultItem( $oVo ) {
		return ( new Shield\Scans\Apc\ConvertVosToResults() )->convertItem( $oVo );
	}

	/**
	 * @return null
	 */
	protected function getRepairer() {
		return null;
	}

	/**
	 * @return Shield\Scans\Apc\Scanner
	 */
	protected function getScanner() {
		return new Shield\Scans\Apc\Scanner();
	}

	/**
	 * @param Shield\Scans\Apc\ResultsSet $oRes
	 */
	protected function runCronAutoRepair( $oRes ) {
		// no autorepair
	}

	/**
	 * @param Shield\Scans\Apc\ResultsSet $oRes
	 * @return bool - true if user notified
	 */
	protected function runCronUserNotify( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$bSend = $oFO->isWpvulnSendEmail();
		if ( $bSend ) {
			$this->emailResults( $oRes );
		}
		return $bSend;
	}

	/**
	 * @param Shield\Scans\Apc\ResultsSet $oRes
	 * @return bool
	 */
	protected function emailResults( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$oWpPlugins = $this->loadWpPlugins();
		$oWpThemes = $this->loadWpThemes();
		$oCon = $this->getCon();

		$aContent = array(
			sprintf( _wpsf__( '%s has detected items with known security vulnerabilities.' ), $oCon->getHumanName() ),
			_wpsf__( 'You should update or remove these items at your earliest convenience.' ),
			_wpsf__( 'Details for the items(s) are below:' ),
			'',
		);

		/** @var Shield\Scans\Apc\ResultItem $oItem */
		foreach ( $oRes->getItems() as $oItem ) {

			if ( $oItem->context == 'plugins' ) {
				$aPlugin = $oWpPlugins->getPlugin( $oItem->slug );
				$sName = sprintf( '%s - %s', _wpsf__( 'Plugin' ), empty( $aPlugin ) ? 'Unknown' : $aPlugin[ 'Name' ] );
			}
			else {
				$sName = sprintf( '%s - %s', _wpsf__( 'Theme' ), $oWpThemes->getCurrentThemeName() );
			}

			$oVuln = $oItem->getWpVulnVo();
			$aContent[] = implode( "<br />", array(
				sprintf( '%s: %s', _wpsf__( 'Item' ), $sName ),
				'- '.sprintf( _wpsf__( 'Vulnerability Title: %s' ), $oVuln->title ),
				'- '.sprintf( _wpsf__( 'Vulnerability Type: %s' ), $oVuln->vuln_type ),
				'- '.sprintf( _wpsf__( 'Fixed Version: %s' ), $oVuln->fixed_in ),
				'- '.sprintf( _wpsf__( 'Further Information: %s' ), $oVuln->getUrl() ),
				'',
			) );
		}

		$aContent[] = $this->getScannerButtonForEmail();
		$aContent[] = '';

		$sSubject = sprintf( '%s - %s', _wpsf__( 'Warning' ), _wpsf__( 'Plugin(s) Discovered With Known Security Vulnerabilities.' ) );
		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$bSendSuccess = $this->getEmailProcessor()
							 ->sendEmailWithWrap( $sTo, $sSubject, $aContent );

		if ( $bSendSuccess ) {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Successfully sent Plugin Vulnerability Notification email alert to: %s' ), $sTo ) );
		}
		else {
			$this->addToAuditEntry( sprintf( _wpsf__( 'Failed to send Plugin Vulnerability Notification email alert to: %s' ), $sTo ) );
		}
		return $bSendSuccess;
	}

	/**
	 * @return string[]
	 */
	protected function getAllAbandonedPlugins() {
		return $this->getAllAbandoned()->getUniqueSlugs();
	}

	/**
	 * @return Shield\Scans\Apc\ResultsSet
	 */
	protected function getAllAbandoned() {
		/** @var Shield\Databases\Scanner\Select $oSel */
		$oSel = $this->getScannerDb()
					 ->getDbHandler()
					 ->getQuerySelector();
		$aVos = $oSel->filterByScan( static::SCAN_SLUG )
					 ->filterByNotIgnored()
					 ->query();
		return $this->convertVosToResults( $aVos );
	}

	/**
	 * @return bool
	 */
	protected function countVulnerablePlugins() {
		return $this->getAllAbandoned()->countUniqueSlugsForPluginsContext();
	}

	/**
	 * @return string
	 */
	protected function getCronName() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->getWpvCronName();
	}
}