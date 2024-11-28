<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class ActivityLogging extends Base {

	public function title() :string {
		return __( 'WordPress Activity Logging', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'See details of every action that happens on your WordPress site.', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Edit activity log settings', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return self::con()->comps->activity_log->isLogToDB() ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}