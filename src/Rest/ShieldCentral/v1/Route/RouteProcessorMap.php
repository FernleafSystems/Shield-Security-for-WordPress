<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\{
	BuildCapabilityReport,
	BuildSyncPayload,
	ConnectionStore,
	PairingSessionStore,
	PairingTokenStore
};
use FernleafSystems\Wordpress\Services\Services;
use WP_REST_Request as Req;

class RouteProcessorMap {

	use PluginControllerConsumer;

	/**
	 * @return \Closure[]
	 */
	public function map() :array {
		return [
			PairBootstrap::class => fn( Req $req ) :array => $this->guard(
				fn() :array => $this->pairBootstrap( $req )
			),
			PairHealth::class    => fn( Req $req ) :array => $this->guard(
				fn() :array => $this->pairHealth( $req )
			),
			PairFinalize::class  => fn( Req $req ) :array => $this->guard(
				fn() :array => $this->pairFinalize( $req )
			),
			PairCleanup::class   => fn( Req $req ) :array => $this->guard(
				fn() :array => $this->pairCleanup( $req )
			),
			SiteUnpair::class    => fn( Req $req ) :array => $this->guard(
				fn() :array => $this->siteUnpair( $req )
			),
			SyncCollect::class   => fn( Req $req ) :array => $this->guard(
				fn() :array => $this->syncCollect( $req )
			),
		];
	}

	private function pairBootstrap( Req $req ) :array {
		$pairingToken = (string)$req->get_param( 'pairing_token' );
		$tokenStore = new PairingTokenStore();
		if ( !$tokenStore->isValid( $pairingToken ) ) {
			return $this->failure( 'invalid_pairing_token', 'The ShieldCentral pairing token is invalid or expired.' );
		}

		$pairingSession = ( new PairingSessionStore() )->create(
			$pairingToken,
			(string)$req->get_param( 'site_url' )
		);
		$tokenStore->forget( $pairingToken );

		return [
			'success'         => true,
			'pairing_session' => $pairingSession,
		];
	}

	private function pairHealth( Req $req ) :array {
		if ( !( new PairingSessionStore() )->exists( (string)$req->get_param( 'pairing_session' ) ) ) {
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

	private function pairFinalize( Req $req ) :array {
		$pairingSession = (string)$req->get_param( 'pairing_session' );
		if ( !( new PairingSessionStore() )->exists( $pairingSession ) ) {
			return $this->failure( 'invalid_pairing_session', 'The ShieldCentral pairing session is invalid or expired.' );
		}

		$siteUuid = (string)$req->get_param( 'site_uuid' );
		( new PairingSessionStore() )->delete( $pairingSession );

		return \array_merge(
			[ 'success' => true ],
			( new ConnectionStore() )->create( $siteUuid )
		);
	}

	private function pairCleanup( Req $req ) :array {
		( new PairingSessionStore() )->delete( (string)$req->get_param( 'pairing_session' ) );

		$siteUuid = (string)$req->get_param( 'site_uuid' );
		$connectionMaterial = $req->get_param( 'connection_material' );
		if ( $siteUuid !== '' || $connectionMaterial !== null ) {
			if ( !( new ConnectionStore() )->delete( $siteUuid, $this->connectionMaterialFromRequest( $req ) ) ) {
				return $this->failure(
					'invalid_connection_material',
					'The ShieldCentral connection material is invalid.',
					true
				);
			}
		}

		return [ 'success' => true ];
	}

	private function siteUnpair( Req $req ) :array {
		if ( !( new ConnectionStore() )->delete( (string)$req->get_param( 'site_uuid' ), $this->connectionMaterialFromRequest( $req ) ) ) {
			return $this->failure(
				'invalid_connection_material',
				'The ShieldCentral connection material is invalid.',
				true
			);
		}

		return [ 'success' => true ];
	}

	private function syncCollect( Req $req ) :array {
		if ( !( new ConnectionStore() )->verify( (string)$req->get_param( 'site_uuid' ), $this->connectionMaterialFromRequest( $req ) ) ) {
			return $this->failure(
				'invalid_connection_material',
				'The ShieldCentral connection material is invalid.',
				true
			);
		}

		return ( new BuildSyncPayload() )->build();
	}

	private function connectionMaterialFromRequest( Req $req ) :array {
		$connectionMaterial = $req->get_param( 'connection_material' );
		return \is_array( $connectionMaterial ) ? $connectionMaterial : [];
	}

	private function guard( \Closure $processor ) :array {
		try {
			return $processor();
		}
		catch ( \Throwable $e ) {
			return $this->failure( 'shieldcentral_unexpected_error', 'The ShieldCentral request could not be processed.' );
		}
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
