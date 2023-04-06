<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

class ModCon extends Base\ModCon {

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