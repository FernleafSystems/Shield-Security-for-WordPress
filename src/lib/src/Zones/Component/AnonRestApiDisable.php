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

	protected function tooltip() :string {
		return __( 'Enable or disable access to the anonymous Rest API', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( self::con()->opts->optIs( 'disable_anonymous_restapi', 'Y' ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = __( "Anonymous (unauthenticated) requests to the WP REST API is allowed.", 'wp-simple-firewall' );
		}

		return $status;
	}
}