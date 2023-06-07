<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\BlockRequestCrowdsec;

class SetIpBlockedCrowdsec extends Base {

	public const SLUG = 'set_ip_blocked_crowdsec';

	protected function execResponse() :bool {
		$this->con()->this_req->is_ip_blocked_crowdsec = true;

		add_action( 'init', function () {
			( new BlockRequestCrowdsec() )->execute();
		}, HookTimings::INIT_RULES_RESPONSE_IP_BLOCK_REQUEST_CROWDSEC );

		return true;
	}
}