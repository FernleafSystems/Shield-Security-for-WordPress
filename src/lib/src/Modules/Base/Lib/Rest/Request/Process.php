<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Cache;

abstract class Process {

	use ModConsumer;
	use Rest\Route\RestRouteConsumer;

	/**
	 * @var RequestVO
	 */
	private $reqVO;

	/**
	 * @var \WP_REST_Request
	 */
	protected $wpRestRequest;

	/**
	 * Process constructor.
	 * @param Rest\Route\RouteBase|mixed $route
	 * @param \WP_REST_Request           $restRequest
	 */
	public function __construct( $route, \WP_REST_Request $restRequest ) {
		$this->setRestRoute( $route );
		$this->wpRestRequest = $restRequest;
	}

	/**
	 * @return array
	 */
	public function run() :array {
		$route = $this->getRestRoute();

		$apiResponse = [
			'meta' => [
				'ts'          => Services::Request()->ts(),
				'api_version' => $this->getCon()->getVersion(),
				'from_cache'  => false
			],
		];

		$locked = false;
		try {
			if ( $route->bypass_lock !== true ) {
				$locker = ( new Base\Lib\Rest\Utility\RestLocker() )->setRestRoute( $route );
				$locked = $locker->start();
			}

			// Ensure only valid parameters are supplied
			$permittedParams = array_keys( $this->wpRestRequest->get_attributes()[ 'args' ] );
			if ( count( array_diff_key(
					$this->wpRestRequest->get_params(),
					array_flip( $permittedParams )
				) ) > 0 ) {
				throw new \Exception(
					sprintf( 'Please only supply parameters that are permitted: %s',
						implode( ', ', $permittedParams ) ), 500 );
			}

			// Is the site ready?
			( new Base\Lib\Rest\Utility\PreChecks() )
				->setMod( $this->getMod() )
				->run();

			// Begin processing.

			$cacher = $route->getCacheHandler();
			$cacher->request_file = $this->getCacheFileFragment();
			$cacheDef = $cacher->getCacheDefinition();
			if ( $cacher->can_cache ) {
				( new Cache\LoadFromCache() )
					->setCacheDef( $cacheDef )
					->load();
				if ( is_array( $cacheDef->data ) ) {
					$apiResponse[ 'meta' ][ 'from_cache' ] = true;
				}
			}

			if ( !is_array( $cacheDef->data ) ) {
				$cacheDef->data = $this->process();
				if ( $cacher->can_cache ) {
					( new Cache\StoreToCache() )
						->setCacheDef( $cacheDef )
						->store();
				}
			}

			$apiResponse[ 'error' ] = false;
			$apiResponse[ $this->getResponseResultsDataKey() ] = $cacheDef->data;
		}
		catch ( \Exception $e ) {
			$apiResponse[ 'error' ] = true;
			$apiResponse[ 'code' ] = $e->getCode();
			$apiResponse[ 'message' ] = $e->getMessage();
			$apiResponse[ $this->getResponseResultsDataKey() ] = [];
		}

		if ( $locked && !empty( $locker ) ) {
			$locker->end();
		}

		return $apiResponse;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	abstract protected function process() :array;

	protected function getResponseResultsDataKey() :string {
		return $this->getMod()->getModSlug( false );
	}

	protected function getWpRestRequest() :\WP_REST_Request {
		return $this->wpRestRequest;
	}

	/**
	 * @return RequestVO|mixed
	 */
	protected function getRequestVO() {
		if ( !isset( $this->reqVO ) ) {
			$this->reqVO = $this->getRestRoute()
								->getNewReqVO()
								->applyFromArray( $this->wpRestRequest->get_params() );
		}
		return $this->reqVO;
	}

	protected function getCacheFileFragment() :string {
		$d = $this->getWpRestRequest()->get_params();
		ksort( $d );
		return md5( serialize( $d ) );
	}
}
