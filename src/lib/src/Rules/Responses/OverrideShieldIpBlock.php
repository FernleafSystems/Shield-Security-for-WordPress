<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class OverrideShieldIpBlock extends Base {

	public function execResponse() :bool {
		self::con()->this_req->is_ip_blocked_shield = false;
//		add_filter( 'shield/is_request_blocked', '__return_false' );
		return true;
	}
}