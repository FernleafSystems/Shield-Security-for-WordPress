<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public function getDbH_IPs() :DB\IPs\Ops\Handler {
		return $this->getDbHandler()->loadDbH( 'ips' );
	}
}