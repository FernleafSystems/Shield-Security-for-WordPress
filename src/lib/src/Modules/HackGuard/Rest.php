<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class Rest extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest {

	protected function enumRoutes() :array {
		return [
			'scan_results' => Rest\Route\Results\GetAll::class,
			'scan_status'  => Rest\Route\Scans\Status::class,
			'scan_start'   => Rest\Route\Scans\Start::class,
		];
	}
}