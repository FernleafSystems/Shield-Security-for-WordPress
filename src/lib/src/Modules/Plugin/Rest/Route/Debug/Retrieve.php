<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Route\Debug;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Route\RouteBase;

class Retrieve extends RouteBase {

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request\Debug\Retrieve::class;
	}

	public function getRoutePath() :string {
		return 'debug';
	}
}