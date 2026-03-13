<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

class ActivityRecent extends Base {

	protected function process() :array {
		return self::con()->comps->site_query->recentActivity();
	}
}
