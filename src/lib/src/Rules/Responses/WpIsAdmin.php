<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class WpIsAdmin extends Base {

	const SLUG = 'wp_is_admin';

	protected function execResponse() :bool {
		$this->getCon()->this_req->wp_is_admin = true;
		$this->getCon()->this_req->wp_is_networkadmin = is_network_admin();
		return true;
	}
}