<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Route\Lists;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Request\Lists;

class AddIP extends ListsBase {

	const ROUTE_METHOD = \WP_REST_Server::CREATABLE;

	protected function getRouteArgsCustom() :array {
		return [
			'ip'    => $this->getRouteArgSchema( 'ip' ),
			'list'  => $this->getRouteArgSchema( 'list' ),
			'label' => [
				'description' => 'The label to assign to the IP address.',
				'type'        => 'string',
				'required'    => true,
			],
		];
	}

	protected function getRequestProcessorClass() :string {
		return Lists\AddIP::class;
	}

	public function getRoutePath() :string {
		return '/(?P<list>bypass|block)/(?P<ip>[0-9a-f\.:]{3,})';
	}
}