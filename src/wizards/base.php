<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @uses php 5.4+
 * Class ICWP_WPSF_Wizard_Base
 */
abstract class ICWP_WPSF_Wizard_Base {

	use Shield\Modules\ModConsumer;

	/**
	 * @var string
	 */
	private $sCurrentWizard;

	public function init() {
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ], 0 );
	}

	/**
	 * Ensure to only ever process supported wizards
	 */
	public function ajaxExec_WizRenderStep() {
		$oReq = Services::Request();

		$aResponse = [
			'success'   => false,
			'next_step' => [],
		];

		try {
			$this->setCurrentWizard( $oReq->post( 'wizard_slug' ) );
			if ( $this->getUserCan() ) {
				$aNextStep = $this->buildNextStep(
					$oReq->post( 'wizard_steps' ),
					(int)$oReq->post( 'current_index' )
				);
				$aResponse[ 'success' ] = true;
				$aResponse[ 'next_step' ] = $aNextStep;
			}
			else {
				$aResponse[ 'message' ] = 'Please login to run this wizard.';
			}
		}
		catch ( Exception $oE ) {
		}

		return $aResponse;
	}

	/**
	 * TODO: does not honour 'min_user_permissions' from the wizard definition
	 */
	public function onWpLoaded() {
		$sWizard = Services::Request()->query( 'wizard' );
		try {
			$this->setCurrentWizard( $sWizard );

			$sDieMessage = 'Not Permitted';
			if ( $this->getUserCan() ) {
				$this->loadWizard();
			}
			else {
				$sDieMessage = 'Please login to run this wizard';
			}

			Services::WpGeneral()->wpDie( $sDieMessage );
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
	 * @return string
	 */
	public function renderWizardLandingPage() {
		try {
			$sContent = $this->getMod()
							 ->renderTemplate(
								 'wizard/pages/landing.twig',
								 $this->getRenderData_PageWizardLanding(),
								 true
							 );
		}
		catch ( Exception $oE ) {
			$sContent = $oE->getMessage();
		}
		return $sContent;
	}

	/**
	 * @return string
	 */
	public function renderWizardLandingSnippet() {
		try {
			$sContent = $this->getMod()
							 ->renderTemplate(
								 'wizard/snippets/wizard_landing.twig',
								 $this->getRenderData_PageWizardLanding(),
								 true
							 );
		}
		catch ( Exception $oE ) {
			$sContent = $oE->getMessage();
		}
		return $sContent;
	}

	/**
	 * @uses echo()
	 */
	protected function loadWizardLanding() {
		echo $this->renderWizardLandingPage();
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
	protected function getUserCan( $sPerm = null ) {
		if ( empty( $sPerm ) ) {
			$sPerm = 'manage_options';
		}
		return $sPerm == 'none' || current_user_can( $sPerm );
	}

	/**
	 * @param string $sSlide
	 * @return bool
	 */
	protected function getUserCanSlide( $sSlide ) {
		return $this->getUserCan();
	}

	/**
	 * @return string[] the array of wizard slugs supported
	 */
	protected function getSupportedWizards() {
		return array_keys( $this->getMod()->getWizardDefinitions() );
	}

	/**
	 * @return array
	 */
	public function ajaxExec_WizProcessStep() {
		$oResponse = $this->processWizardStep( Services::Request()->post( 'wizard-step' ) );
		if ( !empty( $oResponse ) ) {
			$this->buildWizardResponse( $oResponse );
		}

		$aData = $oResponse->getData();
		$aData[ 'success' ] = $oResponse->successful();
		return $aData;
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
	 * @return \FernleafSystems\Utilities\Response
	 */
	protected function buildWizardResponse( $oResponse ) {

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
		return $oResponse;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	protected function renderWizard() {
		remove_all_actions( 'wp_footer' ); // FIX: nextgen gallery forces this to run.
		return $this->getMod()
					->renderTemplate( 'wizard/pages/wizard.twig', $this->getRenderData_PageWizard(), true );
	}

	/**
	 * @return array[]
	 */
	protected function getModuleWizardsForRender() {
		/** @var ICWP_WPSF_FeatureHandler_Base $mod */
		$mod = $this->getMod();
		$aWizards = $mod->getWizardDefinitions();
		foreach ( $aWizards as $sKey => &$aWizard ) {
			$aWizard[ 'has_perm' ] = empty( $aWizard[ 'min_user_permissions' ] ) || $this->getUserCan( $aWizard[ 'min_user_permissions' ] );
			$aWizard[ 'url' ] = $mod->getUrl_Wizard( $sKey );
			$aWizard[ 'has_premium' ] = isset( $aWizard[ 'has_premium' ] ) && $aWizard[ 'has_premium' ];
			$aWizard[ 'available' ] = $this->getWizardAvailability( $sKey );
		}
		return $aWizards;
	}

	/**
	 * Override this to provide custom logic for wizard availability - e.g. isPremium() etc.
	 * @param string $sKey
	 * @return bool
	 */
	protected function getWizardAvailability( $sKey ) {
		return true;
	}

	/**
	 * @return array[]
	 */
	protected function getRenderData_PageWizardLanding() {
		/** @var ICWP_WPSF_FeatureHandler_Base $oMod */
		$oMod = $this->getMod();

		$aWizards = $this->getModuleWizardsForRender();

		return Services::DataManipulation()->mergeArraysRecursive(
			$oMod->getBaseDisplayData(),
			[
				'strings' => [
					'page_title'   => 'Select Your Wizard',
					'premium_note' => 'Note: This uses features only available to Pro-licensed installations.'
				],
				'data'    => [
					'mod_wizards_count' => count( $aWizards ),
					'mod_wizards'       => $aWizards
				],
				'hrefs'   => [
					'dashboard'   => $oMod->getUrl_AdminPage(),
					'goprofooter' => 'https://shsec.io/goprofooter',
				],
				'ajax'    => [
					'content'       => $oMod->getAjaxActionData( 'wiz_process_step' ),
					'steps'         => $oMod->getAjaxActionData( 'wiz_render_step' ),
					'steps_as_json' => $oMod->getAjaxActionData( 'wiz_render_step', true ),
				]
			]
		);
	}

	/**
	 * TODO: Abstract and move elsewhere - it's here because Wizards on the only consumer of twig templates
	 * @return array
	 */
	protected function getRenderData_TwigPageBase() {
		return $this->getMod()->getBaseDisplayData();
	}

	/**
	 * @return array
	 */
	protected function getRenderData_PageWizard() {
		$oCon = $this->getMod()->getCon();
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		return Services::DataManipulation()->mergeArraysRecursive(
			$oMod->getBaseDisplayData(),
			[
				'strings' => [
					'page_title'  => $this->getPageTitle(),
					'plugin_name' => $oCon->getHumanName()
				],
				'data'    => [
					'wizard_slug'       => $this->getWizardSlug(),
					'wizard_steps'      => json_encode( $this->buildSteps() ),
					'wizard_first_step' => json_encode( $this->getWizardFirstStep() ),
				],
				'hrefs'   => [
					'dashboard'   => $oMod->getUrl_AdminPage(),
					'goprofooter' => 'https://shsec.io/goprofooter',
				],
				'ajax'    => [
					'content'       => $oMod->getAjaxActionData( 'wiz_process_step' ),
					'steps'         => $oMod->getAjaxActionData( 'wiz_render_step' ),
					'steps_as_json' => $oMod->getAjaxActionData( 'wiz_render_step', true ),
				]
			]
		);
	}

	/**
	 * @return string
	 */
	protected function getPageTitle() {
		return sprintf( __( '%s Wizard', 'wp-simple-firewall' ), $this->getMod()->getCon()->getHumanName() );
	}

	/**
	 * @return string[]
	 */
	protected function buildSteps() {
		return $this->getUserCan() ? $this->determineWizardSteps() : [ 'no_access' ];
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
		return $this->buildNextStep( $this->buildSteps(), -1 );
	}

	protected function getNextStepDefinition( $aStepsInThisInstance, $nCurrentStep ) {
	}

	/**
	 * @param array $aStepsInThisInstance
	 * @param int   $nCurrentPos
	 * @return array
	 */
	protected function buildNextStep( $aStepsInThisInstance, $nCurrentPos ) {
		$aNextStepDef = $this->getNextStep( $aStepsInThisInstance, $nCurrentPos );

		try {
			$aNextStepDef[ 'content' ] = $this->renderWizardStep( $aNextStepDef[ 'slug' ] );
		}
		catch ( Exception $oE ) {
			$aNextStepDef[ 'content' ] = 'Content could not be displayed due to error: '.$oE->getMessage();
		}

		return $aNextStepDef;
	}

	/**
	 * @param array $aStepsInThisInstance
	 * @param int   $nCurrentPos
	 * @return array
	 */
	protected function getNextStep( $aStepsInThisInstance, $nCurrentPos ) {
		// The assumption here is that the step data exists!
		$sNextStepKey = $aStepsInThisInstance[ $nCurrentPos + 1 ];
		return $this->getStepsDefinition()[ $sNextStepKey ];
	}

	/**
	 * @param string $sStep
	 * @return array
	 */
	protected function getRenderData_Slide( $sStep ) {
		return Services::DataManipulation()->mergeArraysRecursive(
			$this->getRenderData_SlideBase(),
			$this->getRenderData_SlideExtra( $sStep )
		);
	}

	/**
	 * @return array
	 */
	protected function getRenderData_SlideBase() {
		$oMod = $this->getMod();
		$aWizards = $this->getModuleWizardsForRender();
		return [
			'strings' => $oMod->getStrings()->getDisplayStrings()
			,
			'flags'   => [
				'is_premium'        => $oMod->isPremium(),
				'has_other_wizards' => false
			],
			'hrefs'   => [
				'dashboard' => $oMod->getUrl_AdminPage(),
				'gopro'     => 'https://shsec.io/ap',
			],
			'imgs'    => [],
			'data'    => [
				'mod_wizards_count' => count( $aWizards ),
				'mod_wizards'       => $aWizards
			],
		];
	}

	/**
	 * @param string $sStep
	 * @return array
	 */
	protected function getRenderData_SlideExtra( $sStep ) {
		return [];
	}

	/**
	 * @param string $sSlug
	 * @return string
	 * @throws Exception
	 */
	protected function renderWizardStep( $sSlug ) {

		$sTemplateSlug = $sSlug;
		if ( strpos( $sSlug, '/' ) === false ) {
			$sBase = $this->isSlideCommon( $sSlug ) ? 'common' : $this->getWizardSlug();
			$sTemplateSlug = sprintf( '%s/%s', $sBase, $sSlug );
		}

		return $this->getMod()
					->renderTemplate(
						sprintf( 'wizard/slides/%s.twig', $sTemplateSlug ),
						$this->getRenderData_Slide( $sSlug ),
						true
					);
	}

	/**
	 * @param string $sSlideSlug
	 * @return bool
	 */
	protected function isSlideCommon( $sSlideSlug ) {
		return in_array( $sSlideSlug, [ 'no_access' ] );
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
	protected function getStepsDefinition() {
		$aNoAccess = [
			'no_access' => [
				'title' => __( 'No Access', 'wp-simple-firewall' ),
			]
		];
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
		return $this->getMod()->getWizardDefinitions()[ $this->getWizardSlug() ];
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
}