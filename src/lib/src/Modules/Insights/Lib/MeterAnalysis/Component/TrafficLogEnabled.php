<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Options;

class TrafficLogEnabled extends Base {

	public const SLUG = 'traffic_log_enabled';
	public const WEIGHT = 25;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_Traffic();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isTrafficLoggerEnabled();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_Traffic();
		return $mod->isModOptEnabled() ? $this->link( 'enable_logger' ) : $this->link( 'enable_traffic' );
	}

	public function title() :string {
		return __( 'Traffic Logging', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Traffic requests are being logged, making it easier to track issues.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Traffic requests aren't being logged, making it harder to track issues.", 'wp-simple-firewall' );
	}
}