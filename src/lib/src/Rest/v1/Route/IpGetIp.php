<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

class IpGetIp extends IpBase {

	protected function getRouteArgsCustom() :array {
		return [
			'ip' => $this->getRouteArgSchema( 'ip' ),
		];
	}

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\IpGetIp::class;
	}

	public function getRoutePath() :string {
		return '/(?P<ip>[0-9a-f\.:]{3,})';
	}
}