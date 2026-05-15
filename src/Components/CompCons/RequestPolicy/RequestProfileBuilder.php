<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Request\RequestTypeResolver;
use FernleafSystems\Wordpress\Services\Services;

class RequestProfileBuilder {

	use PluginControllerConsumer;

	public function build() :RequestProfile {
		$request = Services::Request();
		$resolver = new RequestTypeResolver();
		$type = $resolver->resolve();
		$method = \strtoupper( $request->getMethod() );
		$isMutation = RequestProfile::isMutationMethod( $method );

		return new RequestProfile( [
			'method'     => $method,
			'type'       => $type,
			'surface'    => $this->surface( $type, $isMutation, $resolver ),
			'path'       => (string)$request->getPath(),
			'rest_route' => self::con()->this_req->getRestRoute(),
		] );
	}

	private function surface( string $type, bool $isMutation, RequestTypeResolver $resolver ) :string {
		$req = self::con()->this_req;

		if ( $resolver->isShieldAction() ) {
			return RequestProfile::SURFACE_SHIELD_ACTION;
		}

		switch ( $type ) {
			case Handler::TYPE_XMLRPC:
				return RequestProfile::SURFACE_XMLRPC;

			case Handler::TYPE_LOGIN:
			case Handler::TYPE_2FA:
				return RequestProfile::SURFACE_AUTH_ATTEMPT;

			case Handler::TYPE_REST:
			case Handler::TYPE_MCP:
				return $isMutation ? RequestProfile::SURFACE_API_MUTATION : RequestProfile::SURFACE_API_READ;

			case Handler::TYPE_COMMENT:
				return RequestProfile::SURFACE_CONTENT_MUTATION;

			case Handler::TYPE_AJAX:
				return $isMutation ? RequestProfile::SURFACE_ADMIN_MUTATION : RequestProfile::SURFACE_API_READ;

			default:
				break;
		}

		if ( $this->isProbePath( (string)$req->path ) ) {
			return RequestProfile::SURFACE_PROBE;
		}

		if ( $isMutation ) {
			return $req->wp_is_admin ? RequestProfile::SURFACE_ADMIN_MUTATION : RequestProfile::SURFACE_CONTENT_MUTATION;
		}

		return RequestProfile::SURFACE_PUBLIC_READ;
	}

	private function isProbePath( string $path ) :bool {
		return \preg_match( '#(?:^|/)(?:wp-config\.php|\.env|phpinfo\.php|eval-stdin\.php)(?:$|[?])#i', $path ) === 1;
	}
}
