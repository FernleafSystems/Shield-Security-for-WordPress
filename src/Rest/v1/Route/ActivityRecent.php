<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

class ActivityRecent extends Base {

	public function getRoutePath() :string {
		return '/activity/recent';
	}

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\ActivityRecent::class;
	}
}
