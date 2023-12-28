<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class OverrideShieldIpBlock extends Base {

	public function execResponse() :bool {
		add_filter( 'shield/is_request_blocked', '__return_false' );
		return true;
	}
}