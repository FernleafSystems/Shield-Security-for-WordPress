<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class WpIsAjax extends Base {

	const SLUG = 'wp_is_ajax';

	protected function execConditionCheck() :bool {
		return $this->getCon()->req->wp_is_ajax ?? Services::WpGeneral()->isAjax();
	}
}