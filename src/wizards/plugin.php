<?php

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\FindSourceFromIp;

class ICWP_WPSF_Wizard_Plugin extends ICWP_WPSF_Wizard_BaseWpsf {

	/**
	 * @return string
	 */
	protected function getPageTitle() :string {
		return sprintf( __( '%s Welcome Wizard', 'wp-simple-firewall' ), $this->getCon()->getHumanName() );
	}

	/**
	 * @param string $step
	 * @return StdResponse|\FernleafSystems\Utilities\Response|null
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

			case 'login_protect':
				$response = $this->wizardLoginProtect();
				break;

			case 'comments_filter':
				$response = $this->wizardCommentsFilter();
				break;

			case 'plugin_badge':
				$response = $this->wizardPluginSecurityBadge();
				break;

			case 'plugin_telemetry':
				$response = $this->wizardPluginTelemetry();
				break;

			case 'optin_usage':
			case 'optin_badge':
			case 'optins':
				$response = $this->wizardOptin();
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

		$stepsSlugs = [
			'welcome',
			'ip_detect'
		];

		if ( $con->isPremiumActive() ) {
			$stepsSlugs[] = 'import';
		}

		if ( !$con->getModule( 'admin_access_restriction' )->isModuleEnabled() ) {
			$stepsSlugs[] = 'admin_access_restriction';
		}

		$mod = $con->getModule_AuditTrail();
		if ( !$mod->isModuleEnabled() ) {
			$stepsSlugs[] = 'audit_trail';
		}

		if ( !$con->getModule_IPs()->isModuleEnabled() ) {
//			$stepsSlugs[] = 'ips';
		}

		$stepsSlugs[] = 'login_protect';
		$stepsSlugs[] = 'comments_filter';
		$stepsSlugs[] = 'plugin_badge';
//		$stepsSlugs[] = 'plugin_telemetry';
		$stepsSlugs[] = 'free_trial';
		$stepsSlugs[] = 'optin';

		if ( !$con->isPremiumActive() ) {
			$stepsSlugs[] = 'import';
		}

		$stepsSlugs[] = 'thankyou';
		return $stepsSlugs;
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

				case 'login_protect':
					$additional = [
						'vars'    => [
							'video_id' => '269191603'
						],
						'strings' => [
							'slide_title' => 'Brute Force Login Protection',
						],
					];
					break;

				case 'comments_filter':
					$additional = [
						'vars'    => [
							'video_id' => '269193270'
						],
						'strings' => [
							'slide_title' => 'Block 100% Comment SPAM by Bots - no CAPTCHAs (really!)',
						],
					];
					break;

				case 'plugin_badge':
					$additional = [
						'vars'    => [
							'video_id' => '552430272'
						],
						'strings' => [
							'slide_title' => 'Demonstrate To Visitors That You Take Security Seriously',
						],
					];
					break;

				case 'plugin_telemetry':
					$additional = [
						'hrefs'   => [
							'privacy_policy' => $this->getOptions()->getDef( 'href_privacy_policy' ),
						],
						'vars'    => [
							'email' => Services::WpUsers()->getCurrentWpUser()->user_email
						],
						'strings' => [
							'slide_title' => 'Want 15% off ShieldPRO?',
						],
					];
					break;

				case 'free_trial':
					$additional = [
						'hrefs'   => [
							'free_trial' => 'https://shsec.io/freetrialwizard',
							'features'   => 'https://getshieldsecurity.com/features/',
						],
						'imgs'    => [
							'free_trial' => $con->svgs->raw( 'bootstrap/shield-fill-plus.svg' ),
						],
						'strings' => [
							'slide_title' => 'Try ShieldPRO For Free',
						],
					];
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
					$users = Services::WpUsers()->getCurrentWpUser();
					$additional = [
						'hrefs'   => [
							'facebook'       => 'https://shsec.io/pluginshieldsecuritygroupfb',
							'twitter'        => 'https://shsec.io/pluginshieldsecuritytwitter',
							'email'          => 'https://shsec.io/pluginshieldsecuritynewsletter',
						],
						'imgs'    => [
							'facebook' => $con->svgs->raw( 'bootstrap/facebook.svg' ),
							'twitter'  => $con->svgs->raw( 'bootstrap/twitter.svg' ),
							'email'    => $con->svgs->raw( 'bootstrap/envelope-fill.svg' ),
						],
						'vars'    => [
							'name'  => $users->first_name,
							'email' => $users->user_email
						],
						'strings' => [
							'slide_title' => 'Come Join Us!',
						],
					];
					break;

				case 'thankyou':
					$additional = [
						'vars'    => [
							'video_id' => '269364269'
						],
						'strings' => [
							'slide_title' => 'Thank You For Choosing Shield Security',
						],
					];
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

				case 'license':
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
		$req = Services::Request();

		$sMasterSiteUrl = $req->post( 'MasterSiteUrl' );
		$sSecretKey = $req->post( 'MasterSiteSecretKey' );
		$bEnabledNetwork = $req->post( 'ShieldNetworkCheck' ) === 'Y';

		try {
			$code = ( new Plugin\Lib\ImportExport\Import() )
				->setMod( $this->getMod() )
				->fromSite( $sMasterSiteUrl, $sSecretKey, $bEnabledNetwork );
		}
		catch ( Exception $e ) {
			$sSiteResponse = $e->getMessage();
			$code = $e->getCode();
		}

		$errors = [
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

		$sMessage = isset( $errors[ $code ] ) ? $errors[ $code ] : 'Unknown Error';

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $code === 0 )
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

		$input = Services::Request()->post( 'LoginProtectOption' );

		if ( !empty( $input ) ) {
			$enable = $input === 'Y';

			if ( $enable ) { // we don't disable the whole module
				$mod->setIsMainFeatureEnabled( true );
			}
			$mod->setEnabledAntiBotDetection( $enable );
			$mod->saveModOptions();

			$success = $opts->isEnabledAntiBot() === $enable;
			if ( $success ) {
				$msg = sprintf( '%s has been %s.', __( 'Login Guard', 'wp-simple-firewall' ),
					$enable ? __( 'Enabled', 'wp-simple-firewall' ) : __( 'Disabled', 'wp-simple-firewall' )
				);
			}
			else {
				$msg = sprintf( __( '%s setting could not be changed at this time.', 'wp-simple-firewall' ), __( 'Login Guard', 'wp-simple-firewall' ) );
			}
		}
		else {
			$msg = __( 'No option was selected', 'wp-simple-firewall' );
			$success = false;
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

	private function wizardPluginSecurityBadge() :StdResponse {
		$r = new StdResponse();

		$input = Services::Request()->post( 'SecurityPluginBadge' );

		if ( !empty( $input ) ) {
			$toEnable = $input === 'Y';

			$modPlugin = $this->getCon()->getModule_Plugin();
			if ( $toEnable ) { // we don't disable the whole module
				$modPlugin->setIsMainFeatureEnabled( true );
			}
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options $optsPlugin */
			$optsPlugin = $modPlugin->getOptions();
			$optsPlugin->setOpt( 'display_plugin_badge', $toEnable ? 'Y' : 'N' );
			$modPlugin->saveModOptions();

