<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Route\Scans;

abstract class ScansBase extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Route\Base {

	public function getRoutePath() :string {
		return '/scans';
	}
}