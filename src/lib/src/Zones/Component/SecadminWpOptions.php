<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class SecadminWpOptions extends Base {

	public function explanation() :array {
		return [
				   EnumEnabledStatus::GOOD => [
				   ],
				   EnumEnabledStatus::BAD  => [
					   __( 'Turn on the option to restrict access to critical WordPress settings.', 'wp-simple-firewall' ),
				   ],
			   ][ $this->enabledStatus() ];
	}

	public function title() :string {
		return __( 'WordPress Core Options Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Prevent tampering and accidental changes to the core WordPress configuration.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return self::con()->opts->optIs( 'admin_access_restrict_options', 'Y' ) ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}