<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class WpIsAdmin extends Base {

	const SLUG = 'wp_is_admin';

	protected function execConditionCheck() :bool {
		return $this->getCon()->this_req->wp_is_admin ?? ( is_network_admin() || is_admin() );
	}
}