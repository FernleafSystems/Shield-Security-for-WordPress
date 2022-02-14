<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route\Base;

abstract class IPsBase extends Base {

	public function getRoutePathPrefix() :string {
		return '/ips';
	}

	protected function getPropertySchema( string $key ) :array {
		switch ( $key ) {

			case 'list':
				$sch = [
					'description' => 'The IP list for the IP.',
					'type'        => 'string',
					'enum'        => [
						'bypass',
						'block',
					],
					'required'    => true,
				];
				break;

			default:
				$sch = parent::getPropertySchema( $key );
				break;
		}
		return $sch;
	}
}