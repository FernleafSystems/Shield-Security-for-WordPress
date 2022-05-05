<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities;

class ModCon extends Base\ModCon {

	/**
	 * @var bool
	 * @deprecated 15.0
	 */
	protected static $bIsVerifiedBot;

	/**
	 * @var bool
	 * @deprecated 15.0
	 */
	private static $bVisitorIsWhitelisted;

	/**
	 * @deprecated 15.0
	 */
	public function getDbHandler_Sessions() :Shield\Databases\Session\Handler {
		return $this->getCon()
					->getModule_Sessions()
					->getDbHandler_Sessions();
	}

	public function getSessionWP() :Shield\Modules\Sessions\Lib\SessionVO {
		return $this->getCon()
					->getModule_Sessions()
					->getSessionCon()
					->getCurrentWP();
	}

	/**
	 * @return Shield\Databases\Session\EntryVO|null
	 * @deprecated 15.0
	 */
	public function getSession() :array {
		return $this->getCon()
					->getModule_Sessions()
					->getSessionCon()
					->getCurrent();
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

	public function getPluginReportEmail() :string {
		return $this->getCon()
					->getModule_Plugin()
					->getPluginReportEmail();
	}

	public function getIfSupport3rdParty() :bool {
		return $this->isPremium();
	}

	/**
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		$req = $this->getCon()->this_req;
		return ( !$req->request_bypasses_all_restrictions || $this->cfg->properties[ 'run_if_whitelisted' ] )
			   && ( !$req->is_trusted_bot || $this->cfg->properties[ 'run_if_verified_bot' ] )
			   && ( !$req->wp_is_wpcli || $this->cfg->properties[ 'run_if_wpcli' ] )
			   && parent::isReadyToExecute();
	}

	/**
	 * @deprecated 15.0
	 */
	public function isVisitorWhitelisted() :bool {
		return $this->getCon()->this_req->is_ip_whitelisted;
	}

	/**
	 * @deprecated 15.0
	 */
	public function isTrustedVerifiedBot() :bool {
		return $this->getCon()->this_req->is_trusted_bot;
	}

	/**
	 * @deprecated 15.0
	 */
	protected function getUntrustedProviders() :array {
		$untrustedProviders = apply_filters( 'shield/untrusted_service_providers', [] );
		return is_array( $untrustedProviders ) ? $untrustedProviders : [];
	}

	/**
	 * @deprecated 15.0
	 */
	public function isVerifiedBot() :bool {
		return $this->isTrustedVerifiedBot();
	}

	public function isXmlrpcBypass() :bool {
		return $this->getCon()
					->getModule_Plugin()
					->isXmlrpcBypass();
	}

	public function cleanStringArray( array $arr, string $pregReplacePattern ) :array {
		$cleaned = [];

		foreach ( $arr as $val ) {
			$val = preg_replace( $pregReplacePattern, '', $val );
			if ( strlen( $val ) > 0 ) {
				$cleaned[] = $val;
			}
		}
		return $cleaned;
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