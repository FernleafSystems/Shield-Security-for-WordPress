<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Request;

class GetList extends Base {

	protected function getRouteArgsCustom() :array {
		return [
			'list' => [
				'description' => 'The IP list to retrieve.',
				'type'        => 'string',
				'enum'        => [
					'bypass',
					'block',
					'all'
				],
				'required'    => true,
			],
		];
	}

	protected function getRequestProcessorClass() :string {
		return Request\GetList::class;
	}

	public function getRoutePath() :string {
		return '/(?P<list>bypass|block|all)';
	}
}