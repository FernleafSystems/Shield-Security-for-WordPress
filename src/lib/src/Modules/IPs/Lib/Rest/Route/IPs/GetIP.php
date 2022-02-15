<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Route\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Rest\Request\IPs;

class GetIP extends IPsBase {

	protected function getRouteArgsCustom() :array {
		return [
			'ip' => $this->getRouteArgSchema( 'ip' ),
		];
	}

	protected function getRequestProcessorClass() :string {
		return IPs\GetIP::class;
	}

	public function getRoutePath() :string {
		return '/(?P<ip>[0-9a-f\.:]{3,})';
	}
}