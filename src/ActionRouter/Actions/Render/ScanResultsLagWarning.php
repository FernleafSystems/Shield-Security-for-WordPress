<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ScanResultsLagWarning {

	use PluginControllerConsumer;

	public function getText() :string {
		return self::con()->comps->site_query->scanRuntime()[ 'is_running' ]
			? __( 'Scans are still in progress, so displayed results may lag behind the current site state.', 'wp-simple-firewall' )
			: '';
	}
}
