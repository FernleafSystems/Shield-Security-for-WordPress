<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class WpIsWpcli extends Base {

	const SLUG = 'wp_is_wpcli';

	protected function execResponse() :bool {
		$this->getCon()->this_req->wp_is_wpcli = true;
		return true;
	}
}