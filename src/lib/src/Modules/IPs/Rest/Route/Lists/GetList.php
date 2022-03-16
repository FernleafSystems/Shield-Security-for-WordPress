<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Route\Lists;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Request\Lists;

class GetList extends ListsBase {

	protected function getRouteArgsCustom() :array {
		return [
			'list' => $this->getRouteArgSchema( 'list' ),
		];
	}

	protected function getRequestProcessorClass() :string {
		return Lists\GetList::class;
	}

	public function getRoutePath() :string {
		return '/(?P<list>bypass|block)';
	}
}