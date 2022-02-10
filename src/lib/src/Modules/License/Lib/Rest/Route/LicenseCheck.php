<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Rest\Request;

class LicenseCheck extends RouteBase {

	protected function getRequestProcessorClass() :string {
		return Request\LicenseCheck::class;
	}

	public function getRoutePath() :string {
		return '/license/check';
	}
}