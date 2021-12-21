<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property bool $bypass_lock
 */
abstract class RouteBase extends \WP_REST_Controller {

	use ModConsumer;
	use DynProperties;

	/**
	 * @var bool
	 */
	private $registered;

	/**
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#examples
	 */
	public function register_routes() {
		if ( empty( $this->registered ) ) {
			$this->registered = true;
			if ( $this->isReady() ) {
				register_rest_route( $this->getNamespace(),
					$this->getRoutePath(),
					[ $this->buildRouteDefs() ]
				);
			}
		}
	}

	/**
	 * @return Base\Lib\Rest\Request\RequestVO
	 */
	public function getNewReqVO() {
		return new Base\Lib\Rest\Request\RequestVO();
	}

	/**
	 * @param array[] $aArgs
	 * @return array[]
	 */
	protected function applyArgsDefaults( $aArgs ) {
		return array_map(
			function ( $args ) {

				$args[ 'validate_callback' ] = function ( $value, $wpRequest, $reqArgKey ) {
					return $this->validateRequestArg( $value, $wpRequest, $reqArgKey );
				};

				$args[ 'sanitize_callback' ] = function ( $value, $wpRequest, $reqArgKey ) {
					return $this->sanitizeRequestArg( $value, $wpRequest, $reqArgKey );
				};

				return $args;
			},
			$aArgs
		);
	}

	public function buildRouteDefs() :array {
		return [
			'methods'             => $this->getArgMethods(),
			'callback'            => function ( \WP_REST_Request $req ) {
				return $this->executeApiRequest( $req );
			},
			'permission_callback' => function ( \WP_REST_Request $req ) {
				return $this->verifyPermission( $req );
			},
			'args'                => $this->applyArgsDefaults( $this->getRouteArgs() ),
		];
	}

	public function getCacheHandler() :RouteCache {
		return new RouteCache( $this );
	}

	protected function getNamespace() :string {
		$version = $this->getMod()
						->getOptions()
						->getDef( 'rest_version' );
		return sprintf( '%s/v%s',
			$this->getCon()->prefix(),
			is_numeric( $version ) ? $version : '1'
		);
	}

	abstract public function getRoutePath() :string;

	/**
	 * @return array[]
	 */
	protected function getRouteArgs() :array {
		return array_merge( $this->getRouteArgsDefaults(), $this->getRouteArgsCustom() );
	}

	/**
	 * @return array[]
	 */
	protected function getRouteArgsCustom() :array {
		return [];
	}

	/**
	 * @return array[][]
	 */
	protected function getRouteArgsDefaults() :array {
		return [];
	}

	protected function getRouteSlug() :string {
		try {
			return strtolower( ( new \ReflectionClass( $this ) )->getShortName() );
		}
		catch ( \ReflectionException $e ) {
			return substr( md5( get_class( $this ) ), 0, 6 );
		}
	}

	public function getArgMethods() :array {
		return [ \WP_REST_Server::READABLE ];
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function getWorkingDir() :string {
		$base = $this->getMod()->getRestHandler()->getWorkingDir();
		if ( !empty( $base ) ) {
			$dir = path_join( $base, 'r-'.$this->getRouteSlug() );
			Services::WpFs()->mkdir( $dir );
			if ( !empty( realpath( $dir ) ) ) {
				return $dir;
			}
		}
		throw new \Exception( 'Working directory not available' );
	}

	protected function isReady() :bool {
		try {
			$ready = $this->getWorkingDir() !== false;
		}
		catch ( \Exception $e ) {
			$ready = false;
		}
		return $ready;
	}

	/**
	 * @param \WP_REST_Request $wpReq
	 * @return \WP_REST_Response
	 */
	protected function executeApiRequest( \WP_REST_Request $wpReq ) :\WP_REST_Response {

		$apiResponse = $this->processRequest( $wpReq );
		$apiResponse[ 'meta' ][ 'params' ] = $wpReq->get_params();
		$apiResponse = $this->adjustApiResponse( $apiResponse, $wpReq );

		$response = new \WP_REST_Response();
		$response->set_data( $apiResponse );

		if ( $apiResponse[ 'error' ] ) {
			$response->set_status( empty( $apiResponse[ 'code' ] ) ? 500 : $apiResponse[ 'code' ] );
			$response->header( 'Cache-Control', 'no-cache, must-revalidate' );
		}
		else {
			$response->header( 'Cache-Control', 'public, max-age='.$this->getCacheHandler()->expiration );
			$response->set_status( 200 );
		}

		return $response;
	}

	protected function adjustApiResponse( array $response, \WP_REST_Request $wpReq ) :array {
		return $response;
	}

	abstract protected function processRequest( \WP_REST_Request $wpReq ) :array;

	/**
	 * @param string|mixed     $value
	 * @param \WP_REST_Request $wpRequest
	 * @param string           $reqArgKey
	 * @return \WP_Error|mixed
	 */
	public function sanitizeRequestArg( $value, \WP_REST_Request $wpRequest, string $reqArgKey ) {
		try {
			$value = rest_sanitize_request_arg( $value, $wpRequest, $reqArgKey );
			if ( !is_wp_error( $value ) ) {
				$value = $this->customSanitizeRequestArg( $value, $wpRequest, $reqArgKey );
			}
		}
		catch ( \Exception $e ) {
			$value = new \WP_Error( 400, $e->getMessage() );
		}
		return $value;
	}

	/**
	 * @param string|mixed     $value
	 * @param \WP_REST_Request $wpRequest
	 * @param string           $reqArgKey
	 * @return \WP_Error|bool
	 */
	public function validateRequestArg( $value, \WP_REST_Request $wpRequest, string $reqArgKey ) {
		try {
			$valid = rest_validate_request_arg( $value, $wpRequest, $reqArgKey );
			if ( $valid === true ) { // retain WP_ERROR info
				$valid = $this->customValidateRequestArg( $value, $wpRequest, $reqArgKey );
			}
		}
		catch ( \Exception $e ) {
			$valid = new \WP_Error( 400, $e->getMessage() );
		}
		return $valid;
	}

	/**
	 * @param mixed            $value
	 * @param \WP_REST_Request $wpRequest
	 * @param string           $reqArgKey
	 * @return \WP_Error|mixed
	 * @throws \Exception
	 */
	protected function customSanitizeRequestArg( $value, \WP_REST_Request $wpRequest, string $reqArgKey ) {
		return $value;
	}

	/**
	 * @param string|mixed     $value
	 * @param \WP_REST_Request $wpRequest
	 * @param string           $reqArgKey
	 * @return true
	 * @throws \Exception
	 */
	protected function customValidateRequestArg( $value, $wpRequest, $reqArgKey ) :bool {
		return true;
	}

	/**
	 * @param \WP_REST_Request $req
	 * @return \WP_Error|true
	 */
	protected function verifyPermission( \WP_REST_Request $req ) {
		return true;
	}
}