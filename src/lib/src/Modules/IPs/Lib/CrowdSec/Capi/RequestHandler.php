<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Capi;

use AptowebDeps\CrowdSec\CapiClient\Client\CapiHandler\CapiHandlerInterface;
use AptowebDeps\CrowdSec\Common\Client\ClientException;
use FernleafSystems\Wordpress\Services\Utilities\URL;
use AptowebDeps\CrowdSec\Common\Client\HttpMessage\{
	Request,
	Response
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class RequestHandler implements CapiHandlerInterface {

	use PluginControllerConsumer;

	public function getListDecisions( string $url, array $headers = [] ) :string {
		return 'not yet implemented';
	}

	public function handle( Request $request ) :Response {
		$result = wp_remote_request( $this->buildUri( $request ), $this->buildWpRemoteRequest( $request ) );
		if ( is_wp_error( $result ) ) {
			throw new ClientException( $result->get_error_message() );
		}
		/** @var \WP_HTTP_Requests_Response $res */
		$res = $result[ 'http_response' ];
		return new Response( $res->get_data(), $res->get_status(), $res->get_headers()->getAll() );
	}

	protected function buildUri( Request $request ) :string {
		return $request->getMethod() === 'GET' ? $request->getUri() : URL::Build( $request->getUri(), $request->getParams() );
	}

	protected function buildWpRemoteRequest( Request $request ) :array {
		return [
			'method'     => \strtoupper( $request->getMethod() ),
			'headers'    => $request->getHeaders(),
			'user-agent' => $this->getApiUserAgent(),
			'body'       => $request->getMethod() === 'GET' ? '' : \wp_json_encode( $request->getParams() ),
		];
	}

	private function getApiUserAgent() :string {
		$con = self::con();
		return sprintf( '%s/v%s', $con->isPremiumActive() ? 'ShieldSecurityPro' : 'ShieldSecurity', $con->cfg->version() );
	}
}