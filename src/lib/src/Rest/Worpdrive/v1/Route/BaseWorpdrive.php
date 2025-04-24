<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Process\StandardWorpdriveByCallable;
use FernleafSystems\Wordpress\Services\Utilities\{
	Net\IpID,
	ServiceProviders
};

abstract class BaseWorpdrive extends \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route\Base {

	use PluginControllerConsumer;

	public const ROUTE_METHOD = \WP_REST_Server::CREATABLE;

	protected function getRequestProcessorClass() :string {
		return StandardWorpdriveByCallable::class;
	}

	protected function verifyPermission( \WP_REST_Request $req ) {
		/** @var \WP_Error|bool $v */
		$v = apply_filters( 'shield/rest_api_verify_permission', parent::verifyPermission( $req ), $req );
		return $v === true
			   || ( ( ( is_wp_error( $v ) && $v->has_errors() ) || $v === false ) && $this->isRequestFromWorpdrive() );
	}

	protected function isRequestFromWorpdrive() :bool {
		try {
			$isShield = ( new IpID( self::con()->this_req->ip ) )->run()[ 0 ] === ServiceProviders::PROVIDER_WORPDRIVE;
		}
		catch ( \Exception $e ) {
			$isShield = false;
		}
		return $isShield;
	}

	/**
	 * @inheritDoc
	 */
	protected function processRequest( \WP_REST_Request $req ) :array {
		/** @var StandardWorpdriveByCallable $pro */
		$pro = $this->getRequestProcessor();
		return $pro->setWpRestRequest( $req )
				   ->setProcessCallable( RouteProcessorMap::Map()[ static::class ] )
				   ->run();
	}

	public function getRoutePathPrefix() :string {
		return '/worpdrive';
	}

	/**
	 * @inheritDoc
	 */
	protected function customValidateRequestArg( $value, \WP_REST_Request $request, string $reqArgKey ) {
		switch ( $reqArgKey ) {
			case 'dir':
				// Validate against split paths. If no split paths, then no \dirname()
				$valid = new \WP_Error( 'Directory provided does not appear to be valid.' );
				foreach (
					\array_filter( [
						'/'.\trim( wp_normalize_path( ABSPATH ) ),
						\dirname( wp_normalize_path( ABSPATH ) )
					] ) as $root
				) {
					if ( \str_starts_with( $value, $root ) ) {
						$valid = true;
					}
				}
				break;
			default:
				$valid = parent::customValidateRequestArg( $value, $request, $reqArgKey );
		}
		return $valid;
	}

	protected function getRouteArgsDefaults() :array {
		return [
			'uuid'       => [
				'description' => 'Request UUID',
				'type'        => 'string',
				'format'      => 'uuid',
				'required'    => true,
				'readonly'    => true,
			],
			'time_limit' => [
				'description' => 'Request Time Limit',
				'type'        => 'integer',
				'required'    => false,
				'readonly'    => true,
				'default'     => 0,
				'minimum'     => 0,
			],
		];
	}
}