<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

abstract class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

	public function getPluginReportEmail() :string {
		return self::con()
				   ->getModule_Plugin()
				   ->getPluginReportEmail();
	}

	/**
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		$req = self::con()->this_req;
		return ( !$req->request_bypasses_all_restrictions || $this->cfg->properties[ 'run_if_whitelisted' ] )
			   && ( !$req->is_trusted_bot || $this->cfg->properties[ 'run_if_verified_bot' ] )
			   && ( !$req->wp_is_wpcli || $this->cfg->properties[ 'run_if_wpcli' ] )
			   && parent::isReadyToExecute();
	}

	public function isXmlrpcBypass() :bool {
		return self::con()
				   ->getModule_Plugin()
				   ->isXmlrpcBypass();
	}

	protected function getNamespaceRoots() :array {
		// Ensure order of namespaces is 'Module', 'BaseShield', then 'Base'
		return [
			$this->getNamespace(),
			self::con()->getModulesNamespace().'\\BaseShield',
			$this->getBaseNamespace(),
		];
	}

	/**
	 * @deprecated 18.5
	 */
	public function cleanStringArray( array $arr, string $pregReplacePattern ) :array {
		$cleaned = [];
		foreach ( $arr as $val ) {
			$val = \preg_replace( $pregReplacePattern, '', $val );
			if ( \strlen( $val ) > 0 ) {
				$cleaned[] = $val;
			}
		}
		return $cleaned;
	}

	/**
	 * @deprecated 18.5
	 */
	public function getCaptchaCfg() {
		/** @var Plugin\Options $plugOpts */
		$plugOpts = self::con()->getModule_Plugin()->opts();
		$cfg = ( new Plugin\Lib\Captcha\CaptchaConfigVO() )->applyFromArray( $plugOpts->getCaptchaConfig() );
		$cfg->invisible = $cfg->theme === 'invisible';
		$cfg->url_api = '';
		$cfg->js_handle = self::con()->prefix( $cfg->provider );
		return $cfg;
	}
}