<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class WpIsAdmin extends Base {

	public const SLUG = 'wp_is_admin';

	protected function execConditionCheck() :bool {
		return is_network_admin() || is_admin();
	}

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->wp_is_admin;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->wp_is_admin = $result;
	}
}