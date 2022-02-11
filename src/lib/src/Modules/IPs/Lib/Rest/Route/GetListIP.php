<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Request;

class GetListIP extends Base {

	protected function getRouteArgsCustom() :array {
		return [
			'ip'   => [
				'description' => 'The IP address to add to the list.',
				'type'        => 'string',
				'required'    => true,
			],
			'list' => [
				'description' => 'The IP list from which to extract the IP.',
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
		return Request\GetListIP::class;
	}

	public function getRoutePath() :string {
		return '/(?P<list>bypass|block)/(?P<ip>[0-9a-f\.:]{3,})';
	}
}