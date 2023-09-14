<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Monolog;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Options;

class ActivityLogEnabled extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'activity_log_enabled';
	public const WEIGHT = 5;

	protected function testIfProtected() :bool {
		try {
			$mod = self::con()->getModule_AuditTrail();
			/** @var Options $opts */
			$opts = $mod->opts();

			( new Monolog() )->assess();
			$protected = $mod->isModOptEnabled() && $opts->isLogToDB();
		}
		catch ( \Exception $e ) {
			$protected = false;
		}

		return $protected;
	}

	protected function getOptConfigKey() :string {
		return 'log_level_db';
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