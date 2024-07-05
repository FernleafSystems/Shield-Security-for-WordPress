<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class AnonRestApiDisable extends Base {

	public function title() :string {
		return __( 'Block Anonymous REST API', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Disable anonymous requests to the REST API.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return self::con()->opts->optIs( 'disable_anonymous_restapi', 'Y' ) ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}