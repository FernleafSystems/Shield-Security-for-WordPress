<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin_Wizard', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wizard.php' );

/**
 * @uses php 5.4+
 * Class ICWP_WPSF_Processor_Plugin_SetupWizard
 */
class ICWP_WPSF_Processor_LoginProtect_Wizard extends ICWP_WPSF_Processor_Base_Wizard {

	/**
	 * @return string[]
	 */
	protected function getSupportedWizards() {
		return array( 'mfa' );
	}

	public function ajaxWizardProcessStepSubmit() {
		$oDP = $this->loadDP();

		$this->loadAutoload(); // for Response
		switch ( $oDP->post( 'wizard-step' ) ) {

			case 'optin':
				$oResponse = $this->wizardOptin();
				break;

			default:
				$oResponse = new \FernleafSystems\Utilities\Response();
				$oResponse->setSuccessful( false )
						  ->setMessageText( _wpsf__( 'Unknown request' ) );
				break;
		}

		$sMessage = $oResponse->getMessageText();
		if ( $oResponse->successful() ) {
			$sMessage .= '<br />'.sprintf( _wpsf__( 'Please click %s to continue.' ), _wpsf__( 'Next' ) );
		}
		else {
			$sMessage = sprintf( '%s: %s', _wpsf__( 'Error' ), $sMessage );
		}

		$aData = $oResponse->getData();
		$aData[ 'message' ] = $sMessage;

		$this->getFeature()
			 ->sendAjaxResponse( $oResponse->successful(), $aData );
	}

	/**
	 * @return string[]
	 */
	protected function determineWizardSteps() {

		switch ( $this->getCurrentWizard() ) {
			case 'mfa':
				$aSteps = $this->determineWizardSteps_Mfa();
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
	private function determineWizardSteps_Mfa() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();

		$aStepsSlugs = array( 'mfa_start' );
		$aStepsSlugs[] = 'mfa_finished';
		return $aStepsSlugs;
	}

	/**
	 * @param string $sSlug
	 * @return array
	 */
	protected function getRenderDataForStep( $sSlug ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		$oConn = $this->getController();

		$aData = array(
			'flags' => array(
				'is_premium' => $oFO->isPremium()
			),
			'hrefs' => array(
				'dashboard' => $oFO->getFeatureAdminPageUrl(),
				'gopro'     => 'http://icwp.io/ap',
			),
			'imgs'  => array(),
		);

		$aAdd = array();

		switch ( $sSlug ) {
			case 'license':
				break;
			case 'import_options':
				$aAdd = array(
					'hrefs' => array(
						'blog_importexport' => 'http://icwp.io/av'
					),
					'imgs'  => array(
						'shieldnetworkmini' => $oConn->getPluginUrl_Image( 'shield/shieldnetworkmini.png' ),
					)
				);
				break;
			default:
				break;
		}

		return $this->loadDP()->mergeArraysRecursive( $aData, $aAdd );
	}

	/**
	 * @return array[]
	 */
	protected function getAllDefinedSteps() {
		return array(
			'mfa_start'    => array(
				'title'             => _wpsf__( 'Start Import' ),
				'content'           => '',
				'restricted_access' => false
			),
			'mfa_finished' => array(
				'title'             => _wpsf__( 'Import Finished' ),
				'content'           => '',
				'restricted_access' => false
			),
		);
	}
}