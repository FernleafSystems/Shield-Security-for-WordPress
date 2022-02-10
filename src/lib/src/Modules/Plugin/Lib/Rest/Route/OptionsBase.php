<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route\RouteBase;

abstract class OptionsBase extends RouteBase {

	public function getRoutePath() :string {
		return '';
	}

	public function getRoutePathPrefix() :string {
		return '/options';
	}
}