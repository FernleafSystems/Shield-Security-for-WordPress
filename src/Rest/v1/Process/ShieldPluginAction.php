<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionRoutingController,
	Exceptions,
	ResponseAdapter\ResponseAdapterFactory,
	Utility\ExternalEntryGuard,
	Utility\ResponseEnvelopeNormalizer
};

class ShieldPluginAction extends Base {

	protected function process() :array {
		$req = $this->getWpRestRequest();
		$params = $req->get_params();
		$actionSlug = ActionData::TransportField( $params, ActionData::FIELD_EXECUTE );
		$payload = \is_array( $params[ 'payload' ] ?? null ) ? $params[ 'payload' ] : [];

		try {
			$data = ( new ResponseAdapterFactory() )
				->forActionType( ActionRoutingController::ACTION_REST )
				->adapt( ( new ExternalEntryGuard() )->dispatch(
					ExternalEntryGuard::ENTRY_REST,
					$actionSlug,
					// The action nonce travels in the REST URL query, outside the payload.
					\array_merge( $payload, [
						ActionData::FIELD_NONCE => ActionData::TransportField( $params, ActionData::FIELD_NONCE ),
					] )
				) )
				->payload();
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
