<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionNonce,
	ActionProcessor,
	ActionResponse,
	Actions\BaseAction,
	Exceptions\ActionDoesNotExistException,
	Exceptions\ActionException,
	Exceptions\ActionNotAdmittedException,
	Exceptions\InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;

/**
 * The external boundary for routed actions.
 *
 * An action is reachable from an external entry point only if its own concrete class declares that
 * entry in ExternalAccess(). The declaration also states whether the entry point must verify an action
 * nonce for that exact action before the action runs, and whether that nonce is bound to the user's login
 * session. Declarations are never inherited, so a subclass of a reachable action is unreachable until it
 * declares its own access.
 *
 * Every entry point runs the action a request names through dispatch(), and in no other way. A request names
 * an action by its slug, which is only ever looked up among the registered actions.
 */
class ExternalEntryGuard {

	use PluginControllerConsumer;

	public const ENTRY_ORDINARY = 'ordinary';
	public const ENTRY_AJAX = 'ajax';
	public const ENTRY_REST = 'rest';
	public const ENTRY_MAINWP = 'mainwp';

	/**
	 * An action nonce bound to the user's login session. The proof to use unless there's a reason not to.
	 */
	public const PROOF_NONCE = 'nonce';
	/**
	 * An action nonce that works in any login session of the user it was issued to. Only for a nonce that
	 * leaves the page it was issued into, such as one in a link sent by email.
	 */
	public const PROOF_NONCE_ANY_SESSION = 'nonce_any_session';
	public const PROOF_NONE = 'none';

	public const ENTRIES = [
		self::ENTRY_ORDINARY,
		self::ENTRY_AJAX,
		self::ENTRY_REST,
		self::ENTRY_MAINWP,
	];

	public const PROOFS = [
		self::PROOF_NONCE,
		self::PROOF_NONCE_ANY_SESSION,
		self::PROOF_NONE,
	];

	/**
	 * Request fields that get a request to its action, or that the page adds for its own transport. They are
	 * not part of the action's data.
	 */
	private const ROUTING_FIELDS = [
		ActionData::FIELD_ACTION,
		ActionData::FIELD_EXECUTE,
		ActionData::FIELD_NONCE,
		ActionData::FIELD_WRAP_RESPONSE,
		ActionData::FIELD_AJAXURL,
		ActionData::FIELD_REST_NONCE,
		ActionData::FIELD_REST_URL,
		'shield_uniq',
	];

	/**
	 * Runs the action a request names, for the entry point the request arrived at: the action must be registered
	 * under that slug, declared for the entry, and its declared proof must hold. A refusal of a registered action
	 * is recorded. The action then runs on the request's own data and nothing else.
	 *
	 * @param string     $slug       the action's slug, as the request gave it
	 * @param array      $transport  the request data the entry point captured, including the action nonce
	 * @param array|null $formSource see ActionDataFromTransport()
	 * @throws ActionDoesNotExistException no registered action has that slug
	 * @throws ActionNotAdmittedException the action isn't declared for the entry point
	 * @throws InvalidActionNonceException the action's declared proof failed
	 * @throws ActionException the action refused the request
	 */
	public function dispatch( string $entry, string $slug, array $transport, ?array $formSource = null ) :ActionResponse {
		$action = ActionsMap::ActionFromRequest( $slug );
		if ( $action === '' ) {
			throw new ActionDoesNotExistException( 'There was no action handler available for '.esc_html( $slug ) );
		}
		if ( !$this->admit( $slug, $entry ) ) {
			throw new ActionNotAdmittedException( __( 'Action transport is not allowed.', 'wp-simple-firewall' ) );
		}
		try {
			$this->verifyProof( $slug, $entry, $transport );
		}
		catch ( InvalidActionNonceException $e ) {
			$this->recordRejection( $action, $entry );
			throw $e;
		}

		return ( new ActionProcessor() )->processAction( $action, self::ActionDataFromTransport( $transport, $formSource ) );
	}

	/**
	 * Whether an entry point admits the action a request names.
	 */
	public function isAdmitted( string $slug, string $entry ) :bool {
		return $this->proofFor( $slug, $entry ) !== '';
	}

	/**
	 * The admission check an entry point makes before it takes a request on: whether the entry admits the action
	 * the request names, recording the refusal when it doesn't. Requests for unknown slugs are not recorded.
	 */
	public function admit( string $slug, string $entry ) :bool {
		$admitted = $this->isAdmitted( $slug, $entry );
		if ( !$admitted ) {
			$action = ActionsMap::ActionFromRequest( $slug );
			if ( $action !== '' ) {
				$this->recordRejection( $action, $entry );
			}
		}
		return $admitted;
	}

