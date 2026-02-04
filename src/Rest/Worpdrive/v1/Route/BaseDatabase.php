<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

class BaseDatabase extends BaseWorpdrive {

	public function getRoutePath() :string {
		return sprintf( '/db/(?P<type>%s)', \implode( '|', $this->enumTypes() ) );
	}

	protected function enumTypes() :array {
		return [
			'schema',
			'data',
		];
	}

	protected function getRouteArgsCustom() :array {
		return [
			'type' => [
				'description' => 'DB Export Type',
				'type'        => 'string',
				'enum'        => [
					'schema',
					'data',
				],
				'required'    => true,
			],
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
			'table_export_map'  => [
				'description' => 'DB Table Export Map',
				'type'        => 'object',
				'required'    => false,
				'readonly'    => true,
				'default'     => 0,
			],
		];
	}
}