<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Route\{
	Results\GetAll,
	Scans\Start,
	Scans\Status
};

class Rest extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest {

	protected function enumRoutes() :array {
		return [
			'scan_status'  => Status::class,
			'scan_start'   => Start::class,
			'scan_results' => GetAll::class,
		];
	}
}