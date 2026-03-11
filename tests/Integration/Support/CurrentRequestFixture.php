<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Request as ServicesRequest;

trait CurrentRequestFixture {

	protected function snapshotCurrentRequestState() :array {
		return [
			'server'   => $_SERVER,
			'query'    => $_GET,
			'post'     => $_POST,
			'services' => ServicesState::snapshot(),
			'this_req' => self::con()->this_req ?? null,
		];
	}

	protected function restoreCurrentRequestState( array $snapshot ) :void {
		$_SERVER = \is_array( $snapshot[ 'server' ] ?? null ) ? $snapshot[ 'server' ] : [];
		$_GET = \is_array( $snapshot[ 'query' ] ?? null ) ? $snapshot[ 'query' ] : [];
		$_POST = \is_array( $snapshot[ 'post' ] ?? null ) ? $snapshot[ 'post' ] : [];

		ServicesState::restore( \is_array( $snapshot[ 'services' ] ?? null ) ? $snapshot[ 'services' ] : [] );

		if ( isset( $snapshot[ 'this_req' ] ) && $snapshot[ 'this_req' ] instanceof ThisRequest ) {
			self::con()->this_req = $snapshot[ 'this_req' ];
		}
	}

	protected function applyCurrentRequestState(
		array $server,
		array $query = [],
		array $post = [],
		array $requestOverrides = []
	) :ThisRequest {
		$host = (string)\wp_parse_url( \home_url(), \PHP_URL_HOST );
		$_SERVER = \array_merge( $_SERVER, [
			'HTTP_HOST'       => empty( $host ) ? 'example.org' : $host,
			'HTTP_USER_AGENT' => 'phpunit',
			'REMOTE_ADDR'     => '198.51.100.25',
			'REQUEST_METHOD'  => 'GET',
			'REQUEST_URI'     => '/',
		], $server );
		$_GET = $query;
		$_POST = $post;

		$request = new ServicesRequest();
		ServicesState::mergeItems( [
			'service_request' => $request,
		] );

		$currentRequest = self::con()->this_req ?? null;

		$thisRequest = new ThisRequest( \array_merge( [
			'request'                  => $request,
			'path'                     => empty( $request->getPath() ) ? '/' : $request->getPath(),
			'wp_is_ajax'               => false,
			'wp_is_permalinks_enabled' => true,
			'rest_api_root'            => \rest_url(),
			'is_security_admin'        => $currentRequest instanceof ThisRequest ? (bool)$currentRequest->is_security_admin : false,
		], $requestOverrides ) );
		self::con()->this_req = $thisRequest;

		return $thisRequest;
	}

	protected function applyCurrentShieldAjaxRequest( array $post, bool $isSecurityAdmin ) :ThisRequest {
		return $this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-admin/admin-ajax.php',
			],
			[],
			$post,
			[
				'path'              => '/wp-admin/admin-ajax.php',
				'wp_is_ajax'        => true,
				'is_security_admin' => $isSecurityAdmin,
			]
		);
	}

	protected function snapshotCurrentRequestBags() :array {
		$servicesRequest = ServicesState::snapshot()[ 'items' ][ 'service_request' ] ?? null;
		if ( !$servicesRequest instanceof ServicesRequest ) {
			$servicesRequest = new ServicesRequest();
			ServicesState::mergeItems( [
				'service_request' => $servicesRequest,
			] );
		}

		$thisRequest = self::con()->this_req->request;

		return [
			'services_query' => \is_array( $servicesRequest->query ) ? $servicesRequest->query : [],
			'services_post'  => \is_array( $servicesRequest->post ) ? $servicesRequest->post : [],
			'this_query'     => \is_array( $thisRequest->query ) ? $thisRequest->query : [],
			'this_post'      => \is_array( $thisRequest->post ) ? $thisRequest->post : [],
		];
	}

	protected function restoreCurrentRequestBags( array $snapshot ) :void {
		$servicesRequest = \FernleafSystems\Wordpress\Services\Services::Request();
		$thisRequest = self::con()->this_req->request;

		$servicesRequest->query = \is_array( $snapshot[ 'services_query' ] ?? null ) ? $snapshot[ 'services_query' ] : [];
		$servicesRequest->post = \is_array( $snapshot[ 'services_post' ] ?? null ) ? $snapshot[ 'services_post' ] : [];
		$thisRequest->query = \is_array( $snapshot[ 'this_query' ] ?? null ) ? $snapshot[ 'this_query' ] : [];
		$thisRequest->post = \is_array( $snapshot[ 'this_post' ] ?? null ) ? $snapshot[ 'this_post' ] : [];
	}

	protected function mergeCurrentRequestTransport( array $transport ) :void {
		$servicesRequest = \FernleafSystems\Wordpress\Services\Services::Request();
		$thisRequest = self::con()->this_req->request;

		$servicesRequest->query = \array_merge( \is_array( $servicesRequest->query ) ? $servicesRequest->query : [], $transport );
		$servicesRequest->post = \array_merge( \is_array( $servicesRequest->post ) ? $servicesRequest->post : [], $transport );
		$thisRequest->query = \array_merge( \is_array( $thisRequest->query ) ? $thisRequest->query : [], $transport );
		$thisRequest->post = \array_merge( \is_array( $thisRequest->post ) ? $thisRequest->post : [], $transport );
	}

	protected function canonicalShieldTransportFor( string $actionClass ) :array {
		$actionData = ActionData::Build( $actionClass, false );

		return [
			ActionData::FIELD_ACTION  => (string)( $actionData[ ActionData::FIELD_ACTION ] ?? ActionData::FIELD_SHIELD ),
			ActionData::FIELD_EXECUTE => (string)( $actionData[ ActionData::FIELD_EXECUTE ] ?? '' ),
			ActionData::FIELD_NONCE   => (string)( $actionData[ ActionData::FIELD_NONCE ] ?? '' ),
		];
	}
}
