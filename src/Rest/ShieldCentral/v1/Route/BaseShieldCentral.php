<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Process\StandardShieldCentralByCallable;

abstract class BaseShieldCentral extends \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route\Base {

	use PluginControllerConsumer;

	public const ROUTE_METHOD = \WP_REST_Server::CREATABLE;

	public function getRoutePathPrefix() :string {
		return '/shield-central';
	}

	public function isRouteAvailable() :bool {
		return true;
	}

	protected function verifyPermission( \WP_REST_Request $req ) {
		unset( $req );
		return true;
	}

	protected function getRequestProcessorClass() :string {
		return StandardShieldCentralByCallable::class;
	}

	protected function processRequest( \WP_REST_Request $req ) :array {
		/** @var StandardShieldCentralByCallable $processor */
		$processor = $this->getRequestProcessor();
		return $processor->setWpRestRequest( $req )
						 ->setProcessCallable( ( new RouteProcessorMap() )->map()[ static::class ] )
						 ->run();
	}

	protected function getRouteArgsDefaults() :array {
		return [
			'request_id' => [
				'description' => 'ShieldCentral request ID.',
				'type'        => 'string',
				'required'    => false,
			],
		];
	}

	protected function stringArg( string $description, bool $required = true ) :array {
		return [
			'description' => $description,
			'type'        => 'string',
			'required'    => $required,
		];
	}
}
