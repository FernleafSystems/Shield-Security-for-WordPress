<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Options;

class LockdownAnonymousRestApi extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'lockdown_anonymous_rest_api';
	public const WEIGHT = 2;

	protected function testIfProtected() :bool {
		$mod = $this->con()->getModule_Lockdown();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isRestApiAnonymousAccessDisabled();
	}

	protected function getOptConfigKey() :string {
		return 'disable_anonymous_restapi';
	}

	public function title() :string {
		return __( 'Anonymous REST API Access', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Anonymous access to the WordPress REST API is disabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Anonymous access to the WordPress REST API isn't blocked.", 'wp-simple-firewall' );
	}
}