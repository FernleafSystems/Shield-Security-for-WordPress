<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginTelemetry;

class PluginDumpTelemetry extends BaseAction {

	public const SLUG = 'dump_telemetry_data';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		echo sprintf( '<pre><code>%s</code></pre>', \print_r( ( new PluginTelemetry() )->collectTrackingData(), true ) );
		die();
	}
}