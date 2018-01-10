<?php

if ( class_exists( 'ICWP_WPSF_Processor_HackProtect_Wizard', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wizard.php' );

/**
 * Class ICWP_WPSF_Processor_LoginProtect_Wizard
 */
class ICWP_WPSF_Processor_HackProtect_Wizard extends ICWP_WPSF_Processor_Base_Wizard {

	/**
	 * @return string[]
	 */
	protected function getSupportedWizards() {
		return array( 'cfs', 'ufc', 'wpvuln' );
	}

	/**
	 * @return string
	 */
	protected function getPageTitle() {
		return sprintf( _wpsf__( '%s Hack Protect Wizard' ), $this->getController()->getHumanName() );
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
		$oFO = $this->getFeature();
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
		$oFO = $this->getFeature();

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
	private function processMultiSelect() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		$bEnabledMulti = $this->loadDP()->post( 'multiselect' ) === 'Y';
		$oFO->setIsChainedAuth( $bEnabledMulti );
		$sMessage = sprintf( _wpsf__( 'Multi-Factor Authentication was %s for the site.' ),
			$bEnabledMulti ? _wpsf__( 'enabled' ) : _wpsf__( 'disabled' )
		);

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( true )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return string[]
	 */
	protected function determineWizardSteps() {

		switch ( $this->getCurrentWizard() ) {
			case 'cfs':
				$aSteps = $this->determineWizardSteps_Cfs();
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
	private function determineWizardSteps_Cfs() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		$aStepsSlugs = array(
			'cfs_start',
			'cfs_scanresult',
			'cfs_finished'
		);
		return $aStepsSlugs;
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Ufc() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		$aStepsSlugs = array(
			'ufc_start',
			'ufc_exclusions',
			'ufc_scanresult',
			'ufc_finished'
		);
		return $aStepsSlugs;
	}

	/**
	 * @param string $sStep
	 * @return array
	 */
	protected function getRenderDataForStep( $sStep ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getFeature();
		/** @var ICWP_WPSF_Processor_HackProtect $oProc */
		$oProc = $oFO->getProcessor();

		$aAdd = array();

		switch ( $sStep ) {

			case 'ufc_exclusions':
				$aFiles = $oFO->getUfcFileExclusions();
				$aAdd[ 'data' ] = array(
					'files' => array(
						'count' => count( $aFiles ),
						'has'   => !empty( $aFiles ),
						'list'  => implode( "\n", $aFiles ),
					)
				);
				break;

			case 'ufc_scanresult':
				$aFiles = array_map(
					function ( $sFile ) {
						return str_replace( ABSPATH, '', $sFile );
					},
					$oProc->getSubProcessorFileCleanerScan()->discoverFiles()
				);

				$aAdd[ 'data' ] = array(
					'files' => array(
						'count' => count( $aFiles ),
						'has'   => !empty( $aFiles ),
						'list'  => $aFiles,
					)
				);
				break;

			case 'cfs_scanresult':
				$aFiles = array_map(
					function ( $sFile ) {
						return str_replace( ABSPATH, '', $sFile );
					},
					$oProc->getSubProcessorFileCleanerScan()->discoverFiles()
				);

				$aAdd[ 'data' ] = array(
					'files' => array(
						'count' => count( $aFiles ),
						'has'   => !empty( $aFiles ),
						'list'  => $aFiles,
					)
				);
				break;

			case 'cfs_start':
				break;

			case 'mfa_multiselect':
				$aAdd = array(
					'flags' => array(
						'has_multiselect' => $oFO->isChainedAuth(),
					)
				);
				break;

			default:
				break;
		}

		return $this->loadDP()->mergeArraysRecursive( parent::getRenderDataForStep( $sStep ), $aAdd );
	}

	/**
	 * @return array[]
	 */
	protected function getAllDefinedSteps() {
		return array(
			'ufc_start'      => array(
				'title'             => _wpsf__( 'Start File Cleaner' ),
				'restricted_access' => false
			),
			'ufc_exclusions' => array(
				'title' => _wpsf__( 'Exclude Files' ),
			),
			'ufc_scanresult' => array(
				'title' => _wpsf__( 'Scan Result' ),
			),
			'ufc_finished'   => array(
				'title'             => _wpsf__( 'Finished: File Cleaner' ),
				'restricted_access' => false
			),
		);
	}
}