<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpIdentify;

class ModCon extends Base\ModCon {

	/**
	 * @var bool
	 */
	protected static $bIsVerifiedBot;

	/**
	 * @var bool
	 */
	private static $bVisitorIsWhitelisted;

	/**
	 * @return bool
	 * @deprecated 10.2
	 */
	public function canCacheDirWrite() :bool {
		return ( new Shield\Modules\Plugin\Lib\TestCacheDirWrite() )
			->setMod( $this->getCon()->getModule_Plugin() )
			->canWrite();
	}

	/**
	 * @return Shield\Databases\Session\Handler
	 */
	public function getDbHandler_Sessions() {
		return $this->getCon()
					->getModule_Sessions()
					->getDbHandler_Sessions();
	}

	/**
	 * @return Shield\Databases\Session\EntryVO|null
	 */
	public function getSession() {
		return $this->getCon()
					->getModule_Sessions()
					->getSessionCon()
					->getCurrent();
	}

	/**
	 * @return bool
	 */
	public function hasValidRequestIP() {
		return Services::IP()->isValidIp( Services::IP()->getRequestIp() );
	}

	public function onWpInit() {
		parent::onWpInit();
		if ( $this->isThisModulePage() && !$this->isWizardPage() && ( $this->getSlug() != 'insights' ) ) {
			$this->redirectToInsightsSubPage();
		}
	}

	protected function redirectToInsightsSubPage() {
		Services::Response()->redirect(
			$this->getCon()->getModule_Insights()->getUrl_AdminPage(),
			[
				'inav'   => 'settings',
				'subnav' => $this->getSlug()
			],
			true, false
		);
	}

	public function getCaptchaCfg() :Plugin\Lib\Captcha\CaptchaConfigVO {
		$plugMod = $this->getCon()->getModule_Plugin();
		/** @var Shield\Modules\Plugin\Options $plugOpts */
		$plugOpts = $plugMod->getOptions();
		$cfg = ( new Plugin\Lib\Captcha\CaptchaConfigVO() )->applyFromArray( $plugOpts->getCaptchaConfig() );
		$cfg->invisible = $cfg->theme === 'invisible';

		if ( $cfg->provider === Plugin\Lib\Captcha\CaptchaConfigVO::PROV_GOOGLE_RECAP2 ) {
			$cfg->url_api = 'https://www.google.com/recaptcha/api.js';
		}
		elseif ( $cfg->provider === Plugin\Lib\Captcha\CaptchaConfigVO::PROV_HCAPTCHA ) {
			$cfg->url_api = 'https://hcaptcha.com/1/api.js';
		}
		else {
			error_log( 'CAPTCHA Provider not supported: '.$cfg->provider );
		}

		$cfg->js_handle = $this->getCon()->prefix( $cfg->provider );

		return $cfg;
	}

	public function getSecAdminLoginAjaxData() :array {
		// We set a custom mod_slug so that this module handles the ajax request
		$dat = $this->getAjaxActionData( 'sec_admin_login' );
		$dat[ 'mod_slug' ] = $this->prefix( 'admin_access_restriction' );
		return $dat;
	}

	protected function getSecAdminCheckAjaxData() :array {
		// We set a custom mod_slug so that this module handles the ajax request
		$dat = $this->getAjaxActionData( 'sec_admin_check' );
		$dat[ 'mod_slug' ] = $this->prefix( 'admin_access_restriction' );
		return $dat;
	}

	public function getPluginReportEmail() :string {
		return $this->getCon()
					->getModule_Plugin()
					->getPluginReportEmail();
	}

	/**
	 * @uses echo()
	 */
	public function displayModuleAdminPage() {
		if ( $this->canDisplayOptionsForm() ) {
			parent::displayModuleAdminPage();
		}
		else {
			echo $this->renderRestrictedPage();
		}
	}

