<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\CentralDisabledException;
use WP_REST_Request as Req;

class RouteProcessorMap {

	use PluginControllerConsumer;

	/**
	 * @return \Closure[]
	 */
	public function map() :array {
		return [
			PairBootstrap::class => fn( Req $req ) :array => $this->guard(
				fn() :array => self::con()->comps->central->pairBootstrap( (string)$req->get_param( 'pairing_token' ), (string)$req->get_param( 'site_url' ) )
			),
			PairHealth::class    => fn( Req $req ) :array => $this->guard(
				fn() :array => self::con()->comps->central->pairHealth( (string)$req->get_param( 'pairing_session' ) )
			),
			PairFinalize::class  => fn( Req $req ) :array => $this->guard(
				fn() :array => self::con()->comps->central->pairFinalize( (string)$req->get_param( 'pairing_session' ), (string)$req->get_param( 'site_uuid' ) )
			),
			PairCleanup::class   => fn( Req $req ) :array => $this->guard(
				fn() :array => self::con()->comps->central->pairCleanup( (string)$req->get_param( 'pairing_session' ), (string)$req->get_param( 'site_uuid' ), $req->get_param( 'connection_material' ) === null ? null : $this->connectionMaterialFromRequest( $req ) )
			),
			SiteUnpair::class    => fn( Req $req ) :array => $this->guard(
				fn() :array => self::con()->comps->central->siteUnpair( (string)$req->get_param( 'site_uuid' ), $this->connectionMaterialFromRequest( $req ) )
			),
			SyncCollect::class   => fn( Req $req ) :array => $this->guard(
				fn() :array => self::con()->comps->central->syncCollect( (string)$req->get_param( 'site_uuid' ), $this->connectionMaterialFromRequest( $req ) )
			),
		];
	}

	private function connectionMaterialFromRequest( Req $req ) :array {
		$connectionMaterial = $req->get_param( 'connection_material' );
		return \is_array( $connectionMaterial ) ? $connectionMaterial : [];
	}

	private function guard( \Closure $processor ) :array {
		try {
			return $processor();
		}
		catch ( CentralDisabledException $e ) {
			return $this->failure( 'central_disabled', $e->getMessage() );
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
