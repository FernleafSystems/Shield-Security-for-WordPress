<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Route\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Route\Base;

abstract class IPsBase extends Base {

	public function getRoutePathPrefix() :string {
		return '/ips';
	}

	protected function getRouteArgSchema( string $key ) :array {
		switch ( $key ) {

			default:
				$sch = parent::getRouteArgSchema( $key );
				break;
		}
		return $sch;
	}
}