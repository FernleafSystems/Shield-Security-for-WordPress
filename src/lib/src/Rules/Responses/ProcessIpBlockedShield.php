<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\BlockRequest;

class ProcessIpBlockedShield extends Base {

	public const SLUG = 'process_ip_blocked_shield';

	public function execResponse() :bool {
		add_action( 'init', function () {
			( new BlockRequest() )->execute();
		}, HookTimings::INIT_RULES_RESPONSE_IP_BLOCK_REQUEST_SHIELD );

		return true;
	}
}