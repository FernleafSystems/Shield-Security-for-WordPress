<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

/**
 * @deprecated 18.0
 */
class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

	protected function isReadyToExecute() :bool {
		return false;
	}
}