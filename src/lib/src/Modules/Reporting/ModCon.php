<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

/**
 * @deprecated 18.0
 */
class ModCon extends BaseShield\ModCon {

	protected function isReadyToExecute() :bool {
		return false;
	}
}