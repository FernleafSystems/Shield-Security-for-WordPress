<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class ShieldConfigIsSiteLockdownActive extends Base {

	use Traits\TypeShield;

	public function getDescription() :string {
		return __( "Is Shield's Site Lockdown active.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options $opts */
		$opts = self::con()->getModule_Plugin()->opts();
		return $opts->getBlockdownCfg()->isLockdownActive();
	}

	protected function getPreviousResult() :?bool {
		return $this->req->is_site_lockdown_active;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		$this->req->is_site_lockdown_active = $result;
	}
}