<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\QueryIpBlock;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IsHighReputationIP;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;

class IsIpBlocked extends Base {

	use RequestIP;

	const SLUG = 'is_ip_blocked';

	protected function execConditionCheck() :bool {
		$con = $this->getCon();
		$match = ( new QueryIpBlock() )
			->setMod( $con->getModule_IPs() )
			->setIp( $this->getRequestIP() )
			->run();

		if ( $match ) {
			$highRep = ( new IsHighReputationIP() )
				->setMod( $con->getModule_IPs() )
				->setIP( $this->getRequestIP() )
				->query();
			if ( $highRep ) {
				$match = false;
				$con->fireEvent( 'not_conn_kill_high_rep' );
			}
		}

		return $match;
	}
}