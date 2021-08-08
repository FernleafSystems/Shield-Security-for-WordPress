<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\ParametersToScan;

abstract class BaseRequestParams extends Base {

	private static $params;

	protected function getItemsToScan() :array {
		if ( !isset( self::$params ) ) {
			self::$params = ( new ParametersToScan() )
				->setMod( $this->getMod() )
				->retrieve();
		}
		return self::$params;
	}
}