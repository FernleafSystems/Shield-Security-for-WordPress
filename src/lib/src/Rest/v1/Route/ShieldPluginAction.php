<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

class ShieldPluginAction extends Base {

	protected function getRouteArgsDefaults() :array {
		return [
			'ex'      => [
				'description' => 'Action To Execute',
				'type'        => 'string',
				'required'    => true,
				'readonly'    => true,
			],
			'exnonce' => [
				'description' => 'Action Nonce',
				'type'        => 'string',
				'required'    => false,
				'readonly'    => true,
			],
			'payload' => [
				'description' => 'Action Payload',
				'type'        => 'object',
				'default'     => [],
				'required'    => true,
				'readonly'    => true,
			],
		];
	}

	protected function verifyPermission( \WP_REST_Request $req ) {
		return true;
	}

	public function getRouteMethods() :array {
		return [ 'GET', 'POST' ];
	}

	public function getRoutePath() :string {
		return '/action/(?P<ex>[a-z_-]{3,})';
	}

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\ShieldPluginAction::class;
	}

	/**
	 * The entire REST API is available to cap:level_2 only, but the licenses endpoints are cap:level_1.
	 */
	public function isRouteAvailable() :bool {
		return self::con()->caps->canRestAPILevel1();
	}
}