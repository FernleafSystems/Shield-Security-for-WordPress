<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class WpIsWpcli extends Base {

	const SLUG = 'wp_is_wpcli';

	protected function execConditionCheck() :bool {
		return $this->getCon()->this_req->wp_is_wpcli ?? Services::WpGeneral()->isWpCli();
	}
}