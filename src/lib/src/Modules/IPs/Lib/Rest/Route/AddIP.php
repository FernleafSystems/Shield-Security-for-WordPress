<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\{
	Request
};

class AddIP extends Base {

	public function getArgMethods() :array {
		return array_map( 'trim', explode( ',', \WP_REST_Server::EDITABLE ) );
	}

	protected function getRouteArgsCustom() :array {
		return [
			'list' => [
				'description' => 'The IP list to which to add the IP.',
				'type'        => 'string',
				'enum'        => [
					'bypass',
					'block',
				],
				'required'    => true,
			],
			'ip'   => [
				'description' => 'The IP address to add to the list.',
				'type'        => 'ip',
				'required'    => true,
			],
			'label'   => [
				'description' => 'The label to assign to the IP address.',
				'type'        => 'string',
				'required'    => true,
			],
		];
	}

	protected function getRequestProcessorClass() :string {
		return Request\AddIP::class;
	}

	public function getRoutePath() :string {
		return '/(?P<list>bypass|block)/(?P<ip>[0-9a-f\.:]{3,})';
	}
}