<?php

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\FindSourceFromIp;

class ICWP_WPSF_Wizard_Plugin extends ICWP_WPSF_Wizard_Base {

	/**
	 * @param string $step
	 * @return StdResponse|\FernleafSystems\Utilities\Response|null
	 */
	protected function processWizardStep( string $step ) {
		switch ( $step ) {

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
	 * @param string $step
	 * @return array
	 */
	protected function getRenderData_SlideExtra( $step ) {
		$con = $this->getCon();

		$additional = [];
		if ( $sCurrentWiz == 'welcome' ) {

			switch ( $step ) {
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
							'actions'          => __( 'Certain modules have extra actions and features, e.g. Activity Log Viewer.', 'wp-simple-firewall' )
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

		if ( empty( $additional[ 'vars' ][ 'step_slug' ] ) ) {
			$additional[ 'vars' ][ 'step' ] = $step;
		}

		return $additional;
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
		catch ( \Exception $e ) {
			$msg =$e->getMessage() ;
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
		try {
			$code = ( new Plugin\Lib\ImportExport\Import() )
				->setMod( $this->getMod() )
				->fromSite( (string)$req->post( 'MasterSiteUrl' ), (string)$req->post( 'MasterSiteSecretKey' ), $req->post( 'ShieldNetworkCheck' ) === 'Y' );
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
				$sMessage = sprintf( '%s has been %s.', __( 'Activity Log', 'wp-simple-firewall' ),
					$oMod->isModuleEnabled() ? __( 'Enabled', 'wp-simple-firewall' ) : __( 'Disabled', 'wp-simple-firewall' )
				);
			}
			else {
				$sMessage = sprintf( __( '%s setting could not be changed at this time.', 'wp-simple-firewall' ), __( 'Activity Log', 'wp-simple-firewall' ) );
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
			$opts->setOpt( 'enable_antibot_check', $enable ? 'Y' : 'N' );
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
		$mod = $this->getCon()->getModule_Plugin();
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();

		$success = false;
		$sMessage = __( 'No changes were made as no option was selected', 'wp-simple-firewall' );

		$sInput = $oReq->post( 'BadgeOption' );
		if ( !empty( $sInput ) ) {
			$enabled = $sInput === 'Y';
			$mod->getPluginBadgeCon()->setIsDisplayPluginBadge( $enabled );
			$success = true;
		}

		$sInput = $oReq->post( 'AnonymousOption' );
		if ( !empty( $sInput ) ) {
			$enabled = $sInput === 'Y';
			$opts->setPluginTrackingPermission( $enabled );
			$success = true;
		}

		return ( new \FernleafSystems\Utilities\Response() )
			->setSuccessful( $success )
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
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options $optsComm */
			$optsComm = $modComm->getOptions();
			if ( $toEnable ) { // we don't disable the whole module
				$modComm->setIsMainFeatureEnabled( true );
			}
			$optsComm->setEnabledAntiBot( $toEnable );
			$modComm->saveModOptions();

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

	private function getGdprSearchItems() :array {
		$items = Services::WpGeneral()->getTransient( $this->getCon()->prefix( 'gdpr-items' ) );
		return is_array( $items ) ? $items : [];
	}
}