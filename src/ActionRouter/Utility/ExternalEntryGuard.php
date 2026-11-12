<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionNonce,
	Actions\BaseAction,
	Exceptions\InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;

/**
 * The external boundary for routed actions.
 *
 * An action is reachable from an external entry point only if its own concrete class declares that
 * entry in ExternalAccess(). The declaration also states whether the entry point must verify an action
 * nonce for that exact action before the action runs. Declarations are never inherited, so a subclass
 * of a reachable action is unreachable until it declares its own access.
 */
class ExternalEntryGuard {

	use PluginControllerConsumer;

	public const ENTRY_ORDINARY = 'ordinary';
	public const ENTRY_AJAX = 'ajax';
	public const ENTRY_REST = 'rest';
	public const ENTRY_MAINWP = 'mainwp';

	public const PROOF_NONCE = 'nonce';
	public const PROOF_NONE = 'none';

	public const ENTRIES = [
		self::ENTRY_ORDINARY,
		self::ENTRY_AJAX,
		self::ENTRY_REST,
		self::ENTRY_MAINWP,
	];

	public const PROOFS = [
		self::PROOF_NONCE,
		self::PROOF_NONE,
	];

	public function isAdmitted( string $classOrSlug, string $entry ) :bool {
		return $this->proofFor( $classOrSlug, $entry ) !== '';
	}

	/**
	 * The admission check an entry point makes for a request: whether the entry admits the action, recording the
	 * refusal when it doesn't.
	 */
	public function admit( string $classOrSlug, string $entry ) :bool {
		$admitted = $this->isAdmitted( $classOrSlug, $entry );
		if ( !$admitted ) {
			$this->recordRejection( $classOrSlug, $entry );
		}
		return $admitted;
	}

	/**
	 * The proof check an entry point makes for a request, recording the refusal when the proof fails.
	 * @throws InvalidActionNonceException
	 */
	public function requireProof( string $classOrSlug, string $entry, array $transport ) :void {
		try {
			$this->verifyProof( $classOrSlug, $entry, $transport );
		}
		catch ( InvalidActionNonceException $e ) {
			$this->recordRejection( $classOrSlug, $entry );
			throw $e;
		}
	}

	/**
	 * @throws InvalidActionNonceException
	 */
	public function verifyProof( string $classOrSlug, string $entry, array $transport ) :void {
		$proof = $this->proofFor( $classOrSlug, $entry );
		if ( $proof === '' ) {
			throw new InvalidActionNonceException( 'Action is not declared for this entry point.' );
		}
		if ( $proof === self::PROOF_NONCE
			 && !ActionNonce::Verify( $classOrSlug, ActionData::TransportField( $transport, ActionData::FIELD_NONCE ) ) ) {
			throw new InvalidActionNonceException( 'Invalid Action Nonce.' );
		}
	}

	/**
	 * The action data an entry point passes to the action it admits: the request data captured by that entry
	 * point, with its form decoded. Actions read only their action data, never the global request.
	 *
	 * @param array|null $formSource where the form was submitted, when that's narrower than the transport. The
	 *                               ordinary route merges query and POST for routing, but a form is the POST body.
	 */
	public static function ActionDataFromTransport( array $transport, ?array $formSource = null ) :array {
		$transport[ ActionData::FIELD_FORM ] = FormParams::Decode( $formSource ?? $transport );
		return $transport;
	}

	/**
	 * Records that an entry point refused a registered action, either because the action is not declared
	 * for that entry or because its proof failed. Requests for unknown slugs are not recorded.
	 */
	public function recordRejection( string $classOrSlug, string $entry ) :void {
		$action = ActionsMap::ActionFromSlug( $classOrSlug );
		if ( !empty( $action ) ) {
			self::con()->comps->events->fireEvent( 'action_request_rejected', [
				'audit_params' => [
					'action' => $action::SLUG,
					'entry'  => $entry,
				],
			] );
		}
	}

	/**
	 * @return string the declared proof, or an empty string when the action is not admitted.
	 */
	public function proofFor( string $classOrSlug, string $entry ) :string {
		$action = ActionsMap::ActionFromSlug( $classOrSlug );
		$proof = empty( $action ) ? '' : ( self::DeclaredAccess( $action )[ $entry ] ?? '' );
		return \in_array( $proof, self::PROOFS, true ) ? $proof : '';
	}

	/**
	 * The access declared on the concrete class itself. Anything malformed is dropped, so a bad
	 * declaration fails closed.
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
		return $declared;
	}
}
