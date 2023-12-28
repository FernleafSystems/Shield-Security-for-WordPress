<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class SetRequestToBeLogged extends Base {

	public function execResponse() :bool {
		add_filter( 'shield/is_log_traffic', '__return_true' );
		return true;
	}
}