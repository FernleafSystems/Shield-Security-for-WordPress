<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request;

class Status extends Base {

	protected function getRequestProcessorClass() :string {
		return Request\Status::class;
	}

	public function getRoutePath() :string {
		return '/status';
	}
}