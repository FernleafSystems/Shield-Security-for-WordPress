<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class WpIsWpcli extends Base {

	const SLUG = 'wp_is_wpcli';

	protected function execConditionCheck() :bool {
		$thisReq = $this->getCon()->this_req;
		if ( !isset( $thisReq->wp_is_wpcli ) ) {
			$thisReq->wp_is_wpcli = Services::WpGeneral()->isWpCli();
		}
		return $thisReq->wp_is_wpcli;
	}
}