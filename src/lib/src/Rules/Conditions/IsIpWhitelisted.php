<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\LookupIpOnList;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;

class IsIpWhitelisted extends Base {

	use RequestIP;

	const SLUG = 'is_ip_whitelisted';

	protected function execConditionCheck() :bool {
		return !empty( ( new LookupIpOnList() )
			->setDbHandler( $this->getCon()->getModule_IPs()->getDbHandler_IPs() )
			->setIP( $this->getRequestIP() )
			->setListTypeBypass()
			->lookup() );
	}
}