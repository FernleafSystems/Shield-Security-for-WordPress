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
		$oDP = $this->loadDP();
		$nCurrent = $oDP->FetchPost( 'current_index' );
		$aSteps = $oDP->FetchPost( 'wizard_steps' );
		$aNextStep = $this->getNextWizardStep( $aSteps, $nCurrent );

		// So to keep things simple, we render as normal, but then we overwrite with Security Admin
		if ( !$this->getController()->getHasPermissionToManage() ) {
			$aNextStep[ 'content' ] = $this->renderSecurityAdminVerifyWizardStep( $nCurrent );
		}

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

			case 'admin_access_restriction_verify':
				$oResponse = $this->wizardSecurityAdminVerify();
				break;

			case 'license':
				$oResponse = $this->wizardLicense();
				break;

			case 'importoptions':
				$oResponse = $this->wizardImportOptions();
				break;

			case 'admin_access_restriction':
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
				'wizard_steps' => json_encode( $this->determineWizardSteps() ),
			),
			'hrefs'        => array(
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
				'shield_logo'      => 'https://plugins.svn.wordpress.org/wp-simple-firewall/assets/banner-1544x500-transparent.png',
				'what_is_this'     => 'https://icontrolwp.freshdesk.com/support/solutions/articles/3000064840',
				'favicon'          => $oCon->getPluginUrl_Image( 'pluginlogo_24x24.png' ),
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
	 * @return string[]
	 */
	protected function determineWizardSteps() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();

		$aStepsSlugs = array( 'welcome' );
		if ( !$oFO->isPremium() ) {
			$aStepsSlugs[] = 'license';
		}

		$aStepsSlugs[] = 'importoptions';

		if ( !$this->getController()->getModule( 'admin_access_restriction' )->getIsMainFeatureEnabled() ) {
			$aStepsSlugs[] = 'admin_access_restriction';
		}

		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oModule */
		$oModule = $this->getController()->getModule( 'audit_trail' );
		if ( !$oModule->getIsMainFeatureEnabled() ) {
			$aStepsSlugs[] = 'audit_trail';
		}

		if ( !$this->getController()->getModule( 'ips' )->getIsMainFeatureEnabled() ) {
			$aStepsSlugs[] = 'ips';
		}

		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oModule */
		$oModule = $this->getController()->getModule( 'login_protect' );
		if ( !( $oModule->getIsMainFeatureEnabled() && $oModule->isEnabledGaspCheck() ) ) {
			$aStepsSlugs[] = 'login_protect';
		}

		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oModule */
		$oModule = $this->getController()->getModule( 'comments_filter' );
		if ( !( $oModule->getIsMainFeatureEnabled() && $oModule->isEnabledGaspCheck() ) ) {
			$aStepsSlugs[] = 'comments_filter';
		}

		$aStepsSlugs[] = 'how_shield_works';
		$aStepsSlugs[] = 'thankyou';
		return $aStepsSlugs;
	}

	/**
	 * @param array $aAllSteps
	 * @param int   $nCurrentStep
	 * @return array
	 */
	protected function getNextWizardStep( $aAllSteps, $nCurrentStep ) {
		$aSteps = array_values( array_intersect_key( $this->getWizardSteps(), array_flip( $aAllSteps ) ) );

		if ( isset( $aSteps[ $nCurrentStep + 1 ] ) ) {
			$aNext = $aSteps[ $nCurrentStep + 1 ];

			$aData = $this->getRenderDataForStep( $aNext[ 'slug' ] );
			$aNext[ 'content' ] = $this->renderWizardStep( $aNext[ 'slug' ], $aData );
		}
		else {
			$aNext = array();
		}
		return $aNext;
	}

	/**
	 * @param int $nIndex
	 * @return string
	 */
	protected function renderSecurityAdminVerifyWizardStep( $nIndex ) {
		return $this->renderWizardStep( 'admin_access_restriction_verify', array( 'current_index' => $nIndex ) );
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
			'hrefs' => array(),
			'imgs'  => array(),
		);

		switch ( $sSlug ) {
			case 'importoptions':
				break;
			case 'how_shield_works':
				$aData[ 'imgs' ][ 'how_shield_works' ] = $oConn->getPluginUrl_Image( 'wizard/general-shield_where.png' );
				$aData[ 'imgs' ][ 'modules' ] = $oConn->getPluginUrl_Image( 'wizard/general-shield_modules.png' );
				$aData[ 'imgs' ][ 'options' ] = $oConn->getPluginUrl_Image( 'wizard/general-shield_options.png' );
				$aData[ 'imgs' ][ 'help' ] = $oConn->getPluginUrl_Image( 'wizard/general-shield_help.png' );
				$aData[ 'imgs' ][ 'actions' ] = $oConn->getPluginUrl_Image( 'wizard/general-shield_actions.png' );
				$aData[ 'imgs' ][ 'module_onoff' ] = $oConn->getPluginUrl_Image( 'wizard/general-module_onoff.png' );
				$aData[ 'imgs' ][ 'option_help' ] = $oConn->getPluginUrl_Image( 'wizard/general-option_help.png' );
				break;
			default:
				break;
		}

		return $aData;
	}

	/**
	 * @param string $sSlug
	 * @param array  $aRenderData
	 * @return string
	 */
	protected function renderWizardStep( $sSlug, $aRenderData = array() ) {
		return $this->loadRenderer( $this->getController()->getPath_Templates() )
					->setTemplate( sprintf( 'wizard/slide-%s.twig', $sSlug ) )
					->setRenderVars( $aRenderData )
					->setTemplateEngineTwig()
					->render();
	}

	/**
	 * @return array[]
	 */
	private function getWizardSteps() {
		$aStandard = array(
			'welcome'                  => array(
				'title'   => _wpsf__( 'Welcome' ),
				'slug'    => 'welcome',
				'content' => '',
			),
			'license'                  => array(
				'title'   => _wpsf__( 'Go Pro' ),
				'slug'    => 'license',
				'content' => '',
			),
			'importoptions'            => array(
				'title'   => _wpsf__( 'Import' ),
				'slug'    => 'importoptions',
				'content' => '',
			),
			'admin_access_restriction' => array(
				'title'   => _wpsf__( 'Security Admin' ),
				'slug'    => 'admin_access_restriction',
				'content' => '',
			),
			'audit_trail'              => array(
				'title'   => _wpsf__( 'Audit Trail' ),
				'slug'    => 'audit_trail',
				'content' => '',
			),
			'ips'                      => array(
				'title'   => _wpsf__( 'IP Blacklist' ),
				'slug'    => 'ips',
				'content' => '',
			),
			'login_protect'            => array(
				'title'   => _wpsf__( 'Login Protection' ),
				'slug'    => 'login_protect',
				'content' => '',
			),
			'comments_filter'          => array(
				'title'   => _wpsf__( 'Comment SPAM' ),
				'slug'    => 'comments_filter',
				'content' => '',
			),
			'how_shield_works'         => array(
				'title'   => _wpsf__( 'How Shield Works' ),
				'slug'    => 'how_shield_works',
				'content' => '',
			),
			'thankyou'                 => array(
				'title'   => _wpsf__( 'Thank You' ),
				'slug'    => 'thankyou',
				'content' => '',
			)
		);

		return $aStandard;
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
	private function wizardImportOptions() {
		$oDP = $this->loadDP();
		$sSourceSiteUrl = trim( $oDP->FetchPost( 'SourceSiteUrl' ) );
		$sSecretKey = trim( $oDP->FetchPost( 'SourceSiteSecretKey' ) );

		$aParts = parse_url( $sSourceSiteUrl );

		$bSuccess = false;
		if ( empty( $sSecretKey ) ) {
			$sMessage = _wpsf__( 'Secret key was empty.' );
		}
		else if ( strlen( $sSecretKey ) != 40 ) {
			$sMessage = _wpsf__( 'Secret key was not 40 characters long.' );
		}
		else if ( preg_match( '#[^0-9a-z]#i', $sSecretKey ) ) {
			$sMessage = _wpsf__( 'Secret key contains invalid characters - it should be letters and numbers only.' );
		}
		else if ( empty( $aParts ) ) {
			$sMessage = _wpsf__( 'Source site URL could not be parsed correctly.' );
		}
		else {
			$bReady = true;
			$aEssential = array( 'scheme', 'host' );
			foreach ( $aEssential as $sKey ) {
				$bReady = $bReady && !empty( $aParts[ $sKey ] );
			}

			if ( !$bReady ) {
				$sMessage = _wpsf__( 'Source site URL could not be parsed correctly.' );
			}
			else {
				/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
				$oFO = $this->getFeature();
				$oFO->startImportExportHandshake();

				$sFinalUrl = add_query_arg(
					array(
						'shield_action' => 'importexport_export',
						'secret'        => $sSecretKey,
						'url'           => $this->loadWp()->getHomeUrl()
					),
					$sSourceSiteUrl
				);

				$sResponse = $this->loadFS()->getUrlContent( $sFinalUrl );
				$aParts = @json_decode( $sResponse, true );

				$bSuccess = false;
				$sMessage = 'Unknown Error';
				if ( empty( $aParts ) ) {
					$sMessage = _wpsf__( 'Could not parse the response from the site.' )
								.' '._wpsf__( 'Check the secret key is correct for the remote site.' );
				}
				else if ( !isset( $aParts[ 'success' ] ) || !$aParts[ 'success' ] ) {

					if ( empty ( $aParts[ 'message' ] ) ) {
						$sMessage = _wpsf__( 'Failure response returned from the site.' );
					}
					else {
						$sMessage = sprintf( _wpsf__( 'Remote site responded with - %s' ), $aParts[ 'message' ] );
					}
				}
				else if ( empty( $aParts[ 'data' ] ) || !is_array( $aParts[ 'data' ] ) ) {
					$sMessage = _wpsf__( 'Data returned from the site was empty.' );
				}
				else {
					/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
					$oFO = $this->getFeature();
					do_action( $oFO->prefix( 'import_options' ), $aParts[ 'data' ] );
					$sMessage = _wpsf__( 'Options imported successfully to your site.' );
					$bSuccess = true;
				}
			}
		}

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( $bSuccess )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardSecurityAdminVerify() {
		$oDP = $this->loadDP();
		$sKey = trim( $oDP->FetchPost( 'AccessKey' ) );

		$oResponse = new \FernleafSystems\Utilities\Response();

		$bSuccess = false;
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oModule */
		$oModule = $this->getController()->getModule( 'admin_access_restriction' );

		$sMessage = '';
		if ( empty( $sKey ) ) {
			$sMessage = 'Security access key was empty.';
		}
		else if ( !$oModule->verifyAccessKey( $sKey ) ) {
			$sMessage = _wpsf__( 'Security Admin Key was not correct.' );
		}
		else {
			$bSuccess = true;
			$oModule->setPermissionToSubmit( true );
			$aData = array(
				'rerender' => true
			);
			$oResponse->setData( $aData );
		}

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
			$sMessage = 'Security access key was empty.';
		}
		else if ( $sKey != $sConfirm ) {
			$sMessage = 'Keys do not match.';
		}
		else {
			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oModule */
			$oModule = $this->getController()->getModule( 'admin_access_restriction' );
			try {
				$oModule->setNewAccessKeyManually( $sKey )
						->setPermissionToSubmit( true );
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