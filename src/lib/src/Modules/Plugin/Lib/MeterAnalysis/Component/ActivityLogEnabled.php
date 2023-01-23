<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Options;

class ActivityLogEnabled extends Base {

	public const SLUG = 'activity_log_enabled';

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_AuditTrail();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isLogToDB();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_AuditTrail();
		return $mod->isModOptEnabled() ? $this->link( 'section_localdb' ) : $this->link( 'enable_audit_trail' );
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