<?php

if ( class_exists( 'ICWP_WPSF_Wizard_HackProtect', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

/**
 * Class ICWP_WPSF_Wizard_HackProtect
 */
class ICWP_WPSF_Wizard_HackProtect extends ICWP_WPSF_Wizard_Base {

	/**
	 * @return string
	 */
	protected function getPageTitle() {
		return sprintf( _wpsf__( '%s Hack Protect Wizard' ), $this->getPluginCon()->getHumanName() );
	}

	/**
	 * @param string $sStep
	 * @return \FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( $sStep ) {
		switch ( $sStep ) {
			case 'exclusions':
				$oResponse = $this->process_Exclusions();
				break;
			case 'deletefiles':
				$oResponse = $this->process_DeleteFiles();
				break;
			case 'restorefiles':
				$oResponse = $this->process_RestoreFiles();
				break;
			case 'ufcconfig':
				$oResponse = $this->process_UfcConfig();
				break;
			case 'wcfconfig':
				$oResponse = $this->process_WcfConfig();
				break;

			default:
				$oResponse = null; // we don't process any steps we don't recognise.
				break;
		}
		return $oResponse;
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function process_Exclusions() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getModCon();
		$oFO->setUfcFileExclusions( explode( "\n", $this->loadDP()->post( 'exclusions' ) ) );

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( true )
						 ->setMessageText( 'File exclusions list has been updated.' );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function process_DeleteFiles() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getModCon();

		if ( $this->loadDP()->post( 'DeleteFiles' ) === 'Y' ) {
			// First get the current setting and if necessary, modify it and then reset it.
			$sDesiredOption = 'enabled_delete_only';
			$sCurrentOption = $oFO->getUnrecognisedFileScannerOption();
			if ( $sCurrentOption != $sDesiredOption ) {
				$oFO->setUfcOption( $sDesiredOption );
			}

			/** @var ICWP_WPSF_Processor_HackProtect $oProc */
			$oProc = $oFO->getProcessor();
			$oProc->getSubProcessorFileCleanerScan()
				  ->runScan();
			$oFO->setUfcOption( $sCurrentOption )
				->savePluginOptions();

			$sMessage = 'The scanner will have deleted these files if your filesystem permissions allowed it.';
		}
		else {
			$sMessage = 'No attempt was made to delete the files since the checkbox was not checked.';
		}

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( true )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function process_RestoreFiles() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getModCon();

		if ( $this->loadDP()->post( 'RestoreFiles' ) === 'Y' ) {
			/** @var ICWP_WPSF_Processor_HackProtect $oProc */
			$oProc = $oFO->getProcessor();
			$oProc->getSubProcessorChecksumScan()->doChecksumScan( true );

			$sMessage = 'The scanner will have restore these files if your filesystem permissions allowed it.';
		}
		else {
			$sMessage = 'No attempt was made to restore the files since the checkbox was not checked.';
		}

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( true )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function process_UfcConfig() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getModCon();

		$sSetting = $this->loadDP()->post( 'enable_scan' );
		$oFO->setUfcOption( $sSetting )
			->savePluginOptions();

		$bSuccess = ( $sSetting == $oFO->getUnrecognisedFileScannerOption() );

		if ( $bSuccess ) {
			if ( $oFO->isUfsEnabled() ) {
				$sMessage = 'Scanner automation has been enabled.';
			}
			else {
				$sMessage = 'Scanner automation has been disabled.';
			}
		}
		else {
			$sMessage = 'There was a problem with saving this option. You may need to reload.';
		}

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( $bSuccess )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function process_WcfConfig() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getModCon();

		$sSetting = $this->loadDP()->post( 'enable_scan' );

		$bEnabled = true;
		$bRestore = false;
		$bProcess = true;
		switch ( $sSetting ) {
			case 'enabled_report_only':
				break;
			case 'enabled_restore_report':
				$bRestore = true;
				break;
			default:
				$bProcess = false;
				break;
		}

		$bSuccess = false;
		if ( $bProcess ) {

			$oFO->setWcfScanEnabled( $bEnabled )
				->setWcfScanAutoRepair( $bRestore )
				->savePluginOptions();

			$bSuccess = ( $bEnabled == $oFO->isWcfScanEnabled() ) && ( $bRestore === $oFO->isWcfScanAutoRepair() );

			if ( $bSuccess ) {
				if ( $bEnabled ) {
					$sMessage = 'Scanner automation has been enabled.';
				}
				else {
					$sMessage = 'Scanner automation has been disabled.';
				}
			}
			else {
				$sMessage = 'There was a problem with saving this option. You may need to reload.';
			}
		}
		else {
			$sMessage = 'Scanner automation is unchanged because of failed request.';
		}

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( $bSuccess )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return string[]
	 */
	protected function determineWizardSteps() {

		switch ( $this->getCurrentWizard() ) {
			case 'wcf':
				$aSteps = $this->determineWizardSteps_Wcf();
				break;
			case 'ufc':
				$aSteps = $this->determineWizardSteps_Ufc();
				break;
			default:
				$aSteps = array();
				break;
		}

		return $aSteps;
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Wcf() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getModCon();

		$aStepsSlugs = array(
			'start',
			'scanresult',
		);
		if ( !$oFO->isWcfScanEnabled() ) {
			$aStepsSlugs[] = 'config';
		}
		$aStepsSlugs[] = 'finished';
		return $aStepsSlugs;
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Ufc() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getModCon();

		$aStepsSlugs = array(
			'start',
			'exclusions',
			'scanresult'
		);
		if ( !$oFO->isUfsEnabled() ) {
			$aStepsSlugs[] = 'config';
		}
		$aStepsSlugs[] = 'finished';
		return $aStepsSlugs;
	}

	/**
	 * @param string $sStep
	 * @return array
	 */
	protected function getExtraRenderData( $sStep ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getModCon();
		/** @var ICWP_WPSF_Processor_HackProtect $oProc */
		$oProc = $oFO->getProcessor();

		$aAdditional = array();

		$sCurrentWiz = $this->getCurrentWizard();

		if ( $sCurrentWiz == 'ufc' ) {
			switch ( $sStep ) {

				case 'exclusions':
					$aFiles = $oFO->getUfcFileExclusions();
					$aAdditional[ 'data' ] = array(
						'files' => array(
							'count' => count( $aFiles ),
							'has'   => !empty( $aFiles ),
							'list'  => implode( "\n", $aFiles ),
						)
					);
					break;

				case 'scanresult':
					$aFiles = $this->cleanAbsPath( $oProc->getSubProcessorFileCleanerScan()->discoverFiles() );

					$aAdditional[ 'data' ] = array(
						'files' => array(
							'count' => count( $aFiles ),
							'has'   => !empty( $aFiles ),
							'list'  => $aFiles,
						)
					);
					break;
			}
		}
		else if ( $sCurrentWiz == 'wcf' ) {

			switch ( $sStep ) {
				case 'scanresult':
					$aFiles = $oProc->getSubProcessorChecksumScan()->doChecksumScan( false );
					$aChecksum = $this->cleanAbsPath( $aFiles[ 'checksum_mismatch' ] );
					$aMissing = $this->cleanAbsPath( $aFiles[ 'missing' ] );

					$aAdditional[ 'data' ] = array(
						'files' => array(
							'count'    => count( $aChecksum ) + count( $aMissing ),
							'has'      => !empty( $aChecksum ) || !empty( $aMissing ),
							'checksum' => array(
								'count' => count( $aChecksum ),
								'has'   => !empty( $aChecksum ),
								'list'  => $aChecksum,
							),
							'missing'  => array(
								'count' => count( $aMissing ),
								'has'   => !empty( $aMissing ),
								'list'  => $aMissing,
							)
						)
					);
					break;
			}
		}

		return $aAdditional;
	}

	/**
	 * @param string[] $aFilePaths
	 * @return  string[]
	 */
	private function cleanAbsPath( $aFilePaths ) {
		return array_map(
			function ( $sFile ) {
				return str_replace( ABSPATH, '', $sFile );
			},
			$aFilePaths
		);
	}
}