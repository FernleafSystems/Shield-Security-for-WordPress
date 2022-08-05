<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Route\Lists;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Route\Base;

abstract class ListsBase extends Base {

	public function getRoutePathPrefix() :string {
		return '/ip_lists';
	}

	protected function getRouteArgSchema( string $key ) :array {
		switch ( $key ) {

			case 'list':
				$sch = [
					'description' => 'The IP list name: either block (black) or bypass (white).',
					'type'        => 'string',
					'enum'        => [
						'bypass',
						'block',
						'crowdsec',
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