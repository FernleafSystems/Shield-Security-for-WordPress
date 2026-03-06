<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Monolog;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class ActivityLogEnabled extends Base {

	public const SLUG = 'activity_log_enabled';
	public const WEIGHT = 5;

	protected function testIfProtected() :bool {
		try {
			$protected = self::con()->comps->activity_log->isLogToDB();
			( new Monolog() )->assess();
		}
		catch ( \Exception $e ) {
			$protected = false;
		}

		return $protected;
	}

	protected function hrefFull() :string {
		return self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS );
	}

	public function title() :string {
		return __( 'Activity Logging', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Tracking changes with the Activity Log makes it easier to monitor and investigate issues.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Tracking changes with the Activity Log is disabled making it harder to monitor and investigate issues.", 'wp-simple-firewall' );
	}
}
