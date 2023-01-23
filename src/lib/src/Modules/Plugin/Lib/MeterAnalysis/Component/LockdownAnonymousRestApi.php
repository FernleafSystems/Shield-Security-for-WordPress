<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Options;

class LockdownAnonymousRestApi extends Base {

	public const SLUG = 'lockdown_anonymous_rest_api';
	public const WEIGHT = 20;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_Lockdown();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isRestApiAnonymousAccessDisabled();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_Lockdown();
		return $mod->isModOptEnabled() ? $this->link( 'disable_anonymous_restapi' ) : $this->link( 'enable_lockdown' );
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