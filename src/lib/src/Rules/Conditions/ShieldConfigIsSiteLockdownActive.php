<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteLockdown\SiteBlockdownCfg;

class ShieldConfigIsSiteLockdownActive extends Base {

	use Traits\TypeShield;

	public function getDescription() :string {
		return __( "Is Shield's Site Lockdown active.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return ( new SiteBlockdownCfg() )
			->applyFromArray( self::con()->comps->opts_lookup->getBlockdownCfg() )->isLockdownActive();
	}

	protected function getPreviousResult() :?bool {
		return $this->req->is_site_lockdown_active;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		$this->req->is_site_lockdown_active = $result;
	}
}