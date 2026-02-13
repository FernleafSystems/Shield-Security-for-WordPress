<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionNonce,
	ActionProcessor,
	Constants,
	Exceptions\ActionException,
	Exceptions\InvalidActionNonceException,
	Exceptions\SecurityAdminRequiredException,
	Exceptions\UserAuthRequiredException,
	ResponseAdapter\AjaxResponseAdapter
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\{
	AnyUserAuthRequired,
	SecurityAdminNotRequired
};

class AjaxBatchRequests extends BaseAction {

	use AnyUserAuthRequired;
	use SecurityAdminNotRequired;

	public const SLUG = 'ajax_batch_requests';

	private const MAX_BATCH_SIZE = 20;

	protected function exec() {
		$requests = $this->action_data[ 'requests' ];

		if ( !\is_array( $requests ) ) {
			throw new ActionException( __( 'Invalid batch request format.', 'wp-simple-firewall' ) );
		}
		if ( \count( $requests ) > self::MAX_BATCH_SIZE ) {
			throw new ActionException(
				sprintf(
					/* translators: %s: request count limit */
					__( 'Too many batched requests. Maximum allowed is %s.', 'wp-simple-firewall' ),
					self::MAX_BATCH_SIZE
				)
			);
		}

		$lastRequestIndexes = $this->collectLastRequestIndexes( $requests );
		$results = [];
		foreach ( $requests as $index => $requestItem ) {
			$key = $this->extractResultKey( $requestItem, $index );
			if ( ( $lastRequestIndexes[ $key ] ?? $index ) !== $index ) {
				continue;
			}
			$results[ $key ] = $this->processBatchRequest( $requestItem );
		}

		$this->response()->action_response_data = [
			'success' => true,
			'message' => '',
			'results' => $results,
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'requests',
		];
	}

	/**
	 * @param mixed $requestItem
	 */
	private function extractResultKey( $requestItem, int $index ) :string {
		$key = \is_array( $requestItem ) && \is_string( $requestItem[ 'id' ] ?? null )
			? \trim( $requestItem[ 'id' ] )
			: '';
		return empty( $key ) ? 'item_'.$index : $key;
	}

	private function collectLastRequestIndexes( array $requests ) :array {
		$lastRequestIndexes = [];
		foreach ( $requests as $index => $requestItem ) {
			$key = $this->extractResultKey( $requestItem, $index );
			$lastRequestIndexes[ $key ] = $index;
		}
		return $lastRequestIndexes;
	}

	/**
	 * @param mixed $requestItem
	 */
	private function processBatchRequest( $requestItem ) :array {
		try {
			if ( !\is_array( $requestItem ) ) {
				throw new ActionException( __( 'Invalid batch request item.', 'wp-simple-firewall' ) );
			}

			$actionRequestData = $requestItem[ 'request' ] ?? null;
			if ( !\is_array( $actionRequestData ) ) {
				throw new ActionException( __( 'Missing request payload for batch request item.', 'wp-simple-firewall' ) );
			}

			$actionSlug = (string)( $actionRequestData[ ActionData::FIELD_EXECUTE ] ?? '' );
			$actionNonce = (string)( $actionRequestData[ ActionData::FIELD_NONCE ] ?? '' );
			if ( empty( $actionSlug ) || empty( $actionNonce ) ) {
				throw new ActionException( __( 'Missing action slug or nonce in batched request.', 'wp-simple-firewall' ) );
			}

			$subrequestPayload = $this->stripTransportFields( $actionRequestData );
			$action = ( new ActionProcessor() )->getAction( $actionSlug, $subrequestPayload );
			if ( $action::SLUG === self::SLUG ) {
				throw new ActionException( __( 'Nested batch requests are not allowed.', 'wp-simple-firewall' ), 400 );
			}

			if ( !ActionNonce::Verify( $action::SLUG, $actionNonce ) ) {
				throw new InvalidActionNonceException( __( 'Nonce Failed.', 'wp-simple-firewall' ) );
			}

			$action->setActionOverride( Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED, false );
			$action->process();
			$adapted = $this->adaptSubrequestResponse( $action->response() );
			$payload = $this->normaliseAjaxPayload( $this->sanitizeSubrequestPayload( $adapted[ 'payload' ] ) );

			return [
				'success'     => (bool)( $payload[ 'success' ] ?? false ),
				'status_code' => $adapted[ 'status_code' ],
				'data'        => $payload,
			];
		}
		catch ( InvalidActionNonceException $e ) {
			return $this->buildFailureResult( __( 'Nonce Failed.', 'wp-simple-firewall' ), 401 );
		}
		catch ( SecurityAdminRequiredException $e ) {
			return $this->buildFailureResult(
				\implode( ' ', [
					__( 'You must be authorised as a Security Admin to perform this action.', 'wp-simple-firewall' ),
					__( 'You may need to reload this page to continue.', 'wp-simple-firewall' ),
				] ),
				401
			);
		}
		catch ( UserAuthRequiredException $e ) {
			return $this->buildFailureResult( $e->getMessage(), 403 );
		}
		catch ( ActionException $e ) {
			return $this->buildFailureResult( $e->getMessage(), empty( $e->getCode() ) ? 400 : $e->getCode() );
		}
		catch ( \Throwable $e ) {
			return $this->buildFailureResult( __( 'There was a problem processing the batched request.', 'wp-simple-firewall' ), 500 );
		}
	}

	private function adaptSubrequestResponse( \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse $response ) :array {
		$statusCode = 200;
		$payload = $response->payload();

		// Batch item execution should not fail solely because transport adaptation fails.
		try {
			$routedResponse = ( new AjaxResponseAdapter() )->adapt( $response );
			$statusCode = $routedResponse->statusCode();
			$payload = $routedResponse->payload();
		}
		catch ( \Throwable $e ) {
			// Fallback to raw action payload to preserve independent batch item execution.
		}

		return [
			'status_code' => $statusCode,
			'payload'     => \is_array( $payload ) ? $payload : [],
		];
	}

	private function normaliseAjaxPayload( array $payload ) :array {
		return \array_merge( [
			'success'     => false,
			'page_reload' => false,
			'message'     => __( 'No AJAX message provided', 'wp-simple-firewall' ),
			'error'       => '',
			'html'        => '',
		], $payload );
	}

	private function sanitizeSubrequestPayload( array $payload ) :array {
		return \array_diff_key( $payload, \array_flip( [
			'action_data',
		] ) );
	}

	private function buildFailureResult( string $message, int $statusCode ) :array {
		return [
			'success'     => false,
			'status_code' => $statusCode,
			'error'       => $message,
			'data'        => $this->normaliseAjaxPayload( [
				'success' => false,
				'error'   => $message,
				'message' => $message,
			] ),
		];
	}

	private function stripTransportFields( array $requestData ) :array {
		return \array_diff_key(
			$requestData,
			\array_flip( [
				ActionData::FIELD_ACTION,
				ActionData::FIELD_EXECUTE,
				ActionData::FIELD_NONCE,
				ActionData::FIELD_WRAP_RESPONSE,
				ActionData::FIELD_AJAXURL,
				ActionData::FIELD_REST_NONCE,
				ActionData::FIELD_REST_URL,
				'shield_uniq',
			] )
		);
	}
}
