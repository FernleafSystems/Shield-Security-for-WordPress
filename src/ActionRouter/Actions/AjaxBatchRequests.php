<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionResponse,
	ActionRoutingController,
	Exceptions\ActionDoesNotExistException,
	Exceptions\ActionException,
	Exceptions\ActionNotAdmittedException,
	Exceptions\InvalidActionNonceException,
	Exceptions\SecurityAdminRequiredException,
	Exceptions\UserAuthRequiredException,
	ResponseAdapter\ResponseAdapterFactory,
	Utility\AuthRefreshRequest,
	Utility\ExternalEntryGuard,
	Utility\ResponseEnvelopeNormalizer
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\{
	AnyUserAuthRequired,
	SecurityAdminNotRequired
};

class AjaxBatchRequests extends BaseAction {

	use AnyUserAuthRequired;
	use SecurityAdminNotRequired;

	public const SLUG = 'ajax_batch_requests';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}
	public const ERROR_ACTION_NOT_FOUND = 'action_not_found';
	public const ERROR_ACTION_EXCEPTION = 'action_exception';
	public const ERROR_INVALID_NONCE = 'invalid_nonce';
	public const ERROR_NESTED_BATCH_REQUEST = 'nested_batch_request';
	public const ERROR_ACTION_TRANSPORT_NOT_ALLOWED = 'action_transport_not_allowed';
	public const ERROR_SECURITY_ADMIN_REQUIRED = 'security_admin_required';
	public const ERROR_UNEXPECTED = 'unexpected_error';
	public const ERROR_USER_AUTH_REQUIRED = 'user_auth_required';
	private const MAX_BATCH_SIZE = 50;

	protected function exec() {
		try {
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
			// The requests are a list, whatever keys the request gave them.
			$requests = \array_values( $requests );

			$lastRequestIndexes = $this->collectLastRequestIndexes( $requests );
			$results = [];
			foreach ( $requests as $index => $requestItem ) {
				$key = $this->extractResultKey( $requestItem, $index );
				if ( ( $lastRequestIndexes[ $key ] ?? $index ) !== $index ) {
					continue;
				}
				$results[ $key ] = $this->processBatchRequest( $requestItem );
			}

			$this->response()->setPayload( [
				'message' => '',
				'results' => $results,
			] )->setPayloadSuccess( true );
		}
		catch ( UserAuthRequiredException $e ) {
			if ( !AuthRefreshRequest::isRequested() ) {
				throw $e;
			}
			$this->response()
				->setPayload( ResponseEnvelopeNormalizer::forAjaxAuthRefresh() )
				->setPayloadSuccess( false );
		}
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

			$actionSlug = ActionData::TransportField( $actionRequestData, ActionData::FIELD_EXECUTE );
			if ( empty( $actionSlug ) ) {
				throw new ActionException( __( 'Missing action slug in batched request.', 'wp-simple-firewall' ) );
			}

			if ( $actionSlug === self::SLUG ) {
				return $this->buildFailureResult(
					__( 'Nested batch requests are not allowed.', 'wp-simple-firewall' ),
					400,
					self::ERROR_NESTED_BATCH_REQUEST
				);
			}

			// Each item is an AJAX request in its own right, so it passes the same boundary as a direct request.
			$adapted = $this->adaptSubrequestResponse(
				( new ExternalEntryGuard() )->dispatch( ExternalEntryGuard::ENTRY_AJAX, $actionSlug, $actionRequestData )
			);
			$payload = $this->normaliseAjaxPayload( $adapted[ 'payload' ] );

			return [
				'success'     => (bool)( $payload[ 'success' ] ?? false ),
				'status_code' => $adapted[ 'status_code' ],
				'data'        => $payload,
			];
		}
		catch ( ActionNotAdmittedException $e ) {
			return $this->buildFailureResult( $e->getMessage(), 400, self::ERROR_ACTION_TRANSPORT_NOT_ALLOWED );
		}
		catch ( InvalidActionNonceException $e ) {
			return $this->buildFailureResult( ResponseEnvelopeNormalizer::nonceFailedMessage(), 401, self::ERROR_INVALID_NONCE );
		}
		catch ( SecurityAdminRequiredException $e ) {
			return $this->buildFailureResult(
				ResponseEnvelopeNormalizer::securityAdminRequiredMessage(),
				401,
				self::ERROR_SECURITY_ADMIN_REQUIRED
			);
		}
		catch ( UserAuthRequiredException $e ) {
			if ( AuthRefreshRequest::isRequested() ) {
				throw $e;
			}
			return $this->buildFailureResult( $e->getMessage(), 403, self::ERROR_USER_AUTH_REQUIRED );
		}
		catch ( ActionDoesNotExistException $e ) {
			return $this->buildFailureResult( $e->getMessage(), 400, self::ERROR_ACTION_NOT_FOUND );
		}
		catch ( ActionException $e ) {
			return $this->buildFailureResult(
				$e->getMessage(),
				empty( $e->getCode() ) ? 400 : $e->getCode(),
				self::ERROR_ACTION_EXCEPTION
			);
		}
		catch ( \Throwable $e ) {
			return $this->buildFailureResult(
				__( 'There was a problem processing the batched request.', 'wp-simple-firewall' ),
				500,
				self::ERROR_UNEXPECTED
			);
		}
	}

	private function adaptSubrequestResponse( ActionResponse $response ) :array {
		// Each item is an AJAX response in its own right, adapted the same way as a direct AJAX request.
		$routedResponse = ( new ResponseAdapterFactory() )
			->forActionType( ActionRoutingController::ACTION_AJAX )
			->adapt( $response );

		return [
			'status_code' => $routedResponse->statusCode(),
			'payload'     => $routedResponse->payload(),
		];
	}

	private function normaliseAjaxPayload( array $payload ) :array {
		return ResponseEnvelopeNormalizer::forBatchSubresponse( $payload );
	}

	private function buildFailureResult( string $message, int $statusCode, string $errorCode ) :array {
		return [
			'success'     => false,
			'status_code' => $statusCode,
			'error_code'  => $errorCode,
			'error'       => $message,
			'data'        => $this->normaliseAjaxPayload( [
				'success'    => false,
				'error_code' => $errorCode,
				'error'      => $message,
				'message'    => $message,
			] ),
		];
	}
}
