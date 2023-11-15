<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsSiteLockdownActive extends Base {

	public const SLUG = 'is_site_lockdown_active';

	protected function execConditionCheck() :bool {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options $opts */
		$opts = self::con()->getModule_Plugin()->opts();
		return self::con()->this_req->is_site_lockdown_active = $opts->getBlockdownCfg()->isLockdownActive();
	}
}