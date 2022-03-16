<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Route\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Route\Base;

abstract class ScansBase extends Base {

	public function getRoutePath() :string {
		return '/scans';
	}
}