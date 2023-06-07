<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class WpIsAdmin extends Base {

	public const SLUG = 'wp_is_admin';

	protected function execConditionCheck() :bool {
		$thisReq = $this->con()->this_req;
		if ( !isset( $thisReq->wp_is_admin ) ) {
			$thisReq->wp_is_admin = ( is_network_admin() || is_admin() );
			$thisReq->wp_is_networkadmin = is_network_admin();
		}
		return $thisReq->wp_is_admin;
	}
}