<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

class DatabaseSchema extends BaseDatabase {

	public function getRoutePath() :string {
		return '/db/schema';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'dump_method' => [
				'description' => 'DB Dump Method',
				'type'        => 'string',
				'default'     => 'direct',
				'enum'        => [
					'direct',
					'zip',
				],
				'required'    => true,
			],
		];
	}
}