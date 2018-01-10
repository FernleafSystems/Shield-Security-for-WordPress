<?php

if ( class_exists( 'ICWP_WPSF_Processor_Base_Wizard', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

/**
 * @uses php 5.4+
 * Class ICWP_WPSF_Processor_Base_SetupWizard
 */
abstract class ICWP_WPSF_Processor_Base_Wizard extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var string
	 */
	private $sCurrentWizard;

	/**
	 */
	public function run() {
		if ( $this->hasYetToRun() ) {
			add_action( 'init', array( $this, 'onWpInit' ), 0 );
		}
	}

	public function onWpInit() {
		if ( $this->loadWpUsers()->isUserAdmin() ) {
			$sWizard = (string)$this->loadDP()->query( 'wizard', '' );
			if ( $this->isSupportedWizard( $sWizard ) ) {
				$this->loadWizard( $sWizard );
			}
		}
	}

	/**
	 * @uses echo()
	 * @param string $sWizard
	 */
	protected function loadWizard( $sWizard ) {
		try {
			$sContent = $this->setCurrentWizard( $sWizard )
							 ->renderWizard();
		}
		catch ( Exception $oE ) {
			$sContent = $oE->getMessage();
		}
		echo $sContent;
		die();
	}

	/**
	 * Ensure to only ever process supported wizards
	 */
	public function ajaxWizardRenderStep() {
		$oDP = $this->loadDP();

		$sWizard = $oDP->post( 'wizard_slug' );
		if ( $this->isSupportedWizard( $sWizard ) ) {

			$this->setCurrentWizard( $sWizard );
			$aNextStep = $this->getWizardNextStep( $oDP->post( 'wizard_steps' ), $oDP->post( 'current_index' ) );
			$this->getFeature()
				 ->sendAjaxResponse(
					 true,
					 array( 'next_step' => $aNextStep )
				 );
		}
	}

	/**
	 * @param string $sSlug
	 * @return bool
	 */
	protected function isSupportedWizard( $sSlug ) {
		return in_array( $sSlug, $this->getSupportedWizards() );
	}

	/**
	 * @return string[] the array of wizard slugs supported
	 */
	abstract protected function getSupportedWizards();

	public function ajaxWizardProcessStepSubmit() {
		$this->loadAutoload(); // for Response
		$oResponse = $this->processWizardStep( $this->loadDP()->post( 'wizard-step' ) );
		if ( !empty( $oResponse ) ) {
			$this->sendWizardResponse( $oResponse );
		}
	}

	/**
	 * @param string $sStep
	 * @return \FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( $sStep ) {
		switch ( $sStep ) {
			default:
				$oResponse = null; // we don't process any steps we don't recognise.
				break;
		}
		return $oResponse;
	}

	/**
	 * @param \FernleafSystems\Utilities\Response $oResponse
	 */
	protected function sendWizardResponse( $oResponse ) {

		$sMessage = $oResponse->getMessageText();
		if ( $oResponse->successful() ) {
			$sMessage .= '<br />'.sprintf( 'Please click %s to continue.', __( 'Next Step' ) );
		}
		else {
			$sMessage = sprintf( '%s: %s', __( 'Error' ), $sMessage );
		}

		$aData = $oResponse->getData();
		$aData[ 'message' ] = $sMessage;
		$oResponse->setData( $aData );

		$this->getFeature()
			 ->sendAjaxResponse( $oResponse->successful(), $aData );
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	protected function renderWizard() {
		return $this->loadRenderer( $this->getController()->getPath_Templates() )
					->setTemplate( 'pages/wizard.twig' )
					->setRenderVars( $this->getDisplayData() )
					->setTemplateEngineTwig()
					->render();
	}

	/**
	 * @return array
	 */
	protected function getDisplayData() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		$oCon = $this->getController();

		return array(
			'strings' => array(
				'page_title' => $this->getPageTitle()
			),
			'data'    => array(
				'wizard_slug'       => $this->getCurrentWizard(),
				'wizard_steps'      => json_encode( $this->determineWizardSteps() ),
				'wizard_first_step' => json_encode( $this->getWizardFirstStep() ),
			),
			'hrefs'   => array(
				'form_action'      => $this->loadDataProcessor()->getRequestUri(),
				'css_bootstrap'    => $oCon->getPluginUrl_Css( 'bootstrap3.min.css' ),
				'css_pages'        => $oCon->getPluginUrl_Css( 'pages.css' ),
				'css_steps'        => $oCon->getPluginUrl_Css( 'jquery.steps.css' ),
				'css_fancybox'     => $oCon->getPluginUrl_Css( 'jquery.fancybox.min.css' ),
				'css_globalplugin' => $oCon->getPluginUrl_Css( 'global-plugin.css' ),
				'css_wizard'       => $oCon->getPluginUrl_Css( 'wizard.css' ),
				'js_jquery'        => $this->loadWpIncludes()->getUrl_Jquery(),
				'js_bootstrap'     => $oCon->getPluginUrl_Js( 'bootstrap3.min.js' ),
				'js_fancybox'      => $oCon->getPluginUrl_Js( 'jquery.fancybox.min.js' ),
				'js_globalplugin'  => $oCon->getPluginUrl_Js( 'global-plugin.js' ),
				'js_steps'         => $oCon->getPluginUrl_Js( 'jquery.steps.min.js' ),
				'js_wizard'        => $oCon->getPluginUrl_Js( 'wizard.js' ),
				'plugin_banner'    => $oCon->getPluginUrl_Image( 'pluginbanner_1500x500.png' ),
				'favicon'          => $oCon->getPluginUrl_Image( 'pluginlogo_24x24.png' ),
				'dashboard'        => $oFO->getFeatureAdminPageUrl(),
			),
			'ajax'    => array(
				'content'       => $oFO->getBaseAjaxActionRenderData( 'WizardProcessStepSubmit' ),
				'steps'         => $oFO->getBaseAjaxActionRenderData( 'WizardRenderStep' ),
				'steps_as_json' => $oFO->getBaseAjaxActionRenderData( 'WizardRenderStep', true ),
			)
		);
	}

	/**
	 * @return string
	 */
	protected function getPageTitle() {
		return sprintf( _wpsf__( '%s Wizard' ), $this->getController()->getHumanName() );
	}

	/**
	 * @return string[]
	 */
	protected function determineWizardSteps() {
		// Special case: user doesn't meet even the basic plugin admin permissions
		if ( !$this->getController()->getMeetsBasePermissions() ) {
			return array( 'no_access' );
		}

		switch ( $this->getCurrentWizard() ) {
			default:
				$aSteps = array();
				break;
		}
		return $aSteps;
	}

	/**
	 * @return array
	 */
	protected function getWizardFirstStep() {
		return $this->getWizardNextStep( $this->determineWizardSteps(), -1 );
	}

	/**
	 * @param array $aAllSteps
	 * @param int   $nCurrentStep
	 * @return array
	 */
	protected function getWizardNextStep( $aAllSteps, $nCurrentStep ) {

		// The assumption here is that the step data exists!
		$aStepData = $this->getWizardStepsDefinition()[ $aAllSteps[ $nCurrentStep + 1 ] ];

		$bRestrictedAccess = !isset( $aStepData[ 'restricted_access' ] ) || $aStepData[ 'restricted_access' ];
		try {
			if ( !$bRestrictedAccess || $this->getController()->getHasPermissionToManage() ) {
				$aData = $this->getRenderDataForStep( $aStepData[ 'slug' ] );
				$aStepData[ 'content' ] = $this->renderWizardStep( $aStepData[ 'slug' ], $aData );
			}
			else {
				$aStepData[ 'content' ] = $this->renderSecurityAdminVerifyWizardStep( $nCurrentStep );
			}
		}
		catch ( Exception $oE ) {
			$aStepData[ 'content' ] = 'Content could not be displayed due to error: '.$oE->getMessage();
		}

		return $aStepData;
	}

	/**
	 * @param int $nIndex
	 * @return string
	 * @throws Exception
	 */
	protected function renderSecurityAdminVerifyWizardStep( $nIndex ) {
		return $this->renderWizardStep( 'common/admin_access_restriction_verify', array( 'current_index' => $nIndex ) );
	}

	/**
	 * @param string $sStep
	 * @return array
	 */
	protected function getRenderDataForStep( $sStep ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();

		$aData = array(
			'flags' => array(
				'is_premium' => $oFO->isPremium()
			),
			'hrefs' => array(
				'dashboard' => $oFO->getFeatureAdminPageUrl(),
				'gopro'     => 'http://icwp.io/ap',
			),
			'imgs'  => array(),
			'data'  => array(),
		);

		$aAdd = array();
		switch ( $sStep ) {
			case 'no_access':
				break;
			default:
				break;
		}

		return $this->loadDP()->mergeArraysRecursive( $aData, $aAdd );
	}

	/**
	 * @param string $sSlug
	 * @param array  $aRenderData
	 * @return string
	 * @throws Exception
	 */
	protected function renderWizardStep( $sSlug, $aRenderData = array() ) {
		if ( strpos( $sSlug, '/' ) === false ) {
			$sBase = ( $sSlug == 'no_access' ) ? 'common' : $this->getCurrentWizard();
			$sSlug = sprintf( '%s/%s', $sBase, $sSlug );
		}
		return $this->loadRenderer( $this->getController()->getPath_Templates() )
					->setTemplate( sprintf( 'wizard/slides/%s.twig', $sSlug ) )
					->setRenderVars( $aRenderData )
					->setTemplateEngineTwig()
					->render();
	}

	/**
	 * Overwrite to supply all the possible steps
	 * @return array[]
	 */
	protected function getAllDefinedSteps() {
		return array();
	}

	/**
	 * @return array[]
	 */
	private function getWizardStepsDefinition() {
		$aNoAccess = array(
			'no_access' => array(
				'title'             => _wpsf__( 'No Access' ),
				'restricted_access' => false
			)
		);
		$aSteps = array_merge( $this->getAllDefinedSteps(), $aNoAccess );
		foreach ( $aSteps as $sSlug => $aStep ) {
			$aSteps[ $sSlug ][ 'slug' ] = $sSlug;
			$aSteps[ $sSlug ][ 'content' ] = '';
		}
		return $aSteps;
	}

	/**
	 * @return string
	 */
	public function getCurrentWizard() {
		return $this->sCurrentWizard;
	}

	/**
	 * @param string $sCurrentWizard
	 * @return $this
	 */
	public function setCurrentWizard( $sCurrentWizard ) {
		$this->sCurrentWizard = $sCurrentWizard;
		return $this;
	}
}