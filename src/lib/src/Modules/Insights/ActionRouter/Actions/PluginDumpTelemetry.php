<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginTelemetry;

class PluginDumpTelemetry extends PluginBase {

	use Traits\NonceVerifyRequired;

	const SLUG = 'dump_telemetry_data';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		$tel = ( new PluginTelemetry() )
			->setMod( $this->primary_mod )
			->collectTrackingData();
		echo sprintf( '<pre><code>%s</code></pre>', print_r( $tel, true ) );
		die();
	}
}