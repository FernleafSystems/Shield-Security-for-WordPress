<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

abstract class IpRulesBase extends IpBase {

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