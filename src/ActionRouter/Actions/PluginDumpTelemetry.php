<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginTelemetry;

class PluginDumpTelemetry extends BaseAction {

	use Traits\NonceVerifyRequired;

	public const SLUG = 'dump_telemetry_data';

	protected function exec() {
		echo sprintf( '<pre><code>%s</code></pre>', \print_r( ( new PluginTelemetry() )->collectTrackingData(), true ) );
		die();
	}
}