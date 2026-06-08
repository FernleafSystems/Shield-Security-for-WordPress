<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Host\ShieldWorpdriveHost;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Process\StandardWorpdriveByCallable;
use FernleafSystems\Wordpress\Services\Utilities\{
	Net\IpID,
	ServiceProviders
};
use FernleafSystems\WorpdriveClient\Host\WorpdriveRuntime;

abstract class BaseWorpdrive extends \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route\Base {

	use PluginControllerConsumer;

	public const ROUTE_METHOD = \WP_REST_Server::CREATABLE;

	protected function getRequestProcessorClass() :string {
		return StandardWorpdriveByCallable::class;
	}

	protected function verifyPermission( \WP_REST_Request $req ) {
		/** @var \WP_Error|bool $v */
		$v = apply_filters( 'shield/rest_api_verify_permission', parent::verifyPermission( $req ), $req );
		return $v === true || $this->isVerifiedWorpdriveRequest( $req );
	}

	protected function isVerifiedWorpdriveRequest( \WP_REST_Request $req ) :bool {
		try {
			return !empty( $req->get_param( 'auth_key' ) ) && !empty( $this->getStoredAuth() )
				   && \hash_equals( $this->getStoredAuth(), \hash( 'sha512', $req->get_param( 'auth_key' ) ) )
				   && IpID::IsIpInServiceCollection( self::con()->this_req->ip, ServiceProviders::PROVIDER_WORPDRIVE );
		}
		catch ( \Exception $e ) {
			return false;
		}
	}

	private function getStoredAuth() :string {
		return self::con()->comps->license->getLicense()->auth_key ?? '';
	}

	/**
	 * @inheritDoc
	 */
	protected function processRequest( \WP_REST_Request $req ) :array {
		/** @var StandardWorpdriveByCallable $pro */
		$pro = $this->getRequestProcessor();
		return WorpdriveRuntime::withHost(
			new ShieldWorpdriveHost(),
			fn() => $pro->setWpRestRequest( $req )
						->setProcessCallable( ( new RouteProcessorMap() )->map()[ static::class ] )
						->run()
		);
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
				$valid = new \WP_Error( 'Directory provided does not appear to be valid.' );
				if ( \is_string( $value ) ) {
					$dir = \trailingslashit( wp_normalize_path( $value ) );
					$normAbs = wp_normalize_path( ABSPATH );
					foreach (
						\array_filter( [
							\trailingslashit( $normAbs ),
							\trailingslashit( \dirname( $normAbs ) ),
						] ) as $root
					) {
						if ( $dir === $root ) {
							$valid = true;
							break;
						}
					}
				}
				break;
			default:
				$valid = parent::customValidateRequestArg( $value, $request, $reqArgKey );
				break;
		}
		return $valid;
	}

	protected function getRouteArgsDefaults() :array {
		return [
			'auth_key'   => [
				'description' => 'Auth Key used to verify request from known WorpDrive sources',
				'type'        => 'string',
				'required'    => true,
			],
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
