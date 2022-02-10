<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Option;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Option;

class Get extends Base {

	protected function getRequestProcessorClass() :string {
		return Option\Get::class;
	}

	protected function getRouteArgsCustom() :array {
		return [
			'mod' => [
				'description' => 'The module for the given option key',
				'type'        => 'string',
				'required'    => true,
			],
			'key' => [
				'description' => 'The option key',
				'type'        => 'string',
				'required'    => true,
			],
		];
	}
}