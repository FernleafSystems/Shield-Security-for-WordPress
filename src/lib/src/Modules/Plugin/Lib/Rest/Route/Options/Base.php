<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;

abstract class Base extends RouteBase {

	public function getRoutePathPrefix() :string {
		return '/options';
	}

	protected function getRouteArgsDefaults() :array {
		return [
			'filter_fields' => [
				'description' => '[Filter] Comma-separated fields to include in option info.',
				'type'        => 'string',
				'pattern'     => '^([a-z_]{2,},?)+[a-z]$',
				'required'    => false,
			],
		];
	}

	protected function optKeyExists( string $key ) :bool {
		$exists = false;
		foreach ( $this->getCon()->modules as $module ) {
			if ( $module->getOptions()->optExists( $key ) ) {
				$exists = true;
				break;
			}
		}
		return $exists;
	}

	protected function getPropertySchema( string $key ) :array {
		switch ( $key ) {
			case 'key':
				$sch = [
					'description' => 'Option key',
					'type'        => 'string',
					'pattern'     => '^[0-9a-z_]{3,}$',
					'required'    => true,
					'readonly'    => true,
				];
				break;

			case 'value':
				$sch = [
					'description' => 'Option value',
					'required'    => true,
					'type'        => [
						'array',
						'string',
						'number',
						'null'
					],
				];
				break;

			default:
				$sch = [];
				break;
		}
		return $sch;
	}
}