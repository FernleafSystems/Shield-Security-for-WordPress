<?php

if ( class_exists( 'ICWP_WPSF_Wizard_Base', false ) ) {
	return;
}

/**
 * @uses php 5.4+
 * Class ICWP_WPSF_Wizard_Base
 */
abstract class ICWP_WPSF_Wizard_Base extends ICWP_WPSF_Foundation {

	/**
	 * @var string
	 */
	private $sCurrentWizard;

	/**
	 * @var ICWP_WPSF_FeatureHandler_Base
	 */
	protected $oModule;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Base $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Base $oFeatureOptions ) {
		$this->oModule = $oFeatureOptions;
	}

	/**
	 */
	public function init() {
		add_action( 'wp_loaded', array( $this, 'onWpLoaded' ), 0 );
	}

	/**
	 * Ensure to only ever process supported wizards
	 */
	public function ajaxWizardRenderStep() {
		$oDP = $this->loadDP();

		try {
			$this->setCurrentWizard( $oDP->post( 'wizard_slug' ) );
			if ( $this->getCurrentUserCan() ) {
				$aNextStep = $this->getNextStep( $oDP->post( 'wizard_steps' ), $oDP->post( 'current_index' ) );
				$this->getModCon()
					 ->sendAjaxResponse(
						 true,
						 array( 'next_step' => $aNextStep )
					 );
			}
			else {
				$this->loadWp()
					 ->wpDie( 'Please login to run this wizard.' );
			}
		}
		catch ( Exception $oE ) {
		}
	}

	public function onWpLoaded() {
		$sWizard = $this->loadDP()->query( 'wizard' );
		try {
			$this->setCurrentWizard( $sWizard );
			if ( $this->getCurrentUserCan() ) {
				$this->loadWizard();
			}
			else {
				$this->loadWp()
					 ->wpDie( 'Please login to run this wizard.' );
			}
		}
		catch ( Exception $oE ) {
			if ( $sWizard == 'landing' ) {
				$this->loadWizardLanding();
			}
		}
	}

	/**
	 * @uses echo()
	 */
	protected function loadWizard() {
		try {
			$sContent = $this->renderWizard();
		}
		catch ( Exception $oE ) {
			$sContent = $oE->getMessage();
		}
		echo $sContent;
		die();
	}

	/**
	 * @uses echo()
	 */
	protected function loadWizardLanding() {
		try {
			$sContent = $this->loadRenderer( $this->getModCon()->getController()->getPath_Templates() )
							 ->setTemplate( 'wizard/pages/landing.twig' )
							 ->setRenderVars( $this->getRenderData_PageWizardLanding() )
							 ->setTemplateEngineTwig()
							 ->render();
		}
		catch ( Exception $oE ) {
			$sContent = $oE->getMessage();
		}
		echo $sContent;
		die();
	}

	/**
	 * @param string $sSlug
	 * @return bool
	 */
	protected function isSupportedWizard( $sSlug ) {
		return in_array( $sSlug, $this->getSupportedWizards() );
	}

	/**
	 * @param string $sPerm
	 * @return bool
	 */
	protected function getCurrentUserCan( $sPerm = null ) {
		if ( empty( $sPerm ) ) {
			$sPerm = $this->getWizardProperty( 'min_user_permissions' );
		}
		if ( empty( $sPerm ) ) {
			$sPerm = 'manage_options';
		}
		return $sPerm == 'none' || current_user_can( $sPerm );
	}

	/**
	 * @return string[] the array of wizard slugs supported
	 */
	protected function getSupportedWizards() {
		return array_keys( $this->getModCon()->getWizardDefinitions() );
	}

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

		$this->getModCon()
			 ->sendAjaxResponse( $oResponse->successful(), $aData );
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	protected function renderWizard() {
		return $this->loadRenderer( $this->getModCon()->getController()->getPath_Templates() )
					->setTemplate( 'pages/wizard.twig' )
					->setRenderVars( $this->getRenderData_PageWizard() )
					->setTemplateEngineTwig()
					->render();
	}

	protected function getRenderData_PageWizardLanding() {
		/** @var ICWP_WPSF_FeatureHandler_Base $oFO */
		$oFO = $this->getModCon();

		$aWizards = $oFO->getWizardDefinitions();
		foreach ( $aWizards as $sKey => &$aWizard ) {
			$aWizard[ 'has_perm' ] = $this->getCurrentUserCan( $aWizard[ 'min_user_permissions' ] );
			$aWizard[ 'url' ] = $oFO->getUrl_Wizard( $sKey );
			$aWizard[ 'has_premium' ] = isset( $aWizard[ 'has_premium' ] ) && $aWizard[ 'has_premium' ];
		}

		return $this->loadDP()->mergeArraysRecursive(
			$this->getRenderData_TwigPageBase(),
			array(
				'strings' => array(
					'page_title'   => 'Select Your Wizard',
					'premium_note' => 'Note: This uses features only available to Pro-licensed installations.'
				),
				'data'    => array(
					'wizard_count' => count( $aWizards ),
					'wizards'      => $aWizards
				),
				'hrefs'   => array(
					'dashboard' => $oFO->getUrl_AdminPage(),
				),
				'ajax'    => array(
					'content'       => $oFO->getBaseAjaxActionRenderData( 'WizardProcessStepSubmit' ),
					'steps'         => $oFO->getBaseAjaxActionRenderData( 'WizardRenderStep' ),
					'steps_as_json' => $oFO->getBaseAjaxActionRenderData( 'WizardRenderStep', true ),
				)
			)
		);
	}

	/**
	 * TODO: Abstract and move elsewhere - it's here because Wizards on the only consumer of twig templates
	 * @return array
	 */
	protected function getRenderData_TwigPageBase() {
		$oCon = $this->getModCon()->getController();
		return array(
			'strings' => array(
				'page_title' => 'Twig Page'
			),
			'data'    => array(),
			'hrefs'   => array(
				'form_action'      => $this->loadDP()->getRequestUri(),
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
				'plugin_banner'    => $oCon->getPluginUrl_Image( 'banner-1500x500-transparent.png' ),
				'favicon'          => $oCon->getPluginUrl_Image( 'pluginlogo_24x24.png' ),
			),
			'ajax'    => array()
		);
	}

	/**
	 * @return array
	 */
	protected function getRenderData_PageWizard() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getModCon();
		return $this->loadDP()->mergeArraysRecursive(
			$this->getRenderData_TwigPageBase(),
			array(
				'strings' => array(
					'page_title' => $this->getPageTitle()
				),
				'data'    => array(
					'wizard_slug'       => $this->getWizardSlug(),
					'wizard_steps'      => json_encode( $this->buildSteps() ),
					'wizard_first_step' => json_encode( $this->getWizardFirstStep() ),
				),
				'hrefs'   => array(
					'dashboard' => $oFO->getUrl_AdminPage(),
				),
				'ajax'    => array(
					'content'       => $oFO->getBaseAjaxActionRenderData( 'WizardProcessStepSubmit' ),
					'steps'         => $oFO->getBaseAjaxActionRenderData( 'WizardRenderStep' ),
					'steps_as_json' => $oFO->getBaseAjaxActionRenderData( 'WizardRenderStep', true ),
				)
			)
		);
	}

	/**
	 * @return string
	 */
	protected function getPageTitle() {
		return sprintf( _wpsf__( '%s Wizard' ), $this->getModCon()->getController()->getHumanName() );
	}

	/**
	 * @return string[]
	 */
	protected function buildSteps() {
		return $this->getCurrentUserCan() ? $this->determineWizardSteps() : array( 'no_access' );
	}

	/**
	 * @throws Exception
	 */
	protected function determineWizardSteps() {
		throw new Exception( sprintf( 'Could not determine wizard steps for current wizard: %s', $this->getWizardSlug() ) );
	}

	/**
	 * @return array
	 */
	protected function getWizardFirstStep() {
		return $this->getNextStep( $this->buildSteps(), -1 );
	}

	/**
	 * @param array $aStepsInThisInstance
	 * @param int   $nCurrentStep
	 * @return array
	 */
	protected function getNextStep( $aStepsInThisInstance, $nCurrentStep ) {

		// The assumption here is that the step data exists!
		$sNextStepKey = $aStepsInThisInstance[ $nCurrentStep + 1 ];
		$aStepData = $this->getStepsDefinition()[ $sNextStepKey ];

		$bRestrictedAccess = !isset( $aStepData[ 'restricted_access' ] ) || $aStepData[ 'restricted_access' ];
		try {
			if ( !$bRestrictedAccess || $this->getCurrentUserCan() ) {
				$aData = $this->getRenderData_Slide( $aStepData[ 'slug' ] );
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
	protected function getRenderData_Slide( $sStep ) {
		return $this->loadDP()->mergeArraysRecursive(
			$this->getRenderData_SlideBase(),
			$this->getRenderData_SlideExtra( $sStep )
		);
	}

	/**
	 * @return array
	 */
	protected function getRenderData_SlideBase() {
		$oFO = $this->getModCon();
		return array(
			'flags' => array(
				'is_premium' => $oFO->isPremium()
			),
			'hrefs' => array(
				'dashboard' => $oFO->getUrl_AdminPage(),
				'gopro'     => 'http://icwp.io/ap',
			),
			'imgs'  => array(),
			'data'  => array(),
		);
	}

	/**
	 * @param string $sStep
	 * @return array
	 */
	abstract protected function getRenderData_SlideExtra( $sStep );

	/**
	 * @param string $sSlug
	 * @param array  $aRenderData
	 * @return string
	 * @throws Exception
	 */
	protected function renderWizardStep( $sSlug, $aRenderData = array() ) {
		if ( strpos( $sSlug, '/' ) === false ) {
			// first trim the prefixed wizard slug, e.g. ufc_
			$sCurrentWizard = $this->getWizardSlug();
			$sSlug = preg_replace( sprintf( '#^%s_#', $sCurrentWizard ), '', $sSlug );

			$sBase = ( $sSlug == 'no_access' ) ? 'common' : $sCurrentWizard;
			$sSlug = sprintf( '%s/%s', $sBase, $sSlug );
		}
		return $this->loadRenderer( $this->getModCon()->getController()->getPath_Templates() )
					->setTemplate( sprintf( 'wizard/slides/%s.twig', $sSlug ) )
					->setRenderVars( $aRenderData )
					->setTemplateEngineTwig()
					->render();
	}

	/**
	 * @return array[]
	 */
	protected function getAllDefinedSteps() {
		return $this->getWizard()[ 'steps' ];
	}

	/**
	 * @return array[]
	 */
	private function getStepsDefinition() {
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
	public function getWizardSlug() {
		return $this->sCurrentWizard;
	}

	/**
	 * @return array
	 */
	public function getWizard() {
		return $this->getModCon()->getWizardDefinitions()[ $this->getWizardSlug() ];
	}

	/**
	 * @param string $sKey
	 * @return array
	 */
	public function getWizardProperty( $sKey ) {
		$aW = $this->getWizard();
		return isset( $aW[ $sKey ] ) ? $aW[ $sKey ] : null;
	}

	/**
	 * @param string $sCurrentWizard
	 * @return $this
	 * @throws Exception
	 */
	public function setCurrentWizard( $sCurrentWizard ) {
		if ( empty( $sCurrentWizard ) || !$this->isSupportedWizard( $sCurrentWizard ) ) {
			throw new Exception( 'Not a supported wizard.' );
		}
		$this->sCurrentWizard = $sCurrentWizard;
		return $this;
	}

	/**
	 * @return ICWP_WPSF_FeatureHandler_Base
	 */
	protected function getModCon() {
		return $this->oModule;
	}

	/**
	 * @return ICWP_WPSF_Plugin_Controller
	 */
	protected function getPluginCon() {
		return $this->getModCon()->getConn();
	}
}