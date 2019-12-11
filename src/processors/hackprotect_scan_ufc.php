<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

class ICWP_WPSF_Processor_HackProtect_Ufc extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'ufc';

	/**
	 * @return Ufc\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Ufc\Utilities\ItemActionHandler();
	}

	/**
	 * @param Shield\Scans\Ufc\ResultsSet $oRes
	 * @return bool - true if user notified
	 */
	protected function runCronUserNotify( $oRes ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$bSend = $oFO->isUfcSendReport();
		if ( $bSend ) {
			$this->emailResults( $oRes );
		}
		return $bSend;
	}

	/**
	 * @param Shield\Scans\Ufc\ResultsSet $oRes
	 */
	protected function emailResults( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$sTo = $oFO->getPluginDefaultRecipientAddress();
		$this->getEmailProcessor()
			 ->sendEmailWithWrap(
				 $sTo,
				 sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Unrecognised WordPress Files Detected', 'wp-simple-firewall' ) ),
				 $this->buildEmailBodyFromFiles( $oRes->getItemsPathsFull() )
			 );

		$this->getCon()->fireEvent(
			'ufc_alert_sent',
			[
				'audit' => [
					'to'  => $sTo,
					'via' => 'email',
				]
			]
		);
	}

	/**
	 * The newer approach is to only enumerate files if they were deleted
	 * @param string[] $aFiles
	 * @return string[]
	 */
	private function buildEmailBodyFromFiles( $aFiles ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$oCon = $this->getCon();
		$sName = $oCon->getHumanName();
		$sHomeUrl = Services::WpGeneral()->getHomeUrl();

		$aContent = [
			sprintf( __( 'The %s Unrecognised File Scanner found files which you need to review.', 'wp-simple-firewall' ), $sName ),
			'',
			sprintf( '%s: %s', __( 'Site URL', 'wp-simple-firewall' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
		];

		if ( $oOpts->isUfcDeleteFiles() || $oMod->isIncludeFileLists() ) {
			$aContent[] = __( 'Files discovered', 'wp-simple-firewall' ).':';
			foreach ( $aFiles as $sFile ) {
				$aContent[] = ' - '.$sFile;
			}
			$aContent[] = '';

			if ( $oOpts->isUfcDeleteFiles() ) {
				$aContent[] = sprintf( __( '%s has attempted to delete these files based on your current settings.', 'wp-simple-firewall' ), $sName );
				$aContent[] = '';
			}
		}

		$aContent[] = __( 'We recommend you run the scanner to review your site', 'wp-simple-firewall' ).':';
		$aContent[] = $this->getScannerButtonForEmail();
		$aContent[] = '';

		if ( !$oCon->isRelabelled() ) {
			$aContent[] = sprintf( '[ <a href="https://shsec.io/moreinfoufc">%s</a> ]', __( 'More Info On This Scanner', 'wp-simple-firewall' ) );
		}

		return $aContent;
	}
}