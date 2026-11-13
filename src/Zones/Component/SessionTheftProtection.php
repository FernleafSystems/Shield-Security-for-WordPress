<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Services\Services;

class SessionTheftProtection extends Base {

	public function title() :string {
		return __( 'Session Hijacking Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Protect user sessions against the threat of hijacking and theft.', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$con = self::con();
		$lookup = $con->comps->opts_lookup;

		$status = parent::status();

		if ( empty( $con->opts->optGet( 'session_lock' ) ) && $lookup->getSessionIdleInterval() === 0 ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
		}
		elseif ( !empty( $con->opts->optGet( 'session_lock' ) ) && $lookup->getSessionIdleInterval() > 0 ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
		}

		if ( empty( $con->opts->optGet( 'session_lock' ) ) ) {
			$status[ 'exp' ][] = $this->explanationFixedBy( __( "It's good practice to lock a session at least 1 property to help prevent theft of the session.", 'wp-simple-firewall' ), 'session_lock' );
		}
		if ( $lookup->getSessionIdleInterval() === 0 ) {
			$status[ 'exp' ][] = $this->explanationFixedBy( __( "It's good practice to limit session lifetime, particularly when left idle.", 'wp-simple-firewall' ), 'session_idle_timeout_interval' );
		}
		$source = Services::Request()->getIpDetector()->getPublicRequestSource();
		if ( $source !== 'REMOTE_ADDR' && \in_array( 'ip', $con->opts->optGet( 'session_lock' ), true ) ) {
			$status[ 'level' ] = $status[ 'level' ] === EnumEnabledStatus::BAD ? EnumEnabledStatus::BAD : EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = sprintf( __( 'Session IP locking is enabled, but the current visitor IP source (%s) can weaken that protection.', 'wp-simple-firewall' ), sprintf( '<code>%s</code>', $source ) );
		}

		return $status;
	}
}