			$r->success = $optsPlugin->isOpt( 'display_plugin_badge', 'Y' ) === $toEnable;
			if ( $r->success ) {
				$r->msg_text = sprintf( '%s has been %s.', __( 'Security Plugin Badge', 'wp-simple-firewall' ),
					$toEnable ? __( 'Enabled', 'wp-simple-firewall' ) : __( 'Disabled', 'wp-simple-firewall' )
				);
			}
			else {
				$r->msg_text = sprintf( __( '%s setting could not be changed at this time.', 'wp-simple-firewall' ),
					__( 'Security Plugin Badge', 'wp-simple-firewall' ) );
			}
		}
		else {
			$r->msg_text = __( 'No option was selected', 'wp-simple-firewall' );
			$r->success = false;
		}

		return $r;
	}

	/**
	 * @return \FernleafSystems\Utilities\Response
	 */
	private function wizardCommentsFilter() {

		$input = Services::Request()->post( 'CommentsFilterOption' );

		if ( !empty( $input ) ) {
			$toEnable = $input === 'Y';

			$modComm = $this->getCon()->getModule_Comments();
			if ( $toEnable ) { // we don't disable the whole module
				$modComm->setIsMainFeatureEnabled( true );
			}
			$modComm->setEnabledAntiBot( $toEnable );
			$modComm->saveModOptions();

			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options $optsComm */
			$optsComm = $modComm->getOptions();
			$success = $optsComm->isEnabledAntiBot() === $toEnable;
			if ( $success ) {
				$msg = sprintf( '%s has been %s.', __( 'Comment SPAM Protection', 'wp-simple-firewall' ),
					$toEnable ? __( 'Enabled', 'wp-simple-firewall' ) : __( 'Disabled', 'wp-simple-firewall' )
				);
			}
			else {
				$msg = sprintf( __( '%s setting could not be changed at this time.', 'wp-simple-firewall' ), __( 'Comment SPAM Protection', 'wp-simple-firewall' ) );
			}
		}
		else {
			$msg = __( 'No option was selected', 'wp-simple-firewall' );
			$success = false;
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $success )
			->setMessageText( $msg );
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
}