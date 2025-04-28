<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

class DatabaseData extends BaseDatabase {

	public function getRoutePath() :string {
		return '/db/data';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'table_export_map'  => [
				'description' => 'DB Table Export Map',
				'type'        => 'object',
				'required'    => true,
			],
		];
	}
}