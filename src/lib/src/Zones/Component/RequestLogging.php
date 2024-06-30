<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class RequestLogging extends Base {

	public function title() :string {
		return __( 'Request Logging', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'View details of web requests sent to your WordPress site.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return self::con()->comps->opts_lookup->enabledTrafficLogger() ? EnumEnabledStatus::GOOD : EnumEnabledStatus::OKAY;
	}
}