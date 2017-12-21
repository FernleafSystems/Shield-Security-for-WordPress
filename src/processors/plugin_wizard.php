<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin_Wizard', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wizard.php' );

/**
 * @uses php 5.4+
 * Class ICWP_WPSF_Processor_Plugin_SetupWizard
 */
class ICWP_WPSF_Processor_Plugin_Wizard extends ICWP_WPSF_Processor_Base_Wizard {

	/**
	 * @return string[]
	 */
	protected function getSupportedWizards() {
		return array(
			'welcome',
			'importexport',
		);
	}

	/**
	 * @return string
	 */
	protected function getPageTitle() {
		return sprintf( _wpsf__( '%s Welcome Wizard' ), $this->getController()->getHumanName() );
	}

	/**
	 * @param string $sStep
	 * @return \FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( $sStep ) {
		switch ( $sStep ) {

			case 'admin_access_restriction_verify':
				$oResponse = $this->wizardSecurityAdminVerify();
				break;

			case 'license':
				$oResponse = $this->wizardLicense();
				break;

			case 'import_options':
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

			case 'optin':
				$oResponse = $this->wizardOptin();
				break;

			default:
				$oResponse = null; // we don't process any steps we don't recognise.
				break;
		}
		return $oResponse;
	}

	/**
	 * @return string[]
	 */
	protected function determineWizardSteps() {

		switch ( $this->getCurrentWizard() ) {
			case 'welcome':
				$aSteps = $this->determineWizardSteps_Welcome();
				break;
			case 'import':
				$aSteps = $this->determineWizardSteps_Import();
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
	private function determineWizardSteps_Import() {
		return array(
			'import_start',
			'import_options',
			'import_finished',
		);
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Welcome() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();

		$aStepsSlugs = array( 'welcome' );
		if ( !$oFO->isPremium() ) {
//			$aStepsSlugs[] = 'license'; not showing it for now
		}

		if ( $oFO->isPremium() ) {
			$aStepsSlugs[] = 'import_options';
		}

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
		$aStepsSlugs[] = 'optin';

		if ( !$oFO->isPremium() ) {
			$aStepsSlugs[] = 'import_options';
		}

		$aStepsSlugs[] = 'finish';
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

			case 'optin':
				$oUser = $this->loadWpUsers()->getCurrentWpUser();
				$aAdd = array(
					'data' => array(
						'name'       => $oUser->first_name,
						'user_email' => $oUser->user_email
					)
				);
				break;

			case 'thankyou':
				break;

			case 'how_shield_works':
				$aAdd = array(
					'imgs'     => array(
						'how_shield_works' => $oConn->getPluginUrl_Image( 'wizard/general-shield_where.png' ),
						'modules'          => $oConn->getPluginUrl_Image( 'wizard/general-shield_modules.png' ),
						'options'          => $oConn->getPluginUrl_Image( 'wizard/general-shield_options.png' ),
						'help'             => $oConn->getPluginUrl_Image( 'wizard/general-shield_help.png' ),
						'actions'          => $oConn->getPluginUrl_Image( 'wizard/general-shield_actions.png' ),
						'module_onoff'     => $oConn->getPluginUrl_Image( 'wizard/general-module_onoff.png' ),
						'option_help'      => $oConn->getPluginUrl_Image( 'wizard/general-option_help.png' ),
					),
					'headings' => array(
						'how_shield_works' => _wpsf__( 'Where to find Shield' ),
						'modules'          => _wpsf__( 'Accessing Each Module' ),
						'options'          => _wpsf__( 'Accessing Options' ),
						'help'             => _wpsf__( 'Finding Help' ),
						'actions'          => _wpsf__( 'Actions (not Options)' ),
						'module_onoff'     => _wpsf__( 'Module On/Off Switch' ),
						'option_help'      => _wpsf__( 'Help For Each Option' ),
					),
					'captions' => array(
						'how_shield_works' => _wpsf__( "You'll find the main Shield Security setting in the left-hand WordPress menu." ),
						'modules'          => _wpsf__( 'Shield is split up into independent modules for accessing the options of each feature.' ),
						'options'          => _wpsf__( 'When you load a module, you can access the options by clicking on the Options Panel link.' ),
						'help'             => _wpsf__( 'Each module also has a brief overview help section - there is more in-depth help available.' ),
						'actions'          => _wpsf__( 'Certain modules have extra actions and features, e.g. Audit Trail Viewer.' )
											  .' '._wpsf__( 'Note: Not all modules have the actions section' ),
						'module_onoff'     => _wpsf__( 'Each module has an Enable/Disable checkbox to turn on/off all processing for that module' ),
						'option_help'      => _wpsf__( 'To help you understand each option, most of them have a more info link, and/or a blog link, to read more' ),
					),
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
			'import_start'             => array(
				'title'             => _wpsf__( 'Start Import' ),
				'restricted_access' => false
			),
			'import_finished'          => array(
				'title'             => _wpsf__( 'Import Finished' ),
				'restricted_access' => false
			),
			'welcome'                  => array(
				'title'             => _wpsf__( 'Welcome' ),
				'restricted_access' => false,
			),
			'license'                  => array(
				'title' => _wpsf__( 'Go Pro' ),
			),
			'import_options'           => array(
				'title' => _wpsf__( 'Import' ),
			),
			'admin_access_restriction' => array(
				'title' => _wpsf__( 'Security Admin' ),
			),
			'audit_trail'              => array(
				'title' => _wpsf__( 'Audit Trail' ),
			),
			'ips'                      => array(
				'title' => _wpsf__( 'IP Blacklist' ),
			),
			'login_protect'            => array(
				'title' => _wpsf__( 'Login Protection' ),
			),
			'comments_filter'          => array(
				'title' => _wpsf__( 'Comment SPAM' ),
			),
			'how_shield_works'         => array(
				'title'             => _wpsf__( 'How Shield Works' ),
				'restricted_access' => false,
			),
			'optin'                    => array(
				'title'   => _wpsf__( 'Join Us!' ),
				'content' => '',
			),
			'thankyou'                 => array(
				'title'             => _wpsf__( 'Thank You' ),
				'restricted_access' => false,
			)
		);
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardLicense() {
		$sKey = $this->loadDP()->post( 'LicenseKey' );

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
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		$oDP = $this->loadDP();

		$sMasterSiteUrl = $oDP->post( 'MasterSiteUrl' );
		$sSecretKey = $oDP->post( 'MasterSiteSecretKey' );
		$bEnabledNetwork = $oDP->post( 'ShieldNetworkCheck' ) === 'Y';

		/** @var ICWP_WPSF_Processor_Plugin $oProc */
		$oProc = $oFO->getProcessor();
		$nCode = $oProc->getSubProcessorImportExport()
					   ->runImport( $sMasterSiteUrl, $sSecretKey, $bEnabledNetwork, $sSiteResponse );

		$aErrors = array(
			_wpsf__( 'Options imported successfully to your site.' ), // success
			_wpsf__( 'Secret key was empty.' ),
			_wpsf__( 'Secret key was not 40 characters long.' ),
			_wpsf__( 'Secret key contains invalid characters - it should be letters and numbers only.' ),
			_wpsf__( 'Source site URL could not be parsed correctly.' ),
			_wpsf__( 'Could not parse the response from the site.' )
			.' '._wpsf__( 'Check the secret key is correct for the remote site.' ),
			_wpsf__( 'Failure response returned from the site.' ),
			sprintf( _wpsf__( 'Remote site responded with - %s' ), $sSiteResponse ),
			_wpsf__( 'Data returned from the site was empty.' )
		);

		$sMessage = isset( $aErrors[ $nCode ] ) ? $aErrors[ $nCode ] : 'Unknown Error';

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( $nCode === 0 )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardSecurityAdminVerify() {
		$sKey = $this->loadDP()->post( 'AccessKey' );

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
		$sKey = $oDP->post( 'AccessKey' );
		$sConfirm = $oDP->post( 'AccessKeyConfirm' );

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
		$bEnabled = $this->loadDP()->post( 'AuditTrailOption' ) === 'Y';

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

		$bEnabled = $this->loadDP()->post( 'IpManagerOption' ) === 'Y';

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

		$bEnabled = $this->loadDP()->post( 'LoginProtectOption' ) === 'Y';

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
	private function wizardOptin() {
		$oDP = $this->loadDP();

		$bEnabledTracking = $oDP->post( 'AnonymousOption', 'N', true ) === 'Y';
		$bEnabledBadge = $oDP->post( 'BadgeOption', 'N', true ) === 'Y';

		/** @var ICWP_WPSF_FeatureHandler_Plugin $oModule */
		$oModule = $this->getController()->getModule( 'plugin' );
		$oModule->setIsDisplayPluginBadge( $bEnabledBadge )
				->setPluginTrackingPermission( $bEnabledTracking );

		$sMessage = _wpsf__( 'Preferences have been saved.' );

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( true )
						 ->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardCommentsFilter() {

		$bEnabled = $this->loadDP()->post( 'CommentsFilterOption' ) === 'Y';

		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oModule */
		$oModule = $this->getController()->getModule( 'comments_filter' );
		if ( $bEnabled ) { // we don't disable the whole module
			$oModule->setIsMainFeatureEnabled( true );
		}
		$oModule->setEnabledGasp( $bEnabled )
				->savePluginOptions();

		$bSuccess = $oModule->getIsMainFeatureEnabled() === $bEnabled;
		if ( $bSuccess ) {
			$sMessage = sprintf( '%s has been %s.', _wpsf__( 'Comment SPAM Protection' ),
				$oModule->getIsMainFeatureEnabled() ? _wpsf__( 'Enabled' ) : _wpsf__( 'Disabled' )
			);
		}
		else {
			$sMessage = sprintf( _wpsf__( '%s setting could not be changed at this time.' ), _wpsf__( 'Comment SPAM Protection' ) );
		}

		$oResponse = new \FernleafSystems\Utilities\Response();
		return $oResponse->setSuccessful( $bSuccess )
						 ->setMessageText( $sMessage );
	}
}