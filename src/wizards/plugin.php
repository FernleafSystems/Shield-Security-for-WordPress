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
	protected function getPageTitle() :string {
		return sprintf( __( '%s Welcome Wizard', 'wp-simple-firewall' ), $this->getCon()->getHumanName() );
	}

	/**
	 * @param string $step
	 * @return \FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( string $step ) {
		switch ( $step ) {

			case 'ip_detect':
				$response = $this->wizardIpDetect();
				break;

			case 'license':
				$response = $this->wizardLicense();
				break;

			case 'import':
				$response = $this->wizardImportOptions();
				break;

			case 'admin_access_restriction':
				$response = $this->wizardSecurityAdmin();
				break;

			case 'audit_trail':
				$response = $this->wizardAuditTrail();
				break;

			case 'ips':
				$response = $this->wizardIps();
				break;

			case 'comments_filter':
				$response = $this->wizardCommentsFilter();
				break;

			case 'login_protect':
				$response = $this->wizardLoginProtect();
				break;

			case 'optin_usage':
			case 'optin_badge':
			case 'optins':
				$response = $this->wizardOptin();
				break;

			case 'add-search-item':
				$response = $this->wizardAddSearchItem();
				break;

			case 'confirm-results-delete':
				$response = $this->wizardConfirmDelete();
				break;

			default:
				$response = parent::processWizardStep( $step );
				break;
		}
		return $response;
	}

	/**
	 * @return string[]
	 * @throws Exception
	 */
	protected function determineWizardSteps() :array {
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

		$mod = $con->getModule_AuditTrail();
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
	 * @param string $step
	 * @return array
	 */
	protected function getRenderData_SlideExtra( $step ) {
		$con = $this->getCon();

		$additional = [];

		$sCurrentWiz = $this->getWizardSlug();

		if ( $sCurrentWiz == 'welcome' ) {

			switch ( $step ) {
				case 'welcome':
					$urlBuilder = $con->urls;
					$additional = [
						'imgs'    => [
							'plugin_banner' => $urlBuilder->forImage( 'banner-1500x500-transparent.png' ),
						],
						'vars'    => [
							'video_id' => '267962208'
						],
						'strings' => [
							'slide_title' => 'Welcome To Shield Security for WordPress',
							'next_button' => 'Start',
						],
					];
					break;
				case 'ip_detect':
					$additional = [
						'hrefs'   => [
							'visitor_ip' => 'https://shsec.io/visitorip',
						],
						'vars'    => [
							'video_id' => '269189603'
						],
						'strings' => [
							'slide_title' => 'Accurate Visitor IP Detection',
						],
					];
					break;
				case 'license':
					break;
				case 'import':
					$additional = [
						'hrefs' => [
							'blog_importexport' => 'https://shsec.io/av'
						],
						'imgs'  => [
							'shieldnetworkmini' => $con->urls->forImage( 'shield/shieldnetworkmini.png' ),
						]
					];
					break;

				case 'optin':
					$oUser = Services::WpUsers()->getCurrentWpUser();
					$additional = [
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
					$additional = [
						'imgs'     => [
							'how_shield_works' => $con->urls->forImage( 'wizard/general-shield_where.png' ),
							'modules'          => $con->urls->forImage( 'wizard/general-shield_modules.png' ),
							'options'          => $con->urls->forImage( 'wizard/general-shield_options.png' ),
							'wizards'          => $con->urls->forImage( 'wizard/general-shield_wizards.png' ),
							'help'             => $con->urls->forImage( 'wizard/general-shield_help.png' ),
							'actions'          => $con->urls->forImage( 'wizard/general-shield_actions.png' ),
							'option_help'      => $con->urls->forImage( 'wizard/general-option_help.png' ),
							'module_onoff'     => $con->urls->forImage( 'wizard/general-module_onoff.png' ),
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
							'how_shield_works' => sprintf( __( "You'll find the main %s settings in the left-hand WordPress menu.", 'wp-simple-firewall' ), $con->getHumanName() ),
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
			switch ( $step ) {
				case 'import':
					$additional = [
						'hrefs' => [
							'blog_importexport' => 'https://shsec.io/av'
						],
						'imgs'  => [
							'shieldnetworkmini' => $con->urls->forImage( 'shield/shieldnetworkmini.png' ),
						]
					];
					break;
				case 'results': //gdpr results

					$additional = [];
					break;

				default:
					break;
			}
		}
		elseif ( $sCurrentWiz == 'gdpr' ) {
			switch ( $step ) {

				case 'results':
					$aItems = $this->getGdprSearchItems();
					$bHasSearchItems = !empty( $aItems );
					$aResults = $this->runGdprSearch();

					$nTotal = 0;
					foreach ( $aResults as $aResult ) {
						$nTotal += $aResult[ 'count' ];
					}

					$additional = [
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

		if ( empty( $additional ) ) {
			$additional = parent::getRenderData_SlideExtra( $step );
		}

		if ( !empty( $additional[ 'vars' ][ 'video_id' ] ) ) {
			$additional[ 'imgs' ][ 'video_thumb' ] = $this->getVideoThumbnailUrl( $additional[ 'vars' ][ 'video_id' ] );
		}

		if ( empty( $additional[ 'vars' ][ 'step_slug' ] ) ) {
			$additional[ 'vars' ][ 'step' ] = $step;
		}

		return $additional;
	}

	/**
	 * @see https://stackoverflow.com/questions/1361149/get-img-thumbnails-from-vimeo
	 * @param string $videoID
	 */
	private function getVideoThumbnailUrl( $videoID ) {
		$raw = Services::HttpRequest()
					   ->getContent( sprintf( 'https://vimeo.com/api/v2/video/%s.json', $videoID ) );
		return empty( $raw ) ? '' : json_decode( $raw, true )[ 0 ][ 'thumbnail_large' ];
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardIpDetect() {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();
		$srvIP = Services::IP();
		$ip = trim( Services::Request()->post( 'ip', '' ) );
		$success = false;

		$response = new \FernleafSystems\Utilities\Response();
		if ( empty( $ip ) ) {
			$msg = __( 'IP address was empty.', 'wp-simple-firewall' );
		}
		elseif ( !$srvIP->isValidIp_PublicRemote( $ip ) ) {
			$msg = __( "IP address wasn't a valid public IP address.", 'wp-simple-firewall' );
		}
		else {
			$source = ( new FindSourceFromIp() )->run( Services::Request()->post( 'ip' ) );
			if ( empty( $source ) ) {
				$msg = __( "Sorry, we couldn't find an address source from this IP.", 'wp-simple-firewall' );
			}
			else {
				$success = true;
				$opts->setVisitorAddressSource( $source );
				$msg = __( 'Success!', 'wp-simple-firewall' ).' '
					   .sprintf( '"%s" was found to be the best source of visitor IP addresses for your site.', $source );
			}
		}

		$this->getCon()->getModule_Plugin()->saveModOptions();
		$response->setSuccessful( $success );

		return $response->setMessageText( $msg );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardLicense() {

		$success = false;

		$mod = $this->getCon()->getModule_License();
		try {
			$success = $mod->getLicenseHandler()
						   ->verify( true )
						   ->hasValidWorkingLicense();
			if ( $success ) {
				$msg = __( 'License was found and successfully installed.', 'wp-simple-firewall' );
			}
			else {
				$msg = __( 'License could not be found.', 'wp-simple-firewall' );
			}
		}
		catch ( Exception $e ) {
			$msg = __( $e->getMessage(), 'wp-simple-firewall' );
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $success )
			->setMessageText( $msg );
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
		catch ( Exception $e ) {
			$sSiteResponse = $e->getMessage();
			$nCode = $e->getCode();
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
		$req = Services::Request();
		$pin = $req->post( 'sec_admin_key' );
		$confirm = $req->post( 'AccessKeyConfirm' );

		$success = false;
		if ( empty( $pin ) ) {
			$msg = __( "Security Admin PIN was empty.", 'wp-simple-firewall' );
		}
		elseif ( $pin != $confirm ) {
			$msg = __( "Security PINs don't match.", 'wp-simple-firewall' );
		}
		else {
			$mod = $this->getCon()->getModule_SecAdmin();
			try {
				$mod->setNewPinManually( $pin );
				$success = true;
				$msg = __( 'Security Admin PIN setup was successful.', 'wp-simple-firewall' );
			}
			catch ( \Exception $e ) {
				$msg = __( $e->getMessage(), 'wp-simple-firewall' );
			}
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $success )
			->setMessageText( $msg );
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
		$mod = $this->getCon()->getModule_LoginGuard();
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options $opts */
		$opts = $mod->getOptions();

		$sInput = Services::Request()->post( 'LoginProtectOption' );
		$success = false;
		$msg = __( 'No changes were made as no option was selected', 'wp-simple-firewall' );

		if ( !empty( $sInput ) ) {
			$enabled = $sInput === 'Y';

			if ( $enabled ) { // we don't disable the whole module
				$mod->setIsMainFeatureEnabled( true );
			}
			$mod->setEnabledGaspCheck( $enabled );
			$mod->saveModOptions();

			$success = $opts->isEnabledGaspCheck() === $enabled;
			if ( $success ) {
				$msg = sprintf( '%s has been %s.', __( 'Login Guard', 'wp-simple-firewall' ),
					$enabled ? __( 'Enabled', 'wp-simple-firewall' ) : __( 'Disabled', 'wp-simple-firewall' )
				);
			}
			else {
				$msg = sprintf( __( '%s setting could not be changed at this time.', 'wp-simple-firewall' ), __( 'Login Guard', 'wp-simple-firewall' ) );
			}
		}
		else {
			// skip
			$success = true;
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $success )
			->setMessageText( $msg );
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardOptin() {
		$oReq = Services::Request();
		$oMod = $this->getCon()->getModule_Plugin();
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();

		$bSuccess = true;
		$sMessage = __( 'No changes were made as no option was selected', 'wp-simple-firewall' );

		$sInput = $oReq->post( 'BadgeOption' );
		if ( !empty( $sInput ) ) {
			$bEnabled = $sInput === 'Y';
			$oMod->getPluginBadgeCon()->setIsDisplayPluginBadge( $bEnabled );
			$bSuccess = true;
		}

		$sInput = $oReq->post( 'AnonymousOption' );
		if ( !empty( $sInput ) ) {
			$bEnabled = $sInput === 'Y';
			$oOpts->setPluginTrackingPermission( $bEnabled );
			$bSuccess = true;
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
					$username = sanitize_user( $sInput );
					if ( !empty( $username ) ) {
						$oUser = Services::WpUsers()->getUserByUsername( $username );
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
		else {
			// skip
			$bSuccess = true;
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
			catch ( \Exception $e ) {
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