	/**
	 * Whether a request carries the proof its action declares for the entry point. Nothing is recorded.
	 * @throws InvalidActionNonceException
	 */
	public function verifyProof( string $slug, string $entry, array $transport ) :void {
		$proof = $this->proofFor( $slug, $entry );
		if ( $proof === '' ) {
			throw new InvalidActionNonceException( 'Action is not declared for this entry point.' );
		}
		if ( $proof !== self::PROOF_NONE && !ActionNonce::Verify(
				ActionsMap::ActionFromRequest( $slug ),
				ActionData::TransportField( $transport, ActionData::FIELD_NONCE ),
				$proof === self::PROOF_NONCE
			) ) {
			throw new InvalidActionNonceException( 'Invalid Action Nonce.' );
		}
	}

	/**
	 * @return string the proof the action a request names declares for the entry point, or an empty string when
	 *                the entry point doesn't admit it.
	 */
	public function proofFor( string $slug, string $entry ) :string {
		$action = ActionsMap::ActionFromRequest( $slug );
		return $action === '' ? '' : ( self::DeclaredAccess( $action )[ $entry ] ?? '' );
	}

	/**
	 * The nonce an action's pages and links carry: the nonce proof it declares, or an empty string when none of
	 * its entries asks for a nonce. A nonce is issued once for the action, not per entry, and DeclaredAccess()
	 * leaves an action with one kind.
	 * @param class-string<BaseAction> $actionClass
	 */
	public static function DeclaredNonceProof( string $actionClass ) :string {
		$nonceProofs = \array_intersect(
			self::DeclaredAccess( $actionClass ),
			[ self::PROOF_NONCE, self::PROOF_NONCE_ANY_SESSION ]
		);
		return empty( $nonceProofs ) ? '' : \reset( $nonceProofs );
	}

	/**
	 * The data an action is given when it's reached from outside: the request data its entry point captured,
	 * without the fields that only routed the request, and with its form decoded. Actions read only their action
	 * data, never the global request.
	 *
	 * @param array|null $formSource where the form was submitted, when that's narrower than the transport. The
	 *                               ordinary route merges query and POST for routing, but a form is the POST body.
	 */
	public static function ActionDataFromTransport( array $transport, ?array $formSource = null ) :array {
		$routing = \array_flip( self::ROUTING_FIELDS );
		$data = \array_diff_key( $transport, $routing );
		$data[ ActionData::FIELD_FORM ] = FormParams::Decode( \array_diff_key( $formSource ?? $transport, $routing ) );
		return $data;
	}

	/**
	 * The access declared on the concrete class itself. Anything malformed is dropped, so a bad
	 * declaration fails closed. An action is issued one nonce, so declaring both kinds of nonce is malformed:
	 * the entries asking for a nonce that works in any session are the ones dropped.
	 * @param class-string<BaseAction> $actionClass
	 * @return array<string,string>
	 */
	public static function DeclaredAccess( string $actionClass ) :array {
		$declared = [];
		if ( \is_a( $actionClass, BaseAction::class, true ) ) {
			try {
				$method = new \ReflectionMethod( $actionClass, 'ExternalAccess' );
				if ( $method->getDeclaringClass()->getName() === \ltrim( $actionClass, '\\' ) ) {
					$access = $actionClass::ExternalAccess();
					foreach ( \is_array( $access ) ? $access : [] as $entry => $proof ) {
						if ( \in_array( $entry, self::ENTRIES, true ) && \in_array( $proof, self::PROOFS, true ) ) {
							$declared[ $entry ] = $proof;
						}
					}
				}
			}
			catch ( \ReflectionException $e ) {
			}
		}
		if ( \in_array( self::PROOF_NONCE, $declared, true ) ) {
			$declared = \array_diff( $declared, [ self::PROOF_NONCE_ANY_SESSION ] );
		}
		return $declared;
	}

	/**
	 * Records that an entry point refused a registered action, either because the action is not declared
	 * for that entry or because its proof failed.
	 * @param class-string<BaseAction> $actionClass
	 */
	private function recordRejection( string $actionClass, string $entry ) :void {
		self::con()->comps->events->fireEvent( 'action_request_rejected', [
			'audit_params' => [
				'action' => $actionClass::SLUG,
				'entry'  => $entry,
			],
		] );
	}
}
