<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class WpIsAjax extends Base {

	const SLUG = 'wp_is_ajax';

	protected function execResponse() :bool {
		$this->getCon()->req->wp_is_ajax = true;
		return true;
	}
}