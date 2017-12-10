<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin_SetupWizard', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

/**
 * @uses php 5.4+
 * Class ICWP_WPSF_Processor_Plugin_SetupWizard
 */
class ICWP_WPSF_Processor_Plugin_SetupWizard extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		add_action( 'init', array( $this, 'onWpInit' ), 0 );
	}

	public function onWpInit() {
		if ( $this->loadWpUsers()->isUserLoggedIn() ) { // TODO: can manage
			$this->loadWizard();
		}
	}

	public function ajaxSetupWizardSteps() {
		$nCurrent = $this->loadDP()->FetchPost( 'current_index' );
		$aNextStep = $this->getNextWizardStep( $nCurrent );
		return $this->getFeature()
					->sendAjaxResponse(
						true,
						array( 'next_step' => $aNextStep )
					);
	}

	public function ajaxSetupWizardContent() {
		$oDP = $this->loadDP();

		$this->loadAutoload(); // for Response
		switch ( $oDP->FetchPost( 'wizard-step' ) ) {

			case 'license':
				$oResponse = $this->wizardLicense();
				break;

			case 'securityadmin':
				$oResponse = $this->wizardSecurityAdmin();
				break;

			case 'audit_trail':
				$oResponse = $this->wizardAuditTrail();
				break;

			case 'ips':
				$oResponse = $this->wizardIps();
				break;

			case 'comments_filter':
				$oResponse = $this->wizardCommentsFilter();
				break;

			case 'login_protect':
				$oResponse = $this->wizardLoginProtect();
				break;

			default:
				$oResponse = new \FernleafSystems\Utilities\Response();
				$oResponse->setSuccessful( false )
						  ->setMessageText( _wpsf__( 'Unknown request' ) );
				break;
		}

		$sMessage = $oResponse->getMessageText();
		if ( $oResponse->successful() ) {
			$sMessage .= '<br />'._wpsf__( 'Please click Next (above) to continue.' );
		}
		else {
			$sMessage = sprintf( '%s: %s', _wpsf__( 'Error' ), $sMessage );
		}

		$aData = $oResponse->getData();
		$aData[ 'message' ] = $sMessage;

		$this->getFeature()
			 ->sendAjaxResponse( $oResponse->successful(), $aData );
	}

	protected function loadWizard() {
		$this->printWizard();
		die();
	}

	/**
	 * @return bool true if valid form printed, false otherwise. Should die() if true
	 */
	public function printWizard() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		$oCon = $this->getController();
		$aLoginIntentFields = apply_filters( $oFO->prefix( 'login-intent-form-fields' ), array() );

		$sMessage = $this->loadAdminNoticesProcessor()
						 ->flushFlashMessage()
						 ->getRawFlashMessageText();

		$aDisplayData = array(
			'strings'      => array(
				'welcome'         => _wpsf__( 'Welcome' ),
				'time_remaining'  => _wpsf__( 'Time Remaining' ),
				'calculating'     => _wpsf__( 'Calculating' ).' ...',
				'seconds'         => strtolower( _wpsf__( 'Seconds' ) ),
				'login_expired'   => _wpsf__( 'Login Expired' ),
				'verify_my_login' => _wpsf__( 'Verify My Login' ),
				'more_info'       => _wpsf__( 'More Info' ),
				'what_is_this'    => _wpsf__( 'What is this?' ),
				'message'         => $sMessage,
				'page_title'      => sprintf( _wpsf__( '%s Login Verification' ), $oCon->getHumanName() )
			),
			'data'         => array(
				'login_fields' => $aLoginIntentFields,
			),
			'hrefs'        => array(
				'form_action'     => $this->loadDataProcessor()->getRequestUri(),
				'css_bootstrap'   => $oCon->getPluginUrl_Css( 'bootstrap3.min.css' ),
				'css_pages'       => $oCon->getPluginUrl_Css( 'pages.css' ),
				'css_steps'       => $oCon->getPluginUrl_Css( 'jquery.steps.css' ),
				'css_wizard'      => $oCon->getPluginUrl_Css( 'wizard.css' ),
				'js_jquery'       => $this->loadWpIncludes()->getUrl_Jquery(),
				'js_bootstrap'    => $oCon->getPluginUrl_Js( 'bootstrap3.min.js' ),
				'js_globalplugin' => $oCon->getPluginUrl_Js( 'global-plugin.js' ),
				'js_steps'        => $oCon->getPluginUrl_Js( 'jquery.steps.min.js' ),
				'shield_logo'     => 'https://plugins.svn.wordpress.org/wp-simple-firewall/assets/banner-1544x500-transparent.png',
				'what_is_this'    => 'https://icontrolwp.freshdesk.com/support/solutions/articles/3000064840',
				'favicon'         => $oCon->getPluginUrl_Image( 'pluginlogo_24x24.png' ),
			),
			'ajax'         => array(
				'content'       => $oFO->getBaseAjaxActionRenderData( 'SetupWizardContent' ),
				'steps'         => $oFO->getBaseAjaxActionRenderData( 'SetupWizardSteps' ),
				'steps_as_json' => $oFO->getBaseAjaxActionRenderData( 'SetupWizardSteps', true ),
			),
			'wizard_steps' => json_encode(
				(object)array(
					'title'   => 'test title 1',
					'content' => 'test content 1',
				)
			)
		);

		$this->loadRenderer( $this->getController()->getPath_Templates() )
			 ->setTemplate( 'pages/wizard.twig' )
			 ->setRenderVars( $aDisplayData )
			 ->setTemplateEngineTwig()
			 ->display();

		return true;
	}

	/**
	 * @param int $nCurrentStep
	 * @return array
	 */
	protected function getNextWizardStep( $nCurrentStep ) {
		$aSteps = $this->getWizardSteps();
		$aNext = $aSteps[ $nCurrentStep + 1 ];
		$aNext[ 'content' ] = $this->renderWizardStep( $aNext[ 'slug' ] );
		return $aNext;
	}

	/**
	 * @param string $sSlug
	 * @return string
	 */
	protected function renderWizardStep( $sSlug ) {
		return $this->loadRenderer( $this->getController()->getPath_Templates() )
					->setTemplate( sprintf( 'wizard/slide-%s.twig', $sSlug ) )
					->setRenderVars( array() )
					->setTemplateEngineTwig()
					->render();
	}

	/**
	 * @return array[]
	 */
	private function getWizardSteps() {
		return array(
			array(
				'title'   => _wpsf__( 'Welcome' ),
				'slug'    => 'welcome',
				'content' => '',
			),
			array(
				'title'   => _wpsf__( 'Go Pro' ),
				'slug'    => 'license',
				'content' => '',
			),
			array(
				'title'   => _wpsf__( 'Security Admin' ),
				'slug'    => 'securityadmin',
				'content' => '',
			),
			array(
				'title'   => _wpsf__( 'Audit Trail' ),
				'slug'    => 'audit_trail',
				'content' => '',
			),
			array(
				'title'   => _wpsf__( 'IP Blacklist' ),
				'slug'    => 'ips',
				'content' => '',
			),
			array(
				'title'   => _wpsf__( 'Login Protection' ),
				'slug'    => 'login_protect',
				'content' => '',
			),
			array(
				'title'   => _wpsf__( 'Comment SPAM' ),
				'slug'    => 'comments_filter',
				'content' => '',
			),
			array(
				'title'   => _wpsf__( 'IP Blacklist' ),
				'slug'    => 'ips',
				'content' => '',
			),
			array(
				'content' => '',
			)
		);
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardLicense() {
		$oDP = $this->loadDP();
		$sKey = trim( $oDP->FetchPost( 'LicenseKey' ) );

		$bSuccess = false;
		if ( empty( $sKey ) ) {
			$sMessage = 'License key was empty.';
		}
		else {
			/** @var ICWP_WPSF_FeatureHandler_License $oModule */
			$oModule = $this->getController()->getModule( 'license' );
			try {
				$oModule->activateOfficialLicense( $sKey, true );
				if ( $oModule->hasValidWorkingLicense() ) {
					$bSuccess = true;
					$sMessage = _wpsf__( 'License key was accepted and installed successfully.' );
				}
				else {
					$sMessage = _wpsf__( 'License key was not accepted.' );
				}
			}
			catch ( Exception $oE ) {
				$sMessage = _wpsf__( $oE->getMessage() );
			}
		}

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( $bSuccess )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardSecurityAdmin() {
		$oDP = $this->loadDP();
		$sKey = trim( $oDP->FetchPost( 'AccessKey' ) );
		$sConfirm = trim( $oDP->FetchPost( 'AccessKeyConfirm' ) );

		$oResponse = new \FernleafSystems\Utilities\Response();

		$bSuccess = false;
		if ( empty( $sKey ) ) {
			$sMessage = 'Access Key provided was empty.';
		}
		else if ( $sKey != $sConfirm ) {
			$sMessage = 'Keys do not match.';
		}
		else {
			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oModule */
			$oModule = $this->getController()->getModule( 'admin_access_restriction' );
			try {
				$oModule->setNewAccessKeyManually( $sKey, true );
				$bSuccess = true;
				$sMessage = _wpsf__( 'Security Admin setup was successful.' );
			}
			catch ( Exception $oE ) {
				$sMessage = _wpsf__( $oE->getMessage() );
			}
		}

		return $oResponse->setSuccessful( $bSuccess )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardAuditTrail() {

		$bEnabled = trim( $this->loadDP()->FetchPost( 'AuditTrailOption' ) ) === 'Y';

		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oModule */
		$oModule = $this->getController()->getModule( 'audit_trail' );
		$oModule->setIsMainFeatureEnabled( $bEnabled )
				->savePluginOptions();

		$bSuccess = $oModule->getIsMainFeatureEnabled() === $bEnabled;
		if ( $bSuccess ) {
			$sMessage = sprintf( '%s has been %s.', _wpsf__( 'Audit Trail' ),
				$oModule->getIsMainFeatureEnabled() ? _wpsf__( 'Enabled' ) : _wpsf__( 'Disabled' )
			);
		}
		else {
			$sMessage = sprintf( _wpsf__( '%s setting could not be changed at this time.' ), _wpsf__( 'Audit Trail' ) );
		}

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( $bSuccess )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardIps() {

		$bEnabled = trim( $this->loadDP()->FetchPost( 'IpManagerOption' ) ) === 'Y';

		/** @var ICWP_WPSF_FeatureHandler_Ips $oModule */
		$oModule = $this->getController()->getModule( 'ips' );
		$oModule->setIsMainFeatureEnabled( $bEnabled )
				->savePluginOptions();

		$bSuccess = $oModule->getIsMainFeatureEnabled() === $bEnabled;
		if ( $bSuccess ) {
			$sMessage = sprintf( '%s has been %s.', _wpsf__( 'IP Manager' ),
				$oModule->getIsMainFeatureEnabled() ? _wpsf__( 'Enabled' ) : _wpsf__( 'Disabled' )
			);
		}
		else {
			$sMessage = sprintf( _wpsf__( '%s setting could not be changed at this time.' ), _wpsf__( 'IP Manager' ) );
		}

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( $bSuccess )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardLoginProtect() {

		$bEnabled = trim( $this->loadDP()->FetchPost( 'LoginProtectOption' ) ) === 'Y';

		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oModule */
		$oModule = $this->getController()->getModule( 'login_protect' );
		if ( $bEnabled ) { // we don't disable the whole module
			$oModule->setIsMainFeatureEnabled( true );
		}
		$oModule->setEnabledGaspCheck( $bEnabled )
				->savePluginOptions();

		$bSuccess = $oModule->getIsMainFeatureEnabled() === $bEnabled;
		if ( $bSuccess ) {
			$sMessage = sprintf( '%s has been %s.', _wpsf__( 'Login Protection' ),
				$oModule->getIsMainFeatureEnabled() ? _wpsf__( 'Enabled' ) : _wpsf__( 'Disabled' )
			);
		}
		else {
			$sMessage = sprintf( _wpsf__( '%s setting could not be changed at this time.' ), _wpsf__( 'Login Protection' ) );
		}

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( $bSuccess )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardCommentsFilter() {

		$bEnabled = trim( $this->loadDP()->FetchPost( 'CommentsFilterOption' ) ) === 'Y';

		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oModule */
		$oModule = $this->getController()->getModule( 'comments_filter' );
		if ( $bEnabled ) { // we don't disable the whole module
			$oModule->setIsMainFeatureEnabled( true );
		}
		$oModule->setEnabledGasp( $bEnabled )
				->savePluginOptions();

		$bSuccess = $oModule->getIsMainFeatureEnabled() === $bEnabled;
		if ( $bSuccess ) {
			$sMessage = sprintf( '%s has been %s.', _wpsf__( 'Comments SPAM Protection' ),
				$oModule->getIsMainFeatureEnabled() ? _wpsf__( 'Enabled' ) : _wpsf__( 'Disabled' )
			);
		}
		else {
			$sMessage = sprintf( _wpsf__( '%s setting could not be changed at this time.' ), _wpsf__( 'Comments SPAM Protection' ) );
		}

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( $bSuccess )
						 ->setMessageText( $sMessage );
	}
}