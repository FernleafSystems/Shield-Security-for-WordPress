<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionRoutingController,
	Exceptions,
	Utility\ExternalEntryGuard,
	Utility\ResponseEnvelopeNormalizer
};

class ShieldPluginAction extends Base {

	protected function process() :array {
		$req = $this->getWpRestRequest();
		$params = $req->get_params();
		$actionSlug = ActionData::TransportField( $params, ActionData::FIELD_EXECUTE );
		$payload = \is_array( $params[ 'payload' ] ?? null ) ? $params[ 'payload' ] : [];
		$guard = new ExternalEntryGuard();

		try {
			if ( !$guard->admit( $actionSlug, ExternalEntryGuard::ENTRY_REST ) ) {
				throw new Exceptions\ActionException( __( 'Action transport is not allowed.', 'wp-simple-firewall' ) );
			}
			// The action nonce travels in the REST URL query, outside the payload.
			$guard->requireProof( $actionSlug, ExternalEntryGuard::ENTRY_REST, [
				ActionData::FIELD_NONCE => ActionData::TransportField( $params, ActionData::FIELD_NONCE ),
			] );
			$routed = self::con()
				->action_router
				->action( $actionSlug, ExternalEntryGuard::ActionDataFromTransport( $payload ), ActionRoutingController::ACTION_REST );
			$data = $routed->payload();
		}
		catch ( Exceptions\ActionException $e ) {
			$data = [
				'success' => false,
			];
		}
		catch ( \Throwable $e ) {
			// A fault in the action, not a refusal. The request still gets a controlled response.
			\error_log( \sprintf( 'Shield REST action %s failed: %s', $actionSlug, $e->getMessage() ) );
			$data = [
				'success' => false,
			];
		}

		/** See AJAX normalised data */
		return [
			'success' => (bool)( $data[ 'success' ] ?? false ),
			'data'    => ResponseEnvelopeNormalizer::forRestProcess( $data )
		];
	}
}
