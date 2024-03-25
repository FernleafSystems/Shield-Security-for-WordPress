<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Monolog;

class TrafficLogEnabled extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'traffic_log_enabled';
	public const WEIGHT = 4;

	protected function getOptConfigKey() :string {
		return 'enable_logger';
	}

	protected function testIfProtected() :bool {
		try {
			( new Monolog() )->assess();
			$protected = self::con()->comps->opts_lookup->enabledTrafficLogger();
		}
		catch ( \Exception $e ) {
			$protected = false;
		}

		return $protected;
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