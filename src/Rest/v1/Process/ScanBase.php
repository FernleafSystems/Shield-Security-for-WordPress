<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class ScanBase extends Base {

	use PluginControllerConsumer;

	protected function getScansStatus() :array {
		$runtime = self::con()->comps->site_query->scanRuntime();
		return [
			'enqueued_count'  => $runtime[ 'enqueued_count' ],
			'enqueued_status' => $runtime[ 'running_states' ],
			'current_slug'    => $runtime[ 'current_slug' ],
			'current_name'    => $runtime[ 'current_name' ] !== ''
				? $runtime[ 'current_name' ]
				: __( 'No scan running.', 'wp-simple-firewall' ),
			'progress'        => $runtime[ 'progress' ],
		];
	}
}
