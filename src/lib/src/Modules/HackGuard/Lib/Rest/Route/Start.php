<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request;

class Start extends RouteBase {

	protected function getRequestProcessorClass() :string {
		return Request\Start::class;
	}

	public function getRoutePath() :string {
		return '/scan/start';
	}
}