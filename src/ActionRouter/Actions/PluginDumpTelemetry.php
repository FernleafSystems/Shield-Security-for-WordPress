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
		// Shown as JSON, the form in which the data is sent.
		echo sprintf( '<pre><code>%s</code></pre>', esc_html( (string)wp_json_encode(
			( new PluginTelemetry() )->collectTrackingData(),
			\JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES
		) ) );
		die();
	}
}