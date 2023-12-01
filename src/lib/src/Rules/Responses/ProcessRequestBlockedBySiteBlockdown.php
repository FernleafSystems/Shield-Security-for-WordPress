<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteLockdown\BlockRequestSiteLockdown;

class ProcessRequestBlockedBySiteBlockdown extends Base {

	public const SLUG = 'process_request_blocked_by_site_blockdown';

	public function execResponse() :bool {
		( new BlockRequestSiteLockdown() )->execute();
		return true;
	}
}