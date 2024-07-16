<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

class IpRulesAddIpRule extends IpRulesBase {

	public const ROUTE_METHOD = \WP_REST_Server::CREATABLE;

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
		return \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\IpRulesAddIpRule::class;
	}

	public function getRoutePath() :string {
		return '/(?P<list>bypass|block)/(?P<ip>[0-9a-f\.:]{3,})';
	}
}