<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteLockdown\BlockRequestSiteLockdown;

class SetRequestIsSiteLockdownBlocked extends Base {

	public const SLUG = 'set_request_is_site_lockdown_blocked';

	protected function execResponse() :bool {
		self::con()->this_req->is_site_lockdown_blocked = true;
		( new BlockRequestSiteLockdown() )->execute();
		return true;
	}
}