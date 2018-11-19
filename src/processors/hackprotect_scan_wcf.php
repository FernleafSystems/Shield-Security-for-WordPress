<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Wcf', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/hackprotect_scan_base.php' );

use \FernleafSystems\Wordpress\Plugin\Shield;

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
	 * @param Shield\Scans\WpCore\ResultsSet $oResults
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[]
	 */
	protected function convertResultsToVos( $oResults ) {
		return ( new Shield\Scans\WpCore\ConvertResultsToVos() )->convert( $oResults );
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Shield\Scans\WpCore\ResultsSet
	 */
	protected function convertVosToResults( $aVos ) {
		return ( new Shield\Scans\WpCore\ConvertVosToResults() )->convert( $aVos );
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO $oVo
	 * @return Shield\Scans\WpCore\ResultItem
	 */
	protected function convertVoToResultItem( $oVo ) {
		return ( new Shield\Scans\WpCore\ConvertVosToResults() )->convertItem( $oVo );
	}

	/**
	 * @return Shield\Scans\WpCore\Repair|mixed
	 */
	protected function getRepairer() {
		return new Shield\Scans\WpCore\Repair();
	}

	/**
	 * TODO:
	 * $aAutoFixIndexFiles = $this->getMod()->getDef( 'corechecksum_autofix' );
	 * if ( empty( $aAutoFixIndexFiles ) ) {
	 * $aAutoFixIndexFiles = array();
	 */

	/**
	 * @return Shield\Scans\WpCore\Scanner
	 */
	protected function getScanner() {
		return ( new Shield\Scans\WpCore\Scanner() )
			->setExclusions( $this->getFullExclusions() )
			->setMissingExclusions( $this->getMissingOnlyExclusions() );
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
	 * @param $sItemId - database row ID
	 * @return bool
	 * @throws Exception
	 */
	protected function repairItem( $sItemId ) {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO $oEntry */
		$oEntry = $this->getScannerDb()
					   ->getDbHandler()
					   ->getQuerySelector()
					   ->byId( $sItemId );
		if ( empty( $oEntry ) ) {
			throw new Exception( 'Item could not be found for repair.' );
		}
		$oItem = $this->convertVoToResultItem( $oEntry );

		( new Shield\Scans\WpCore\Repair() )->repairItem( $oItem );
		$this->doStatIncrement( 'file.corechecksum.replaced' );

		return true;
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO[] $aRes
	 */
	protected function handleScanResults( $aRes ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$oRes = $this->convertVosToResults( $aRes );

		if ( $oFO->isWcfScanAutoRepair() ) {
			$this->getRepairer()->repairResultsSet( $oRes );
		}

		if ( $oFO->isUfcSendReport() ) {
			$this->emailResults( $oRes );
		}
	}

	/**
	 * @param Shield\Scans\WpCore\ResultsSet $oResults
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
	 * @param Shield\Scans\WpCore\ResultsSet $oResults
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
	 * @param Shield\Scans\WpCore\ResultsSet $oResult
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
	 * @return string
	 */
	protected function getCronName() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->getWcfCronName();
	}
}