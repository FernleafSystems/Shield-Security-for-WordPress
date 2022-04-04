<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\QueryIpBlock;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;

class IsIpBlocked extends Base {

	use RequestIP;

	const SLUG = 'is_ip_blocked';

	/**
	 * TODO: $con->fireEvent( 'not_conn_kill_high_rep' );
	 * @inheritDoc
	 */
	protected function execConditionCheck() :bool {
		if ( !isset( $this->getCon()->this_req->is_ip_blocked ) ) {
			$this->getCon()->this_req->is_ip_blocked = ( new QueryIpBlock() )
				->setMod( $this->getCon()->getModule_IPs() )
				->setIp( $this->getRequestIP() )
				->run();
		}
		return $this->getCon()->this_req->is_ip_blocked;
	}
}