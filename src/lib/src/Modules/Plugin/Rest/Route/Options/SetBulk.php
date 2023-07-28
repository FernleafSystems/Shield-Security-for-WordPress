<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request\Options;

class SetBulk extends BaseBulk {

	public const ROUTE_METHOD = \WP_REST_Server::EDITABLE;

	protected function getRouteArgsCustom() :array {
		return [
			'options' => [
				'description' => 'Array of options to set. Each must include option key and value',
				'required'    => true,
				'type'        => 'array',
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'key'   => $this->getRouteArgSchema( 'key' ),
						'value' => $this->getRouteArgSchema( 'value' ),
					],
				],
			],
		];
	}

	protected function getRequestProcessorClass() :string {
		return Options\SetBulk::class;
	}
}