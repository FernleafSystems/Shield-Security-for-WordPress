<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Apc extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'apc';

	/**
	 */
	public function run() {
		parent::run();
		add_action( 'deleted_plugin', [ $this, 'onDeletedPlugin' ], 10, 0 );
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->isApcEnabled();
	}

	public function onDeletedPlugin() {
		$this->getScannerDb()
			 ->launchScan( static::SCAN_SLUG );
	}

	/**
	 * @return null
	 */
	protected function getRepairer() {
		return null;
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
		$bSend = $oFO->isApcSendEmail();
		if ( $bSend ) {
			$this->emailResults( $oRes );
		}
		return $bSend;
	}

	/**
	 * @param Shield\Scans\Apc\ResultsSet $oRes
	 */
	protected function emailResults( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$oWpPlugins = Services::WpPlugins();
		$oWpThemes = Services::WpThemes();
		$oWp = Services::WpGeneral();
		$oCon = $this->getCon();

		$aContent = [
			sprintf( __( '%s has detected abandoned plugins installed on your site.', 'wp-simple-firewall' ), $oCon->getHumanName() ),
			__( "Running code that hasn't seen any updates for over 2 years is far from ideal.", 'wp-simple-firewall' ),
			__( 'Details for the items(s) are below:', 'wp-simple-firewall' ),
			'',
		];

		/** @var Shield\Scans\Apc\ResultItem $oItem */
		foreach ( $oRes->getItems() as $oItem ) {

			if ( $oItem->context == 'plugins' ) {
				$oPlug = $oWpPlugins->getPluginAsVo( $oItem->slug );
				$sName = sprintf( '%s - %s', __( 'Plugin', 'wp-simple-firewall' ), empty( $oPlug ) ? 'Unknown' : $oPlug->Name );
			}
			else {
				$sName = sprintf( '%s - %s', __( 'Theme', 'wp-simple-firewall' ), $oWpThemes->getTheme( $oItem->slug ) );
			}

			$aContent[] = implode( "<br />", [
				sprintf( '%s: %s', __( 'Item', 'wp-simple-firewall' ), $sName ),
				'- '.sprintf( __( 'Last Updated: %s', 'wp-simple-firewall' ), $oWp->getTimeStringForDisplay( $oItem->last_updated_at, false ) ),
				'',
			] );
		}

		$aContent[] = $this->getScannerButtonForEmail();
		$aContent[] = '';

		$sSubject = sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Abandoned Plugin(s) Discovered On Your Site.', 'wp-simple-firewall' ) );
		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$this->getEmailProcessor()
			 ->sendEmailWithWrap( $sTo, $sSubject, $aContent );

		$this->getCon()->fireEvent(
			'apc_alert_sent',
			[
				'audit' => [
					'to'  => $sTo,
					'via' => 'email',
				]
			]
		);
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
		$oSel = $this->getMod()
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
	protected function countAbandonedPlugins() {
		return $this->getAllAbandoned()->countUniqueSlugsForPluginsContext();
	}
}