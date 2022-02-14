<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Route\Scans;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Route\Base {

	public function getRoutePath() :string {
		return '';
	}

	public function getRoutePathPrefix() :string {
		return '/scans';
	}
}