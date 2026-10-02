<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\{
	BuildCapabilityReport,
	BuildSyncPayload,
	CentralDisabledException,
	ConnectionStore,
	PairingSessionStore,
	PairingTokenStore
};
use FernleafSystems\Wordpress\Services\Services;

class CentralController {

	use PluginControllerConsumer;

	public const PAIRING_TOKEN_TTL = 900;

	public function isEnabled() :bool {
		return (bool)\apply_filters( 'shield/central/enabled', false )
			   && self::con()->comps->opts_lookup->isPluginEnabled()
			   && !self::con()->this_req->is_force_off;
	}

	public function assertEnabled() :void {
		if ( !$this->isEnabled() ) {
			throw new CentralDisabledException( 'Central is disabled.' );
		}
	}

	/**
	 * @return array{pairing_token:string, ttl_seconds:int, expires_at:string}
	 */
	public function issuePairingToken( int $ttlSeconds = self::PAIRING_TOKEN_TTL ) :array {
		$this->assertEnabled();
		$token = \bin2hex( \random_bytes( 32 ) );
		( new PairingTokenStore() )->storeToken( $token, $ttlSeconds );
		return [
			'pairing_token' => $token,
			'ttl_seconds'   => $ttlSeconds,
			'expires_at'    => \gmdate( 'c', \time() + $ttlSeconds ),
		];
	}

	public function pairBootstrap( string $pairingToken, string $siteUrl ) :array {
		$this->assertEnabled();
		$tokenStore = new PairingTokenStore();
		if ( !$tokenStore->isValid( $pairingToken ) ) {
			return $this->failure( 'invalid_pairing_token', 'The ShieldCentral pairing token is invalid or expired.' );
		}

		$pairingSession = ( new PairingSessionStore() )->create(
			$pairingToken,
			$siteUrl
		);
		$tokenStore->forget( $pairingToken );

		return [
			'success'         => true,
			'pairing_session' => $pairingSession,
		];
	}

	public function pairHealth( string $pairingSession ) :array {
		$this->assertEnabled();
		if ( !( new PairingSessionStore() )->exists( $pairingSession ) ) {
			return $this->failure( 'invalid_pairing_session', 'The ShieldCentral pairing session is invalid or expired.' );
		}

		return [
			'success'            => true,
			'site_name'          => (string)\get_bloginfo( 'name' ),
			'wordpress_home_url' => Services::WpGeneral()->getHomeUrl(),
			'wordpress_site_url' => \site_url( '/' ),
			'capability_report'  => ( new BuildCapabilityReport() )->build(),
			'reported_channels'  => [ BuildSyncPayload::CHANNEL_REST ],
			'reported_actions'   => BuildSyncPayload::ACTIONS,
		];
	}

	public function pairFinalize( string $pairingSession, string $siteUuid ) :array {
		$this->assertEnabled();
		if ( !( new PairingSessionStore() )->exists( $pairingSession ) ) {
			return $this->failure( 'invalid_pairing_session', 'The ShieldCentral pairing session is invalid or expired.' );
		}

		( new PairingSessionStore() )->delete( $pairingSession );

		return \array_merge(
			[ 'success' => true ],
			( new ConnectionStore() )->create( $siteUuid )
		);
	}

	public function pairCleanup( string $pairingSession, string $siteUuid, ?array $connectionMaterial ) :array {
		$this->assertEnabled();
		( new PairingSessionStore() )->delete( $pairingSession );

		if ( $siteUuid !== '' || $connectionMaterial !== null ) {
			if ( !( new ConnectionStore() )->delete( $siteUuid, $connectionMaterial ?? [] ) ) {
				return $this->failure(
					'invalid_connection_material',
					'The ShieldCentral connection material is invalid.',
					true
				);
			}
		}

		return [ 'success' => true ];
	}

	public function siteUnpair( string $siteUuid, array $connectionMaterial ) :array {
		$this->assertEnabled();
		if ( !( new ConnectionStore() )->delete( $siteUuid, $connectionMaterial ) ) {
			return $this->failure(
				'invalid_connection_material',
				'The ShieldCentral connection material is invalid.',
				true
			);
		}

		return [ 'success' => true ];
	}

	public function syncCollect( string $siteUuid, array $connectionMaterial ) :array {
		$this->assertEnabled();
		if ( !( new ConnectionStore() )->verify( $siteUuid, $connectionMaterial ) ) {
			return $this->failure(
				'invalid_connection_material',
				'The ShieldCentral connection material is invalid.',
				true
			);
		}

		return ( new BuildSyncPayload() )->build();
	}

	private function failure( string $diagnosticCode, string $message, bool $reconnectRequired = false ) :array {
		return [
			'success'            => false,
			'message'            => $message,
			'diagnostic_code'    => $diagnosticCode,
			'reconnect_required' => $reconnectRequired,
		];
	}
}
