<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\BlockRequestCrowdsec;

class ProcessIpBlockedCrowdsec extends Base {

	public const SLUG = 'process_ip_blocked_crowdsec';

	public function execResponse() :bool {
		add_action( 'init', function () {
			( new BlockRequestCrowdsec() )->execute();
		}, HookTimings::INIT_RULES_RESPONSE_IP_BLOCK_REQUEST_CROWDSEC );
		return true;
	}
}