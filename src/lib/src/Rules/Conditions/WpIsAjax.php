<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class WpIsAjax extends Base {

	const SLUG = 'wp_is_ajax';

	protected function execConditionCheck() :bool {
		$thisReq = $this->getCon()->this_req;
		if ( !isset( $thisReq->wp_is_ajax ) ) {
			$thisReq->wp_is_ajax = Services::WpGeneral()->isAjax();
		}
		return $thisReq->wp_is_ajax;
	}
}