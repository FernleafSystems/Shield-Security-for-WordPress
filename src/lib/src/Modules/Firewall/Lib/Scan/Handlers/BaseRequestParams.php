<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\ParametersToScan;

abstract class BaseRequestParams extends Base {

	protected function getItemsToScan() :array {
		return ( new ParametersToScan() )
			->setMod( $this->getMod() )
			->retrieve();
	}
}