	protected function renderRestrictedPage() :string {
		/** @var Shield\Modules\SecurityAdmin\Options $secOpts */
		$secOpts = $this->getCon()
						->getModule_SecAdmin()
						->getOptions();
		$aData = Services::DataManipulation()
						 ->mergeArraysRecursive(
							 $this->getUIHandler()->getBaseDisplayData(),
							 [
								 'ajax'    => [
									 'restricted_access' => $this->getAjaxActionData( 'restricted_access' ),
								 ],
								 'strings' => [
									 'force_remove_email' => __( "If you've forgotten your PIN, a link can be sent to the plugin administrator email address to remove this restriction.", 'wp-simple-firewall' ),
									 'click_email'        => __( "Click here to send the verification email.", 'wp-simple-firewall' ),
									 'send_to_email'      => sprintf( __( "Email will be sent to %s", 'wp-simple-firewall' ),
										 Utilities\Obfuscate::Email( $this->getPluginReportEmail() ) ),
									 'no_email_override'  => __( "The Security Administrator has restricted the use of the email override feature.", 'wp-simple-firewall' ),
								 ],
								 'flags'   => [
									 'allow_email_override' => $secOpts->isEmailOverridePermitted()
								 ]
							 ]
						 );
		return $this->renderTemplate( '/wpadmin_pages/security_admin/index.twig', $aData, true );
	}

	/**
	 * @return bool
	 */
	public function getIfSupport3rdParty() {
		return $this->isPremium();
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		$opts = $this->getOptions();
		return ( $opts->isModuleRunIfWhitelisted() || !$this->isVisitorWhitelisted() )
			   && ( $opts->isModuleRunIfVerifiedBot() || !$this->isVerifiedBot() )
			   && ( $opts->isModuleRunUnderWpCli() || !Services::WpGeneral()->isWpCli() )
			   && parent::isReadyToExecute();
	}

	public function isVisitorWhitelisted() :bool {
		if ( !isset( self::$bVisitorIsWhitelisted ) ) {
			try {
				$ipID = ( new IpIdentify( (string)Services::IP()->getRequestIp() ) )->run();
				$ipID = key( $ipID );
			}
			catch ( \Exception $e ) {
				$ipID = IpIdentify::UNKNOWN;
			}

			self::$bVisitorIsWhitelisted =
				in_array( $ipID, [ IpIdentify::ICONTROLWP ] )
				|| ( new Shield\Modules\IPs\Lib\Ops\LookupIpOnList() )
					   ->setDbHandler( $this->getCon()->getModule_IPs()->getDbHandler_IPs() )
					   ->setIP( Services::IP()->getRequestIp() )
					   ->setListTypeWhite()
					   ->lookup()
				   instanceof Shield\Databases\IPs\EntryVO;
		}
		return self::$bVisitorIsWhitelisted;
	}

	public function isVerifiedBot() :bool {
		if ( !isset( self::$bIsVerifiedBot ) ) {
			$srvIP = Services::IP();
			self::$bIsVerifiedBot = !$srvIP->isLoopback() &&
									!in_array( $srvIP->getIpDetector()->getIPIdentity(), [
										IpIdentify::UNKNOWN,
										IpIdentify::THIS_SERVER,
										IpIdentify::VISITOR,
									] );
		}
		return self::$bIsVerifiedBot;
	}

	public function isXmlrpcBypass() :bool {
		return $this->getCon()
					->getModule_Plugin()
					->isXmlrpcBypass();
	}

	/**
	 * @param string[] $aArray
	 * @param string   $sPregReplacePattern
	 * @return string[]
	 */
	protected function cleanStringArray( $aArray, $sPregReplacePattern ) {
		$aCleaned = [];
		if ( !is_array( $aArray ) ) {
			return $aCleaned;
		}

		foreach ( $aArray as $nKey => $sVal ) {
			$sVal = preg_replace( $sPregReplacePattern, '', $sVal );
			if ( !empty( $sVal ) ) {
				$aCleaned[] = $sVal;
			}
		}
		return array_unique( array_filter( $aCleaned ) );
	}

	protected function getNamespaceRoots() :array {
		// Ensure order of namespaces is 'Module', 'BaseShield', then 'Base'
		return [
			$this->getNamespace(),
			$this->getCon()->getModulesNamespace().'\\BaseShield',
			$this->getBaseNamespace(),
		];
	}
}