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
		return array( 'ccs', 'fcs', 'wpvuln' );
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
			case 'ccs':
				$aSteps = $this->determineWizardSteps_Ccs();
				break;
			case 'fcs':
				$aSteps = $this->determineWizardSteps_Fcs();
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
	private function determineWizardSteps_Ccs() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		$aStepsSlugs = array(
			'ccs_start',
			'ccs_scanresult',
			'ccs_finished'
		);
		return $aStepsSlugs;
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Fcs() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		$aStepsSlugs = array(
			'fcs_start',
			'fcs_exclusions',
			'fcs_scanresult',
			'fcs_finished'
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

			case 'fcs_exclusions':
				$aFiles = $oFO->getUfcFileExclusions();
				$aAdd[ 'data' ] = array(
					'files' => array(
						'count' => count( $aFiles ),
						'has'   => !empty( $aFiles ),
						'list'  => implode( "\n", $aFiles ),
					)
				);
				break;

			case 'fcs_scanresult':
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

			case 'mfa_authga':
				$oUser = $this->loadWpUsers()->getCurrentWpUser();
				/** @var ICWP_WPSF_Processor_LoginProtect $oProc */
				$oProc = $oFO->getProcessor();
				$oProcGa = $oProc->getProcessorLoginIntent()
								 ->getProcessorGoogleAuthenticator();
				$sGaUrl = $oProcGa->getGaRegisterChartUrl( $oUser );
				$aAdd = array(
					'data'  => array(
						'name'       => $oUser->first_name,
						'user_email' => $oUser->user_email
					),
					'hrefs' => array(
						'ga_chart' => $sGaUrl,
					),
					'flags' => array(
						'has_ga' => $oProcGa->getCurrentUserHasValidatedProfile(),
					)
				);
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
			'fcs_start'      => array(
				'title'             => _wpsf__( 'Start File Cleaner' ),
				'restricted_access' => false
			),
			'fcs_exclusions' => array(
				'title' => _wpsf__( 'Exclude Files' ),
			),
			'fcs_scanresult' => array(
				'title' => _wpsf__( 'Scan Result' ),
			),
			'fcs_finished'   => array(
				'title'             => _wpsf__( 'Finished: File Cleaner' ),
				'restricted_access' => false
			),
		);
	}
}