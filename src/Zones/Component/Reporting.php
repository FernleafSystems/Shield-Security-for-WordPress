<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Services\Services;

class Reporting extends Base {

	public function title() :string {
		return __( 'Reporting', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( "See what's happening with reports.", 'wp-simple-firewall' );
	}

	protected function status() :array {
		$status = parent::status();
		$email = (string)self::con()->opts->optGet( 'block_send_email_address' );
		if ( Services::Data()->validEmail( $email ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
			$status[ 'exp' ][] = __( 'A valid report email address is configured for security reporting.', 'wp-simple-firewall' );
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = __( 'No dedicated report email address is configured. A default fallback will be used.', 'wp-simple-firewall' );
		}
		return $status;
	}
}
