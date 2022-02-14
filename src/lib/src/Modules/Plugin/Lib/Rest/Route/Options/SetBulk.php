<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

class SetBulk extends BaseBulk {

	protected function getRouteArgsCustom() :array {
		return [
			'options' => [
				'description' => 'Array of options to set. Each must include option key and value',
				'required'    => true,
				'type'        => 'array',
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'key'   => $this->getPropertySchema( 'key' ),
						'value' => $this->getPropertySchema( 'value' ),
					],
				],
			],
		];
	}

	public function getArgMethods() :array {
		return array_map( 'trim', explode( ',', \WP_REST_Server::EDITABLE ) );
	}

	protected function getRequestProcessorClass() :string {
		return Options\SetBulk::class;
	}
}