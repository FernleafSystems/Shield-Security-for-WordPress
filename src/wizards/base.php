<?php

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Services\Services;

abstract class ICWP_WPSF_Wizard_Base {

	use Shield\Modules\ModConsumer;

	/**
	 * @var string
	 */
	private $sCurrentWizard;

	/**
	 * Indicates if stepping through the wizard is automatic
	 * or it needs to add the instruction to click next.
	 *
	 * @var bool
	 */
	const WIZARD_STEPPING_AUTO = true;

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
		catch ( Exception $e ) {
		}

		return $aResponse;
	}

	/**
	 * TODO: does not honour 'min_user_permissions' from the wizard definition
	 */
	public function onWpLoaded() {
		$wizard = Services::Request()->query( 'wizard' );
		try {
			$this->setCurrentWizard( $wizard );

			$sDieMessage = 'Not Permitted';
			if ( $this->getUserCan() ) {
				$this->loadWizard();
			}
			else {
				$sDieMessage = 'Please login to run this wizard';
			}

			Services::WpGeneral()->wpDie( $sDieMessage );
		}
		catch ( \Exception $e ) {
			if ( $wizard == 'landing' ) {
				$this->loadWizardLanding();
			}
		}
	}

	/**
	 * @uses echo()
	 */
	protected function loadWizard() {
		try {
			$content = $this->renderWizard();
		}
		catch ( \Exception $e ) {
			$content = $e->getMessage();
		}
		echo $content;
		die();
	}

	/**
	 * @return string
	 */
	public function renderWizardLandingPage() {
		try {
			$content = $this->getMod()->renderTemplate(
				'wizard/pages/landing.twig',
				$this->getRenderData_PageWizardLanding()
			);
		}
		catch ( \Exception $e ) {
			$content = $e->getMessage();
		}
		return $content;
	}

	/**
	 * @return string
	 */
	public function renderWizardLandingSnippet() {
		try {
			$content = $this->getMod()->renderTemplate(
				'wizard/snippets/wizard_landing.twig',
				$this->getRenderData_PageWizardLanding()
			);
		}
		catch ( \Exception $e ) {
			$content = $e->getMessage();
		}
		return $content;
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
		$r = $this->processWizardStep( Services::Request()->post( 'wizard-step' ) );
		if ( !$r instanceof StdResponse ) {
			$response = new StdResponse();
			$response->msg_text = $r->getMessageText();
			$response->success = $r->successful();
		}
		else {
			$response = $r;
		}

		$msg = $response->msg_text;
		if ( $response->success ) {
			if ( !self::WIZARD_STEPPING_AUTO ) {
				$msg .= '<br />'.sprintf( 'Please click %s to continue.', __( 'Next Step' ) );
			}
		}
		else {
			$msg = sprintf( '%s: %s', __( 'Error' ), $msg );
		}

		$data = $response->getRawData();
		$data[ 'message' ] = $msg;
		$data[ 'success' ] = $response->success;

		return $data;
	}

	/**
	 * @param string $step
	 * @return StdResponse|\FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( string $step ) {
		switch ( $step ) {
			default:
				$response = null; // we don't process any steps we don't recognise.
				break;
		}
		return $response;
	}

	/**
	 * @param \FernleafSystems\Utilities\Response $oResponse
	 * @return \FernleafSystems\Utilities\Response
	 */
	protected function buildWizardResponse( $oResponse ) {

		$msg = $oResponse->getMessageText();
		if ( $oResponse->successful() ) {
			if ( !self::WIZARD_STEPPING_AUTO ) {
				$msg .= '<br />'.sprintf( 'Please click %s to continue.', __( 'Next Step' ) );
			}
		}
		else {
			$msg = sprintf( '%s: %s', __( 'Error' ), $msg );
		}

		$aData = $oResponse->getData();
		$aData[ 'message' ] = $msg;
		$oResponse->setData( $aData );
		return $oResponse;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	public function renderWizard() {
		remove_all_actions( 'wp_footer' ); // FIX: nextgen gallery forces this to run.
		return $this->getMod()->renderTemplate( 'wizard/wizard_container.twig', $this->getRenderData_PageWizard() );
	}

	/**
	 * @return array[]
	 */
	protected function getModuleWizardsForRender() {
		/** @var Shield\Modules\Base\ModCon $mod */
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
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$wizards = $this->getModuleWizardsForRender();

		return Services::DataManipulation()->mergeArraysRecursive(
			$mod->getUIHandler()->getBaseDisplayData(),
			[
				'strings' => [
					'page_title'   => 'Select Your Wizard',
					'premium_note' => 'Note: This uses features only available to Pro-licensed installations.'
				],
				'data'    => [
					'mod_wizards_count' => count( $wizards ),
					'mod_wizards'       => $wizards
				],
				'hrefs'   => [
					'dashboard'   => $this->getCon()->getPluginUrl_DashboardHome(),
					'goprofooter' => 'https://shsec.io/goprofooter',
				],
				'ajax'    => [
					'content'       => $mod->getAjaxActionData( 'wiz_process_step' ),
					'steps'         => $mod->getAjaxActionData( 'wiz_render_step' ),
					'steps_as_json' => $mod->getAjaxActionData( 'wiz_render_step', true ),
				]
			]
		);
	}

	/**
	 * TODO: Abstract and move elsewhere - it's here because Wizards on the only consumer of twig templates
	 * @return array
	 */
	protected function getRenderData_TwigPageBase() {
		return $this->getMod()->getUIHandler()->getBaseDisplayData();
	}

	/**
	 * @return array
	 */
	protected function getRenderData_PageWizard() {
		$con = $this->getMod()->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$steps = $this->buildSteps();

		$aStepsNames = [];
		foreach ( $steps as $stepKey ) {
			$aStepsNames[] = $this->getStepsDefinition()[ $stepKey ][ 'title' ];
		}

		return Services::DataManipulation()->mergeArraysRecursive(
			$mod->getUIHandler()->getBaseDisplayData(),
			[
				'strings' => [
					'page_title'  => $this->getPageTitle(),
					'plugin_name' => $con->getHumanName()
				],
				'data'    => [
					'wizard_slug'       => $this->getWizardSlug(),
					'wizard_steps'      => json_encode( $steps ),
					'wizard_step_names' => json_encode( $aStepsNames ),
					'wizard_first_step' => json_encode( $this->getWizardFirstStep() ),
				],
				'hrefs'   => [
					'dashboard'   => $this->getCon()->getPluginUrl_DashboardHome(),
					'goprofooter' => 'https://shsec.io/goprofooter',
				],
				'ajax'    => [
					'content'       => $mod->getAjaxActionData( 'wiz_process_step' ),
					'steps'         => $mod->getAjaxActionData( 'wiz_render_step' ),
					'steps_as_json' => $mod->getAjaxActionData( 'wiz_render_step', true ),
				]
			]
		);
	}

	protected function getPageTitle() :string {
		return sprintf( __( '%s Wizard', 'wp-simple-firewall' ), $this->getMod()->getCon()->getHumanName() );
	}

	protected function buildSteps() :array {
		return $this->getUserCan() ? $this->determineWizardSteps() : [ 'no_access' ];
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	protected function determineWizardSteps() :array {
		throw new \Exception( sprintf( 'Could not determine wizard steps for current wizard: %s', $this->getWizardSlug() ) );
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
		catch ( \Exception $e ) {
			$aNextStepDef[ 'content' ] = 'Content could not be displayed due to error: '.$e->getMessage();
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

	protected function getRenderData_SlideBase() :array {
		$mod = $this->getMod();
		$aWizards = $this->getModuleWizardsForRender();
		return [
			'strings' => $mod->getStrings()->getDisplayStrings(),
			'flags'   => [
				'is_premium'        => $mod->isPremium(),
				'has_other_wizards' => false
			],
			'hrefs'   => [
				'dashboard' => $this->getCon()->getPluginUrl_DashboardHome(),
				'gopro'     => 'https://shsec.io/ap',
			],
			'imgs'    => [
				'play_button' => $this->getCon()->urls->forImage( 'bootstrap/play-circle.svg' )
			],
			'data'    => [
				'mod_wizards_count' => count( $aWizards ),
				'mod_wizards'       => $aWizards
			],
		];
	}

	/**
	 * @param string $step
	 * @return array
	 */
	protected function getRenderData_SlideExtra( $step ) {
		return [];
	}

	/**
	 * @param string $slug
	 * @return string
	 * @throws Exception
	 */
	protected function renderWizardStep( $slug ) {

		$template = $slug;
		if ( strpos( $slug, '/' ) === false ) {
			$base = $this->isSlideCommon( $slug ) ? 'common' : $this->getWizardSlug();
			$template = sprintf( '%s/%s', $base, $slug );
		}

		return $this->getMod()->renderTemplate(
			sprintf( 'wizard/slides/%s.twig', $template ),
			$this->getRenderData_Slide( $slug )
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