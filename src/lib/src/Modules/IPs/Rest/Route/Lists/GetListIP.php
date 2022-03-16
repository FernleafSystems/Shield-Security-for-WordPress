<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Route\Lists;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Request\Lists;

class GetListIP extends ListsBase {

	public function getRoutePath() :string {
		return '/(?P<list>bypass|block)/(?P<ip>[0-9a-f\.:]{3,})';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'ip'   => $this->getRouteArgSchema( 'ip' ),
			'list' => $this->getRouteArgSchema( 'list' ),
		];
	}

	protected function getRequestProcessorClass() :string {
		return Lists\GetListIP::class;
	}
}