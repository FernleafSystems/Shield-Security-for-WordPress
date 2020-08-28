<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\FindSourceFromIp;

/**
 * Class ICWP_WPSF_Processor_LoginProtect_Wizard
 */
class ICWP_WPSF_Wizard_Plugin extends ICWP_WPSF_Wizard_BaseWpsf {

	/**
	 * @return string
	 */
	protected function getPageTitle() {
		return sprintf( __( '%s Welcome Wizard', 'wp-simple-firewall' ), $this->getCon()->getHumanName() );
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
		return [
			'start',
			'search',
			'results',
			'finished',
		];
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Import() {
		return [
			'start',
			'import',
			'finished',
		];
	}

	/**
	 * @return string[]
	 */
	private function determineWizardSteps_Welcome() {
		$con = $this->getCon();

		$aStepsSlugs = [
			'welcome',
			'ip_detect'
		];

		if ( $con->isPremiumActive() ) {
			$aStepsSlugs[] = 'import';
		}

		if ( !$con->getModule( 'admin_access_restriction' )->isModuleEnabled() ) {
			$aStepsSlugs[] = 'admin_access_restriction';
		}

		/** @var ICWP_WPSF_FeatureHandler_AuditTrail $mod */
		$mod = $con->getModule( 'audit_trail' );
		if ( !$mod->isModuleEnabled() ) {
			$aStepsSlugs[] = 'audit_trail';
		}

		if ( !$con->getModule_IPs()->isModuleEnabled() ) {
			$aStepsSlugs[] = 'ips';
		}

		$mod = $con->getModule_LoginGuard();
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options $oOpts */
		$oOpts = $mod->getOptions();
		if ( !( $mod->isModuleEnabled() && $oOpts->isEnabledGaspCheck() ) ) {
			$aStepsSlugs[] = 'login_protect';
		}

		$modComm = $con->getModule_Comments();
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options $optsComm */
		$optsComm = $modComm->getOptions();
		if ( !( $modComm->isModuleEnabled() && $optsComm->isEnabledGaspCheck() ) ) {
			$aStepsSlugs[] = 'comments_filter';
		}

		$aStepsSlugs[] = 'how_shield_works';
		$aStepsSlugs[] = 'optin';

		if ( !$con->isPremiumActive() ) {
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
		$oConn = $this->getCon();

		$aAdditional = [];

		$sCurrentWiz = $this->getWizardSlug();

		if ( $sCurrentWiz == 'welcome' ) {

			switch ( $sStep ) {
				case 'ip_detect':
					$aAdditional = [
						'hrefs' => [
							'visitor_ip' => 'https://shsec.io/visitorip',
						]
					];
					break;
				case 'license':
					break;
				case 'import':
					$aAdditional = [
						'hrefs' => [
							'blog_importexport' => 'https://shsec.io/av'
						],
						'imgs'  => [
							'shieldnetworkmini' => $oConn->getPluginUrl_Image( 'shield/shieldnetworkmini.png' ),
						]
					];
					break;

				case 'optin':
					$oUser = Services::WpUsers()->getCurrentWpUser();
					$aAdditional = [
						'vars'  => [
							'name'       => $oUser->first_name,
							'user_email' => $oUser->user_email
						],
						'hrefs' => [
							'privacy_policy' => $this->getOptions()->getDef( 'href_privacy_policy' )
						],
					];
					break;

				case 'thankyou':
					break;

				case 'how_shield_works':
					$aAdditional = [
						'imgs'     => [
							'how_shield_works' => $oConn->getPluginUrl_Image( 'wizard/general-shield_where.png' ),
							'modules'          => $oConn->getPluginUrl_Image( 'wizard/general-shield_modules.png' ),
							'options'          => $oConn->getPluginUrl_Image( 'wizard/general-shield_options.png' ),
							'wizards'          => $oConn->getPluginUrl_Image( 'wizard/general-shield_wizards.png' ),
							'help'             => $oConn->getPluginUrl_Image( 'wizard/general-shield_help.png' ),
							'actions'          => $oConn->getPluginUrl_Image( 'wizard/general-shield_actions.png' ),
							'option_help'      => $oConn->getPluginUrl_Image( 'wizard/general-option_help.png' ),
							'module_onoff'     => $oConn->getPluginUrl_Image( 'wizard/general-module_onoff.png' ),
						],
						'headings' => [
							'how_shield_works' => __( 'Where to find Shield', 'wp-simple-firewall' ),
							'modules'          => __( 'Accessing Each Module', 'wp-simple-firewall' ),
							'options'          => __( 'Accessing Options', 'wp-simple-firewall' ),
							'wizards'          => __( 'Launching Wizards', 'wp-simple-firewall' ),
							'help'             => __( 'Finding Help', 'wp-simple-firewall' ),
							'actions'          => __( 'Actions (not Options)', 'wp-simple-firewall' ),
							'option_help'      => __( 'Help For Each Option', 'wp-simple-firewall' ),
							'module_onoff'     => __( 'Module On/Off Switch', 'wp-simple-firewall' ),
						],
						'captions' => [
							'how_shield_works' => sprintf( __( "You'll find the main %s settings in the left-hand WordPress menu.", 'wp-simple-firewall' ), $oConn->getHumanName() ),
							'modules'          => __( 'Shield is split up into independent modules for accessing the options of each feature.', 'wp-simple-firewall' ),
							'options'          => __( 'When you load a module, you can access the options by clicking on the Options Panel link.', 'wp-simple-firewall' ),
							'wizards'          => __( 'Launch helpful walk-through wizards for modules that have them.', 'wp-simple-firewall' ),
							'help'             => __( 'Each module also has a brief overview help section - there is more in-depth help available.', 'wp-simple-firewall' ),
							'actions'          => __( 'Certain modules have extra actions and features, e.g. Audit Trail Viewer.', 'wp-simple-firewall' )
												  .' '.__( 'Note: Not all modules have the actions section', 'wp-simple-firewall' ),
							'module_onoff'     => __( 'Each module has an Enable/Disable checkbox to turn on/off all processing for that module', 'wp-simple-firewall' ),
							'option_help'      => __( 'To help you understand each option, most of them have a more info link, and/or a blog link, to read more', 'wp-simple-firewall' ),
						],
					];
					break;

				default:
					break;
			}
		}
		elseif ( $sCurrentWiz == 'importexport' ) {
			switch ( $sStep ) {
				case 'import':
					$aAdditional = [
						'hrefs' => [
							'blog_importexport' => 'https://shsec.io/av'
						],
						'imgs'  => [
							'shieldnetworkmini' => $oConn->getPluginUrl_Image( 'shield/shieldnetworkmini.png' ),
						]
					];
					break;
				case 'results': //gdpr results

					$aAdditional = [];
					break;

				default:
					break;
			}
		}
		elseif ( $sCurrentWiz == 'gdpr' ) {
			switch ( $sStep ) {

				case 'results':
					$aItems = $this->getGdprSearchItems();
					$bHasSearchItems = !empty( $aItems );
					$aResults = $this->runGdprSearch();

					$nTotal = 0;
					foreach ( $aResults as $aResult ) {
						$nTotal += $aResult[ 'count' ];
					}

					$aAdditional = [
						'flags' => [
							'has_search_items' => $bHasSearchItems
						],
						'data'  => [
							'result'      => $this->runGdprSearch(),
							'count_total' => $nTotal,
							'has_results' => $nTotal > 0,
						]
					];
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
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		$oIps = Services::IP();
		$sIp = Services::Request()->post( 'ip' );

		$oResponse = new \FernleafSystems\Utilities\Response();
		$oResponse->setSuccessful( false );
		if ( empty( $sIp ) ) {
			$sMessage = __( 'IP address was empty.', 'wp-simple-firewall' );
		}
		elseif ( !$oIps->isValidIp_PublicRemote( $sIp ) ) {
			$sMessage = __( "IP address wasn't a valid public IP address.", 'wp-simple-firewall' );
		}
//		else if ( $oIps->getIpVersion( $sIp ) != 4 ) {
//			$sMessage = 'The IP address supplied was not a valid IP address.';
//		}
		else {
			$sSource = ( new FindSourceFromIp() )->run( Services::Request()->post( 'ip' ) );
			if ( empty( $sSource ) ) {
				$sMessage = __( "The address source couldn't be found from this IP.", 'wp-simple-firewall' );
			}
			else {
				$oMod = $this->getCon()
							 ->getModule_Plugin();
				$oOpts->setVisitorAddressSource( $sSource );
				$oMod->saveModOptions();
				$oResponse->setSuccessful( true );
				$sMessage = __( 'Success!', 'wp-simple-firewall' ).' '
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

		/** @var \ICWP_WPSF_FeatureHandler_License $oModule */
		$oModule = $this->getCon()->getModule( 'license' );
		try {
			$bSuccess = $oModule->getLicenseHandler()
								->verify( true )
								->hasValidWorkingLicense();
			if ( $bSuccess ) {
				$sMessage = __( 'License was found and successfully installed.', 'wp-simple-firewall' );
			}
			else {
				$sMessage = __( 'License could not be found.', 'wp-simple-firewall' );
			}
		}
		catch ( Exception $oE ) {
			$sMessage = __( $oE->getMessage(), 'wp-simple-firewall' );
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $bSuccess )
			->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardImportOptions() {
		$oReq = Services::Request();

		$sMasterSiteUrl = $oReq->post( 'MasterSiteUrl' );
		$sSecretKey = $oReq->post( 'MasterSiteSecretKey' );
		$bEnabledNetwork = $oReq->post( 'ShieldNetworkCheck' ) === 'Y';

		try {
			$nCode = ( new Plugin\Lib\ImportExport\Import() )
				->setMod( $this->getMod() )
				->fromSite( $sMasterSiteUrl, $sSecretKey, $bEnabledNetwork );
		}
		catch ( Exception $oE ) {
			$sSiteResponse = $oE->getMessage();
			$nCode = $oE->getCode();
		}

		$aErrors = [
			__( 'Options imported successfully to your site.', 'wp-simple-firewall' ), // success
			__( 'Secret key was empty.', 'wp-simple-firewall' ),
			__( 'Secret key was not 40 characters long.', 'wp-simple-firewall' ),
			__( 'Secret key contains invalid characters - it should be letters and numbers only.', 'wp-simple-firewall' ),
			__( 'Source site URL could not be parsed correctly.', 'wp-simple-firewall' ),
			__( 'Could not parse the response from the site.', 'wp-simple-firewall' )
			.' '.__( 'Check the secret key is correct for the remote site.', 'wp-simple-firewall' ),
			__( 'Failure response returned from the site.', 'wp-simple-firewall' ),
			sprintf( __( 'Remote site responded with - %s', 'wp-simple-firewall' ), $sSiteResponse ),
			__( 'Data returned from the site was empty.', 'wp-simple-firewall' )
		];

		$sMessage = isset( $aErrors[ $nCode ] ) ? $aErrors[ $nCode ] : 'Unknown Error';

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $nCode === 0 )
			->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardSecurityAdmin() {
		$oReq = Services::Request();
		$sKey = $oReq->post( 'AccessKey' );
		$sConfirm = $oReq->post( 'AccessKeyConfirm' );

		$bSuccess = false;
		if ( empty( $sKey ) ) {
			$sMessage = __( "Security Admin PIN was empty.", 'wp-simple-firewall' );
		}
		elseif ( $sKey != $sConfirm ) {
			$sMessage = __( "Security PINs don't match.", 'wp-simple-firewall' );
		}
		else {
			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oModule */
			$oModule = $this->getCon()->getModule( 'admin_access_restriction' );
			try {
				$oModule->setNewAccessKeyManually( $sKey )
						->setSecurityAdminStatusOnOff( true );
				$bSuccess = true;
				$sMessage = __( 'Security Admin PIN setup was successful.', 'wp-simple-firewall' );
			}
			catch ( Exception $oE ) {
				$sMessage = __( $oE->getMessage(), 'wp-simple-firewall' );
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

		$sInput = Services::Request()->post( 'AuditTrailOption' );
		$bSuccess = false;
		$sMessage = __( 'No changes were made as no option was selected', 'wp-simple-firewall' );

		if ( !empty( $sInput ) ) {
			$bEnabled = $sInput === 'Y';

			$oMod = $this->getCon()
						 ->getModule_AuditTrail();
			$oMod->setIsMainFeatureEnabled( $bEnabled );
			$oMod->saveModOptions();

			$bSuccess = $oMod->isModuleEnabled() === $bEnabled;
			if ( $bSuccess ) {
				$sMessage = sprintf( '%s has been %s.', __( 'Audit Trail', 'wp-simple-firewall' ),
					$oMod->isModuleEnabled() ? __( 'Enabled', 'wp-simple-firewall' ) : __( 'Disabled', 'wp-simple-firewall' )
				);
			}
			else {
				$sMessage = sprintf( __( '%s setting could not be changed at this time.', 'wp-simple-firewall' ), __( 'Audit Trail', 'wp-simple-firewall' ) );
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

		$sInput = Services::Request()->post( 'IpManagerOption' );
		$bSuccess = false;
		$sMessage = __( 'No changes were made as no option was selected', 'wp-simple-firewall' );

		if ( !empty( $sInput ) ) {
			$bEnabled = $sInput === 'Y';

			$oMod = $this->getCon()
						 ->getModule_IPs();
			$oMod->setIsMainFeatureEnabled( $bEnabled );
			$oMod->saveModOptions();

			$bSuccess = $oMod->isModuleEnabled() === $bEnabled;
			if ( $bSuccess ) {
				$sMessage = sprintf( '%s has been %s.', __( 'IP Manager', 'wp-simple-firewall' ),
					$oMod->isModuleEnabled() ? __( 'Enabled', 'wp-simple-firewall' ) : __( 'Disabled', 'wp-simple-firewall' )
				);
			}
			else {
				$sMessage = sprintf( __( '%s setting could not be changed at this time.', 'wp-simple-firewall' ), __( 'IP Manager', 'wp-simple-firewall' ) );
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
		$oMod = $this->getCon()->getModule_LoginGuard();
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options $oOpts */
		$oOpts = $oMod->getOptions();

		$sInput = Services::Request()->post( 'LoginProtectOption' );
		$bSuccess = false;
		$sMessage = __( 'No changes were made as no option was selected', 'wp-simple-firewall' );

		if ( !empty( $sInput ) ) {
			$bEnabled = $sInput === 'Y';

			if ( $bEnabled ) { // we don't disable the whole module
				$oMod->setIsMainFeatureEnabled( true );
			}
			$oMod->setEnabledGaspCheck( $bEnabled );
			$oMod->saveModOptions();

			$bSuccess = $oOpts->isEnabledGaspCheck() === $bEnabled;
			if ( $bSuccess ) {
				$sMessage = sprintf( '%s has been %s.', __( 'Login Guard', 'wp-simple-firewall' ),
					$bEnabled ? __( 'Enabled', 'wp-simple-firewall' ) : __( 'Disabled', 'wp-simple-firewall' )
				);
			}
			else {
				$sMessage = sprintf( __( '%s setting could not be changed at this time.', 'wp-simple-firewall' ), __( 'Login Guard', 'wp-simple-firewall' ) );
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
		$oReq = Services::Request();
		$oMod = $this->getCon()->getModule_Plugin();
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();

		$bSuccess = false;
		$sMessage = __( 'No changes were made as no option was selected', 'wp-simple-firewall' );

		$sForm = $oReq->post( 'wizard-step' );
		if ( $sForm == 'optin_badge' ) {
			$sInput = $oReq->post( 'BadgeOption' );

			if ( !empty( $sInput ) ) {
				$bEnabled = $sInput === 'Y';
				$oMod->getPluginBadgeCon()->setIsDisplayPluginBadge( $bEnabled );
				$bSuccess = true;
				$sMessage = __( 'Preferences have been saved.', 'wp-simple-firewall' );
			}
		}
		elseif ( $sForm == 'optin_usage' ) {
			$sInput = $oReq->post( 'AnonymousOption' );

			if ( !empty( $sInput ) ) {
				$bEnabled = $sInput === 'Y';
				$oOpts->setPluginTrackingPermission( $bEnabled );
				$bSuccess = true;
				$sMessage = __( 'Preferences have been saved.', 'wp-simple-firewall' );
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
		$sInput = esc_js( esc_html( Services::Request()->post( 'SearchItem' ) ) );

		$aItems = $this->getGdprSearchItems();

		if ( !empty( $sInput ) ) {
			if ( $sInput === 'CLEAR' ) {
				$aItems = [];
			}
			else {
				$aItems[] = $sInput;
				if ( Services::Data()->validEmail( $sInput ) ) {
					$oUser = Services::WpUsers()->getUserByEmail( $sInput );
					if ( !is_null( $oUser ) ) {
						$aItems[] = $oUser->user_login;
					}
				}
				else {
					$sUsername = sanitize_user( $sInput );
					if ( !empty( $sUsername ) ) {
						$oUser = Services::WpUsers()->getUserByUsername( $sUsername );
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
			->setMessageText( __( 'Search item added.', 'wp-simple-firewall' ) );
	}

	private function wizardConfirmDelete() {
		$bDelete = Services::Request()->post( 'ConfirmDelete' ) === 'Y';
		if ( $bDelete ) {
			$oDeleter = $this->getCon()
							 ->getModule_AuditTrail()
							 ->getDbHandler_AuditTrail()
							 ->getQueryDeleter();
			foreach ( $this->getGdprSearchItems() as $sItem ) {
				$oDeleter->reset()
						 ->addWhereSearch( 'wp_username', $sItem )
						 ->all();
				$oDeleter->reset()
						 ->addWhereSearch( 'message', $sItem )
						 ->all();
			}
			$sMessage = __( 'All entries were deleted', 'wp-simple-firewall' );
		}
		else {
			$sMessage = __( 'Please check the box to confirm deletion.', 'wp-simple-firewall' );
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $bDelete )
			->setMessageText( $sMessage );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardCommentsFilter() {

		$sInput = Services::Request()->post( 'CommentsFilterOption' );
		$bSuccess = false;
		$sMessage = __( 'No changes were made as no option was selected', 'wp-simple-firewall' );

		if ( !empty( $sInput ) ) {
			$bEnabled = $sInput === 'Y';

			$modComm = $this->getCon()->getModule_Comments();
			if ( $bEnabled ) { // we don't disable the whole module
				$modComm->setIsMainFeatureEnabled( true );
			}
			$modComm->setEnabledGasp( $bEnabled );
			$modComm->saveModOptions();

			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options $optsComm */
			$optsComm = $modComm->getOptions();
			$bSuccess = $optsComm->isEnabledGaspCheck() === $bEnabled;
			if ( $bSuccess ) {
				$sMessage = sprintf( '%s has been %s.', __( 'Comment SPAM Protection', 'wp-simple-firewall' ),
					$bEnabled ? __( 'Enabled', 'wp-simple-firewall' ) : __( 'Disabled', 'wp-simple-firewall' )
				);
			}
			else {
				$sMessage = sprintf( __( '%s setting could not be changed at this time.', 'wp-simple-firewall' ), __( 'Comment SPAM Protection', 'wp-simple-firewall' ) );
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
		$aItems = Services::WpGeneral()->getTransient( $this->getCon()->prefix( 'gdpr-items' ) );
		if ( !is_array( $aItems ) ) {
			$aItems = [];
		}
		return $aItems;
	}

	/**
	 * @param array $aItems
	 * @return array
	 */
	private function setGdprSearchItems( $aItems ) {
		if ( !is_array( $aItems ) ) {
			$aItems = [];
		}
		$aItems = array_filter( array_unique( $aItems ) );
		Services::WpGeneral()
				->setTransient(
					$this->getCon()->prefix( 'gdpr-items' ),
					$aItems,
					MINUTE_IN_SECONDS*10
				);
		return $aItems;
	}

	/**
	 * @return array[]
	 */
	private function runGdprSearch() {
		$oFinder = $this->getCon()
						->getModule_AuditTrail()
						->getDbHandler_AuditTrail()
						->getQuerySelector()
						->setResultsAsVo( false );

		$aItems = [];
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
			catch ( \Exception $oE ) {
				$aResults = [];
			}
//			$aResults = array_intersect_key( $aResults, array_flip( [ 'wp_username', 'message' ] ) );
			$aItems[ $sItem ] = [
				'entries' => $aResults,
				'count'   => count( $aResults ),
				'has'     => count( $aResults ) > 0,
			];
		}
		return $aItems;
	}
}