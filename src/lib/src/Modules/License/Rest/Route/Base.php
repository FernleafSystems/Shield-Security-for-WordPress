<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Route\RouteBase;

abstract class Base extends RouteBase {

	public function getRoutePath() :string {
		return '/license';
	}

	protected function getRouteArgsDefaults() :array {
		return [
			'filter_fields' => [
				'description' => '[Filter] Comma-separated fields to include.',
				'type'        => 'array', // WordPress kindly converts CSV to array
				'pattern'     => '^((([a-z_]+),?)+)?$',
				'required'    => false,
			],
		];
	}
}