<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class PreventShieldIpAutoBlock extends Base {

	public function execResponse() :bool {
		add_filter( 'shield/is_ip_blocked_auto', '__return_false' );
		return true;
	}
}