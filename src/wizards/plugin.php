<?php

/**
 * Class ICWP_WPSF_Processor_LoginProtect_Wizard
 */
class ICWP_WPSF_Wizard_Plugin extends ICWP_WPSF_Wizard_BaseWpsf {

	/**
	 * @return string
	 */
	protected function getPageTitle() {
		return sprintf( _wpsf__( '%s Welcome Wizard' ), $this->getPluginCon()->getHumanName() );
	}

	/**
	 * @param string $sStep
	 * @return \FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( $sStep ) {
		switch ( $sStep ) {

			case 'ip_detect':
				$oResponse = $this->wizardIpDetect();
				break;

			case 'license':
				$oResponse = $this->wizardLicense();
				break;

			case 'import':
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

			case 'optin_usage':
			case 'optin_badge':
				$oResponse = $this->wizardOptin();
				break;

			case 'add-search-item':
				$oResponse = $this->wizardAddSearchItem();
				break;

			case 'confirm-results-delete':
				$oResponse = $this->wizardConfirmDelete();
				break;

			default:
				$oResponse = parent::processWizardStep( $sStep );
				break;
		}
		return $oResponse;
	}

	/**
	 * @return string[]
	 * @throws Exception
	 */
	protected function determineWizardSteps() {

		switch ( $this->getWizardSlug() ) {
			case 'welcome':
				$aSteps = $this->determineWizardSteps_Welcome();
				break;
			case 'gdpr':
				$aSteps = $this->determineWizardSteps_Gdpr();
				break;
			case 'importexport':
				$aSteps = $this->determineWizardSteps_Import();
				break;
			default:
				parent::determineWizardSteps();
				break;
		}
		return array_values( array_intersect( array_keys( $this->getAllDefinedSteps() ), $aSteps ) );
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Gdpr() {
		return array(
			'start',
			'search',
			'results',
			'finished',
		);
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Import() {
		return array(
			'start',
			'import',
			'finished',
		);
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Welcome() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getModCon();
		$oConn = $this->getPluginCon();

		$aStepsSlugs = array(
			'welcome',
			'ip_detect'
		);
//		if ( !$oFO->isPremium() ) {
//			$aStepsSlugs[] = 'license'; not showing it for now
//		}

		if ( $oFO->isPremium() ) {
			$aStepsSlugs[] = 'import';
		}

		if ( !$oConn->getModule( 'admin_access_restriction' )->isModuleEnabled() ) {
			$aStepsSlugs[] = 'admin_access_restriction';
		}

		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $oModule */
		$oModule = $oConn->getModule( 'audit_trail' );
		if ( !$oModule->isModuleEnabled() ) {
			$aStepsSlugs[] = 'audit_trail';
		}

		if ( !$oConn->getModule( 'ips' )->isModuleEnabled() ) {
			$aStepsSlugs[] = 'ips';
		}

		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oModule */
		$oModule = $oConn->getModule( 'login_protect' );
		if ( !( $oModule->isModuleEnabled() && $oModule->isEnabledGaspCheck() ) ) {
			$aStepsSlugs[] = 'login_protect';
		}

		/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oModule */
		$oModule = $oConn->getModule( 'comments_filter' );
		if ( !( $oModule->isModuleEnabled() && $oModule->isEnabledGaspCheck() ) ) {
			$aStepsSlugs[] = 'comments_filter';
		}

		$aStepsSlugs[] = 'how_shield_works';
		$aStepsSlugs[] = 'optin';

		if ( !$oFO->isPremium() ) {
			$aStepsSlugs[] = 'import';
		}

		$aStepsSlugs[] = 'thankyou';
		return $aStepsSlugs;
	}

	/**
	 * @param string $sStep
	 * @return array
	 */
	protected function getRenderData_SlideExtra( $sStep ) {
		$oConn = $this->getPluginCon();

		$aAdditional = array();

		$sCurrentWiz = $this->getWizardSlug();

		if ( $sCurrentWiz == 'welcome' ) {

			switch ( $sStep ) {
				case 'ip_detect':
					$aAdditional = array(
						'hrefs' => array(
							'visitor_ip' => 'https://icwp.io/visitorip',
						)
					);
					break;
				case 'license':
					break;
				case 'import':
					$aAdditional = array(
						'hrefs' => array(
							'blog_importexport' => 'https://icwp.io/av'
						),
						'imgs'  => array(
							'shieldnetworkmini' => $oConn->getPluginUrl_Image( 'shield/shieldnetworkmini.png' ),
						)
					);
					break;

				case 'optin':
					$oUser = $this->loadWpUsers()->getCurrentWpUser();
					$aAdditional = array(
						'data'    => array(
							'name'       => $oUser->first_name,
							'user_email' => $oUser->user_email
						),
						'hrefs'   => array(
							'privacy_policy' => $this->getModCon()->getDef( 'href_privacy_policy' )
						),
						'strings' => array(
							'privacy_policy' => sprintf(
								'I certify that I have read and agree to the <a href="%s" target="_blank">Privacy Policy</a>',
								$this->getModCon()->getDef( 'href_privacy_policy' )
							),
						)
					);
					break;

				case 'thankyou':
					break;

				case 'how_shield_works':
					$aAdditional = array(
						'imgs'     => array(
							'how_shield_works' => $oConn->getPluginUrl_Image( 'wizard/general-shield_where.png' ),
							'modules'          => $oConn->getPluginUrl_Image( 'wizard/general-shield_modules.png' ),
							'options'          => $oConn->getPluginUrl_Image( 'wizard/general-shield_options.png' ),
							'wizards'          => $oConn->getPluginUrl_Image( 'wizard/general-shield_wizards.png' ),
							'help'             => $oConn->getPluginUrl_Image( 'wizard/general-shield_help.png' ),
							'actions'          => $oConn->getPluginUrl_Image( 'wizard/general-shield_actions.png' ),
							'option_help'      => $oConn->getPluginUrl_Image( 'wizard/general-option_help.png' ),
							'module_onoff'     => $oConn->getPluginUrl_Image( 'wizard/general-module_onoff.png' ),
						),
						'headings' => array(
							'how_shield_works' => _wpsf__( 'Where to find Shield' ),
							'modules'          => _wpsf__( 'Accessing Each Module' ),
							'options'          => _wpsf__( 'Accessing Options' ),
							'wizards'          => _wpsf__( 'Launching Wizards' ),
							'help'             => _wpsf__( 'Finding Help' ),
							'actions'          => _wpsf__( 'Actions (not Options)' ),
							'option_help'      => _wpsf__( 'Help For Each Option' ),
							'module_onoff'     => _wpsf__( 'Module On/Off Switch' ),
						),
						'captions' => array(
							'how_shield_works' => sprintf( _wpsf__( "You'll find the main %s settings in the left-hand WordPress menu." ), $oConn->getHumanName() ),
							'modules'          => _wpsf__( 'Shield is split up into independent modules for accessing the options of each feature.' ),
							'options'          => _wpsf__( 'When you load a module, you can access the options by clicking on the Options Panel link.' ),
							'wizards'          => _wpsf__( 'Launch helpful walk-through wizards for modules that have them.' ),
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
		}
		else if ( $sCurrentWiz == 'importexport' ) {
			switch ( $sStep ) {
				case 'import':
					$aAdditional = array(
						'hrefs' => array(
							'blog_importexport' => 'https://icwp.io/av'
						),
						'imgs'  => array(
							'shieldnetworkmini' => $oConn->getPluginUrl_Image( 'shield/shieldnetworkmini.png' ),
						)
					);
					break;
				case 'results': //gdpr results

					$aAdditional = array();
					break;

				default:
					break;
			}
		}
		else if ( $sCurrentWiz == 'gdpr' ) {
			switch ( $sStep ) {

				case 'results':
					$aItems = $this->getGdprSearchItems();
					$bHasSearchItems = !empty( $aItems );
					$aResults = $this->runGdprSearch();

					$nTotal = 0;
					foreach ( $aResults as $aResult ) {
						$nTotal += $aResult[ 'count' ];
					}

					$aAdditional = array(
						'flags' => array(
							'has_search_items' => $bHasSearchItems
						),
						'data'  => array(
							'result'      => $this->runGdprSearch(),
							'count_total' => $nTotal,
							'has_results' => $nTotal > 0,
						)
					);
					break;

				default:
					break;
			}
		}

		if ( empty( $aAdditional ) ) {
			$aAdditional = parent::getRenderData_SlideExtra( $sStep );
		}
		return $aAdditional;
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardIpDetect() {
		$oIps = $this->loadIpService();
		$sIp = $this->loadRequest()->post( 'ip' );

		$oResponse = new \FernleafSystems\Utilities\Response();
		$oResponse->setSuccessful( false );
		if ( empty( $sIp ) ) {
			$sMessage = 'IP address was empty.';
		}
		else if ( !$oIps->isValidIp_PublicRemote( $sIp ) ) {
			$sMessage = 'The IP address supplied was not a valid public IP address.';
		}
//		else if ( $oIps->getIpVersion( $sIp ) != 4 ) {
//			$sMessage = 'The IP address supplied was not a valid IP address.';
//		}
		else {
			$sSource = $oIps->determineSourceFromIp( $sIp );
			if ( empty( $sSource ) ) {
				$sMessage = 'Strange, the address source could not be found from this IP.';
			}
			else {
				/** @var ICWP_WPSF_FeatureHandler_Plugin $oModule */
				$oModule = $this->getPluginCon()->getModule( 'plugin' );
				$oModule->setVisitorAddressSource( $sSource )
						->savePluginOptions();
				$oResponse->setSuccessful( true );
				$sMessage = _wpsf__( 'Success!' ).' '
							.sprintf( '"%s" was found to be the best source of visitor IP addresses for your site.', $sSource );
			}
		}

		return $oResponse->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardLicense() {

		$bSuccess = false;

		/** @var ICWP_WPSF_FeatureHandler_License $oModule */
		$oModule = $this->getPluginCon()->getModule( 'license' );
		try {
			$bSuccess = $oModule->verifyLicense( true )
								->hasValidWorkingLicense();
			if ( $bSuccess ) {
				$sMessage = _wpsf__( 'License was found and successfully installed.' );
			}
			else {
				$sMessage = _wpsf__( 'License could not be found.' );
			}
		}
		catch ( Exception $oE ) {
			$sMessage = _wpsf__( $oE->getMessage() );
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $bSuccess )
			->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardImportOptions() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getModCon();
		$oREq = $this->loadRequest();

		$sMasterSiteUrl = $oREq->post( 'MasterSiteUrl' );
		$sSecretKey = $oREq->post( 'MasterSiteSecretKey' );
		$bEnabledNetwork = $oREq->post( 'ShieldNetworkCheck' ) === 'Y';

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

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $nCode === 0 )
			->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardSecurityAdmin() {
		$oReq = $this->loadRequest();
		$sKey = $oReq->post( 'AccessKey' );
		$sConfirm = $oReq->post( 'AccessKeyConfirm' );

		$bSuccess = false;
		if ( empty( $sKey ) ) {
			$sMessage = 'Security access key was empty.';
		}
		else if ( $sKey != $sConfirm ) {
			$sMessage = 'Keys do not match.';
		}
		else {
			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oModule */
			$oModule = $this->getPluginCon()->getModule( 'admin_access_restriction' );
			try {
				$oModule->setNewAccessKeyManually( $sKey )
						->setSecurityAdminStatusOnOff( true );
				$bSuccess = true;
				$sMessage = _wpsf__( 'Security Admin setup was successful.' );
			}
			catch ( Exception $oE ) {
				$sMessage = _wpsf__( $oE->getMessage() );
			}
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $bSuccess )
			->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardAuditTrail() {

		$sInput = $this->loadRequest()->post( 'AuditTrailOption' );
		$bSuccess = false;
		$sMessage = _wpsf__( 'No changes were made as no option was selected' );

		if ( !empty( $sInput ) ) {
			$bEnabled = $sInput === 'Y';

			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oModule */
			$oModule = $this->getPluginCon()->getModule( 'audit_trail' );
			$oModule->setIsMainFeatureEnabled( $bEnabled )
					->savePluginOptions();

			$bSuccess = $oModule->isModuleEnabled() === $bEnabled;
			if ( $bSuccess ) {
				$sMessage = sprintf( '%s has been %s.', _wpsf__( 'Audit Trail' ),
					$oModule->isModuleEnabled() ? _wpsf__( 'Enabled' ) : _wpsf__( 'Disabled' )
				);
			}
			else {
				$sMessage = sprintf( _wpsf__( '%s setting could not be changed at this time.' ), _wpsf__( 'Audit Trail' ) );
			}
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $bSuccess )
			->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardIps() {

		$sInput = $this->loadRequest()->post( 'IpManagerOption' );
		$bSuccess = false;
		$sMessage = _wpsf__( 'No changes were made as no option was selected' );

		if ( !empty( $sInput ) ) {
			$bEnabled = $sInput === 'Y';

			/** @var ICWP_WPSF_FeatureHandler_Ips $oModule */
			$oModule = $this->getPluginCon()->getModule( 'ips' );
			$oModule->setIsMainFeatureEnabled( $bEnabled )
					->savePluginOptions();

			$bSuccess = $oModule->isModuleEnabled() === $bEnabled;
			if ( $bSuccess ) {
				$sMessage = sprintf( '%s has been %s.', _wpsf__( 'IP Manager' ),
					$oModule->isModuleEnabled() ? _wpsf__( 'Enabled' ) : _wpsf__( 'Disabled' )
				);
			}
			else {
				$sMessage = sprintf( _wpsf__( '%s setting could not be changed at this time.' ), _wpsf__( 'IP Manager' ) );
			}
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $bSuccess )
			->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardLoginProtect() {

		$sInput = $this->loadRequest()->post( 'LoginProtectOption' );
		$bSuccess = false;
		$sMessage = _wpsf__( 'No changes were made as no option was selected' );

		if ( !empty( $sInput ) ) {
			$bEnabled = $sInput === 'Y';

			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oModule */
			$oModule = $this->getPluginCon()->getModule( 'login_protect' );
			if ( $bEnabled ) { // we don't disable the whole module
				$oModule->setIsMainFeatureEnabled( true );
			}
			$oModule->setEnabledGaspCheck( $bEnabled )
					->savePluginOptions();

			$bSuccess = $oModule->isEnabledGaspCheck() === $bEnabled;
			if ( $bSuccess ) {
				$sMessage = sprintf( '%s has been %s.', _wpsf__( 'Login Guard' ),
					$bEnabled ? _wpsf__( 'Enabled' ) : _wpsf__( 'Disabled' )
				);
			}
			else {
				$sMessage = sprintf( _wpsf__( '%s setting could not be changed at this time.' ), _wpsf__( 'Login Guard' ) );
			}
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $bSuccess )
			->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardOptin() {
		$oReq = $this->loadRequest();
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oModule */
		$oModule = $this->getPluginCon()->getModule( 'plugin' );

		$bSuccess = false;
		$sMessage = _wpsf__( 'No changes were made as no option was selected' );

		$sForm = $oReq->post( 'wizard-step' );
		if ( $sForm == 'optin_badge' ) {
			$sInput = $oReq->post( 'BadgeOption' );

			if ( !empty( $sInput ) ) {
				$bEnabled = $sInput === 'Y';
				$oModule->setIsDisplayPluginBadge( $bEnabled );
				$bSuccess = true;
				$sMessage = _wpsf__( 'Preferences have been saved.' );
			}
		}
		else if ( $sForm == 'optin_usage' ) {
			$sInput = $oReq->post( 'AnonymousOption' );

			if ( !empty( $sInput ) ) {
				$bEnabled = $sInput === 'Y';
				$oModule->setPluginTrackingPermission( $bEnabled );
				$bSuccess = true;
				$sMessage = _wpsf__( 'Preferences have been saved.' );
			}
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $bSuccess )
			->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardAddSearchItem() {
		$sInput = esc_js( esc_html( $this->loadRequest()->post( 'SearchItem' ) ) );

		$aItems = $this->getGdprSearchItems();

		if ( !empty( $sInput ) ) {
			if ( $sInput === 'CLEAR' ) {
				$aItems = array();
			}
			else {
				$aItems[] = $sInput;
				if ( $this->loadDP()->validEmail( $sInput ) ) {
					$oUser = $this->loadWpUsers()->getUserByEmail( $sInput );
					if ( !is_null( $oUser ) ) {
						$aItems[] = $oUser->user_login;
					}
				}
				else {
					$sUsername = sanitize_user( $sInput );
					if ( !empty( $sUsername ) ) {
						$oUser = $this->loadWpUsers()->getUserByUsername( $sUsername );
						if ( $oUser instanceof WP_User ) {
							$aItems[] = $oUser->user_email;
						}
					}
				}
			}
		}

		$aItems = $this->setGdprSearchItems( $aItems );

		$sSearchList = 'Search list is empty';
		if ( !empty( $aItems ) ) {
			$sItems = implode( '</li><li>', $aItems );
			$sSearchList = sprintf( '<ul><li>%s</li></ul>', $sItems );
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( true )
			->setData( [ 'sSearchList' => $sSearchList ] )
			->setMessageText( _wpsf__( 'Search item added.' ) );
	}

	private function wizardConfirmDelete() {
		$bDelete = $this->loadRequest()->post( 'ConfirmDelete' ) === 'Y';
		if ( $bDelete ) {
			/** @var ICWP_WPSF_Processor_AuditTrail $oProc */
			$oProc = $this->getPluginCon()->getModule( 'audit_trail' )->getProcessor();
			$oDeleter = $oProc->getDbHandler()->getQueryDeleter();
			foreach ( $this->getGdprSearchItems() as $sItem ) {
				$oDeleter->reset()
						 ->addWhereSearch( 'wp_username', $sItem )
						 ->all();
				$oDeleter->reset()
						 ->addWhereSearch( 'message', $sItem )
						 ->all();
			}
			$sMessage = _wpsf__( 'All entries were deleted' );
		}
		else {
			$sMessage = _wpsf__( 'Please check the box to confirm deletion.' );
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $bDelete )
			->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardCommentsFilter() {

		$sInput = $this->loadRequest()->post( 'CommentsFilterOption' );
		$bSuccess = false;
		$sMessage = _wpsf__( 'No changes were made as no option was selected' );

		if ( !empty( $sInput ) ) {
			$bEnabled = $sInput === 'Y';

			/** @var ICWP_WPSF_FeatureHandler_CommentsFilter $oModule */
			$oModule = $this->getPluginCon()->getModule( 'comments_filter' );
			if ( $bEnabled ) { // we don't disable the whole module
				$oModule->setIsMainFeatureEnabled( true );
			}
			$oModule->setEnabledGasp( $bEnabled )
					->savePluginOptions();

			$bSuccess = $oModule->isEnabledGaspCheck() === $bEnabled;
			if ( $bSuccess ) {
				$sMessage = sprintf( '%s has been %s.', _wpsf__( 'Comment SPAM Protection' ),
					$bEnabled ? _wpsf__( 'Enabled' ) : _wpsf__( 'Disabled' )
				);
			}
			else {
				$sMessage = sprintf( _wpsf__( '%s setting could not be changed at this time.' ), _wpsf__( 'Comment SPAM Protection' ) );
			}
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $bSuccess )
			->setMessageText( $sMessage );
	}

	/**
	 * @return array
	 */
	private function getGdprSearchItems() {
		$aItems = $this->loadWp()
					   ->getTransient( $this->getPluginCon()->prefix( 'gdpr-items' ) );
		if ( !is_array( $aItems ) ) {
			$aItems = array();
		}
		return $aItems;
	}

	/**
	 * @param array $aItems
	 * @return array
	 */
	private function setGdprSearchItems( $aItems ) {
		if ( !is_array( $aItems ) ) {
			$aItems = array();
		}
		$aItems = array_filter( array_unique( $aItems ) );
		$this->loadWp()
			 ->setTransient(
				 $this->getPluginCon()->prefix( 'gdpr-items' ),
				 $aItems,
				 MINUTE_IN_SECONDS*10
			 );
		return $aItems;
	}

	/**
	 * @return array[]
	 */
	private function runGdprSearch() {
		/** @var ICWP_WPSF_Processor_AuditTrail $oProc */
		$oProc = $this->getPluginCon()->getModule( 'audit_trail' )->getProcessor();
		$oFinder = $oProc->getDbHandler()
						 ->getQuerySelector()
						 ->setResultsAsVo( false );

		$aItems = array();
		foreach ( $this->getGdprSearchItems() as $sItem ) {
			try {
				$aResults = $oFinder->reset()
									->addWhereSearch( 'wp_username', $sItem )
									->query()
							+
							$oFinder->reset()
									->addWhereSearch( 'message', $sItem )
									->query();
			}
			catch ( Exception $oE ) {
				$aResults = array();
			}
//			$aResults = array_intersect_key( $aResults, array_flip( [ 'wp_username', 'message' ] ) );
			$aItems[ $sItem ] = array(
				'entries' => $aResults,
				'count'   => count( $aResults ),
				'has'     => count( $aResults ) > 0,
			);
		}
		return $aItems;
	}
}