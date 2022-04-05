<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\LookupIpOnList;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;

class IsIpWhitelisted extends Base {

	use RequestIP;

	const SLUG = 'is_ip_whitelisted';

	protected function execConditionCheck() :bool {
		$thisReq = $this->getCon()->this_req;
		if ( !isset( $thisReq->is_ip_whitelisted ) ) {
			$thisReq->is_ip_whitelisted = !empty( ( new LookupIpOnList() )
				->setDbHandler( $this->getCon()->getModule_IPs()->getDbHandler_IPs() )
				->setIP( $this->getRequestIP() )
				->setListTypeBypass()
				->lookup() );
		}
		return $thisReq->is_ip_whitelisted;

	}
}