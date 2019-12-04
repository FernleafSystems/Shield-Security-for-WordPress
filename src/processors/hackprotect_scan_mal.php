<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Mal extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'mal';

	/**
	 * @return bool
	 */
	public function isAvailable() {
		return !$this->isRestricted();
	}

	/**
	 * @return bool
	 */
	public function isRestricted() {
		return !$this->getMod()->isPremium();
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isMalScanEnabled();
	}

	/**
	 * @return Shield\Scans\Mal\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Shield\Scans\Mal\Utilities\ItemActionHandler();
	}

	/**
	 * @param Shield\Scans\Mal\ResultsSet $oRes
	 */
	protected function runCronAutoRepair( $oRes ) {
	}

	/**
	 * @return bool
	 */
	protected function isCronAutoRepair() {
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isMalAutoRepair();
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
				 sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Potential Malware Detected', 'wp-simple-firewall' ) ),
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
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$sName = $this->getCon()->getHumanName();
		$sHomeUrl = Services::WpGeneral()->getHomeUrl();

		$aContent = [
			sprintf( __( "The %s Malware Scanner found files with potential malware.", 'wp-simple-firewall' ), $sName ),
			sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ),
				__( "You must examine the file(s) carefully to determine whether suspicious code is really present.", 'wp-simple-firewall' ) ),
			sprintf( __( "The %s Malware Scanner searches for common malware patterns and so false positives (detection errors) are to be expected sometimes.", 'wp-simple-firewall' ), $sName ),
			sprintf( '%s: %s', __( 'Site URL', 'wp-simple-firewall' ), sprintf( '<a href="%s" target="_blank">%s</a>', $sHomeUrl, $sHomeUrl ) ),
		];

		if ( $oOpts->isMalAutoRepair() || $oFO->isIncludeFileLists() ) {
			$aContent = array_merge( $aContent, $this->buildListOfFilesForEmail( $oResults ) );
			$aContent[] = '';

			if ( $oOpts->isMalAutoRepair() ) {
				$aContent[] = '<strong>'.sprintf( __( "%s has already attempted to repair the files.", 'wp-simple-firewall' ), $sName ).'</strong>'
							  .' '.__( 'But, you should always check these files to ensure everything is as you expect.', 'wp-simple-firewall' );
			}
			else {
				$aContent[] = __( 'You should review these files and replace them with official versions if required.', 'wp-simple-firewall' );
				$aContent[] = __( 'Alternatively you can have the plugin attempt to repair/replace these files automatically.', 'wp-simple-firewall' )
							  .' [<a href="https://shsec.io/g2">'.__( 'More Info', 'wp-simple-firewall' ).'</a>]';
			}
		}

		$aContent[] = '';
		$aContent[] = __( 'We recommend you run the scanner to review your site', 'wp-simple-firewall' ).':';
		$aContent[] = $this->getScannerButtonForEmail();

		if ( !$this->getCon()->isRelabelled() ) {
			$aContent[] = '';
			$aContent[] = '[ <a href="https://shsec.io/moreinfochecksum">'.__( 'More Info On This Scanner', 'wp-simple-firewall' ).'</a> ]';
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