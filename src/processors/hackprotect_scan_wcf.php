<?php

use \FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Wcf extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'wcf';

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->isWcfScanEnabled();
	}

	/**
	 * @param Shield\Scans\Wcf\ResultsSet $oResults
	 * @return Shield\Databases\Scanner\EntryVO[]
	 */
	protected function convertResultsToVos( $oResults ) {
		return ( new Shield\Scans\Wcf\ConvertResultsToVos() )->convert( $oResults );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Shield\Scans\Wcf\ResultsSet
	 */
	protected function convertVosToResults( $aVos ) {
		return ( new Shield\Scans\Wcf\ConvertVosToResults() )->convert( $aVos );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO $oVo
	 * @return Shield\Scans\Wcf\ResultItem
	 */
	protected function convertVoToResultItem( $oVo ) {
		return ( new Shield\Scans\Wcf\ConvertVosToResults() )->convertItem( $oVo );
	}

	/**
	 * @return Shield\Scans\Wcf\Repair|mixed
	 */
	protected function getRepairer() {
		return new Shield\Scans\Wcf\Repair();
	}

	/**
	 * @return Shield\Scans\Wcf\ResultsSet
	 */
	protected function getResultsSet() {
		return new Shield\Scans\Wcf\ResultsSet();
	}

	/**
	 * @return Shield\Scans\Wcf\ResultItem
	 */
	protected function getResultItem() {
		return new Shield\Scans\Wcf\ResultItem();
	}

	/**
	 * TODO:
	 * $aAutoFixIndexFiles = $this->getMod()->getDef( 'corechecksum_autofix' );
	 * if ( empty( $aAutoFixIndexFiles ) ) {
	 * $aAutoFixIndexFiles = [];
	 */

	/**
	 * @return Shield\Scans\Wcf\Scanner
	 */
	protected function getScanner() {
		return ( new Shield\Scans\Wcf\Scanner() )
			->setExclusions( $this->getFullExclusions() )
			->setMissingExclusions( $this->getMissingOnlyExclusions() );
	}

	/**
	 * @return Shield\Scans\Wcf\AsyncScanner
	 */
	protected function getScannerAsync() {
		$sTmpDir = $this->getCon()->getPluginCachePath( static::SCAN_SLUG );
		Services::WpFs()->mkdir( $sTmpDir );
		return ( new Shield\Scans\Wcf\AsyncScanner() )
			->setMod( $this->getMod() )
			->setTmpDir( $sTmpDir )
			->setScanActionVO( $this->getScanActionVO() );
	}

	/**
	 * @return Shield\Scans\Wcf\WcfScanActionVO
	 */
	protected function getScanActionVO() {
		$oAction = new Shield\Scans\Wcf\WcfScanActionVO();
		$oAction->id = static::SCAN_SLUG.'scan';
		return $oAction;
	}

	/**
	 * @return array
	 */
	protected function getFullExclusions() {
		$aExclusions = $this->getMod()->getDef( 'wcf_exclusions' );
		$aExclusions = is_array( $aExclusions ) ? $aExclusions : [];

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
		$aExclusions = $this->getMod()->getDef( 'wcf_exclusions_missing_only' );
		return is_array( $aExclusions ) ? $aExclusions : [];
	}

	/**
	 * @param Shield\Scans\Wcf\ResultItem $oItem
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
	 * @param Shield\Scans\Wcf\ResultsSet $oRes
	 */
	protected function runCronAutoRepair( $oRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isWcfScanAutoRepair() ) {
			$this->getRepairer()->repairResultsSet( $oRes );
		}
	}

	/**
	 * @param Shield\Scans\Wcf\ResultsSet $oRes
	 * @return bool
	 */
	protected function runCronUserNotify( $oRes ) {
		$this->emailResults( $oRes );
		return true;
	}

	/**
	 * @param Shield\Scans\Wcf\ResultsSet $oResults
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
			'wcf_alert_sent',
			[
				'audit' => [
					'to'  => $sTo,
					'via' => 'email',
				]
			]
		);
	}

	/**
	 * @param Shield\Scans\Wcf\ResultsSet $oResults
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

		if ( $oFO->isWcfScanAutoRepair() || $oFO->isIncludeFileLists() ) {
			$aContent = array_merge( $aContent, $this->buildListOfFilesForEmail( $oResults ) );
			$aContent[] = '';

			if ( $oFO->isWcfScanAutoRepair() ) {
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
	 * @param Shield\Scans\Wcf\ResultsSet $oResult
	 * @return array
	 */
	private function buildListOfFilesForEmail( $oResult ) {
		$aContent = [];

		if ( $oResult->hasChecksumFailed() ) {
			$aContent[] = '';
			$aContent[] = __( "The following files have different content:", 'wp-simple-firewall' );
			foreach ( $oResult->getChecksumFailedPaths() as $sFile ) {
				$aContent[] = ' - '.$sFile;
			}
		}
		if ( $oResult->hasMissing() ) {
			$aContent[] = '';
			$aContent[] = __( 'The following files are missing:', 'wp-simple-firewall' );
			foreach ( $oResult->getMissingPaths() as $sFile ) {
				$aContent[] = ' - '.$sFile;
			}
		}
		return $aContent;
	}
}