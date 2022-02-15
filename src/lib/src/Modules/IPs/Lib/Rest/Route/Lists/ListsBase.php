<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route\Lists;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route\Base;

abstract class ListsBase extends Base {

	public function getRoutePathPrefix() :string {
		return '/ip_lists';
	}

	protected function getRouteArgSchema( string $key ) :array {
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
				$sch = parent::getRouteArgSchema( $key );
				break;
		}
		return $sch;
	}
}