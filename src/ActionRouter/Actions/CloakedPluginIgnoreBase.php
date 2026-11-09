<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	CloakedPluginFinding,
	CloakedPluginState
};

/**
 * Ignores, or stops ignoring, cloaked plugins: one, or, from "Ignore all" and "Stop ignoring all", several together.
 * @phpstan-import-type CloakedPluginFindingState from CloakedPluginState
 */
abstract class CloakedPluginIgnoreBase extends BaseAction {

	use Traits\NonceVerifyRequired;
	use Traits\RequestedIdentifiers;

	public const ERROR_IDENTIFIER_UNAVAILABLE = 'cloaked_plugin_identifier_unavailable';
	public const ERROR_MISSING_IDENTIFIER = 'cloaked_plugin_missing_identifier';

	protected function exec() {
		$identities = \array_map( 'trim', $this->requestedIdentifiers() );
		if ( \in_array( '', $identities, true ) || $identities === [] ) {
			$this->fail( __( 'A cloaked plugin identifier is required.', 'wp-simple-firewall' ), self::ERROR_MISSING_IDENTIFIER );
			return;
		}

		$state = $this->currentFindingState();
		$findings = \array_merge( $state[ 'active' ], $state[ 'ignored' ] );
		$cloakedPluginState = $this->buildCloakedPluginState();
		$hasChanged = false;
		// A plugin removed since the page was drawn can't be changed, but that needn't hold up the others.
		foreach ( $identities as $identity ) {
			$hasChanged = $this->applyIdentityChange( $cloakedPluginState, $identity, $findings ) || $hasChanged;
		}

		if ( !$hasChanged ) {
			$this->fail( __( 'The specified cloaked plugin cannot be changed right now.', 'wp-simple-firewall' ), self::ERROR_IDENTIFIER_UNAVAILABLE );
			return;
		}

		$this->response()->setPayload( [
			'page_reload' => false,
			'message'     => $this->successMessage(),
		] )->setPayloadSuccess( true );
	}

	/**
	 * @return CloakedPluginFindingState
	 */
	protected function currentFindingState() :array {
		return self::con()->comps->hidden_plugins->currentState();
	}

	protected function buildCloakedPluginState() :CloakedPluginState {
		return new CloakedPluginState();
	}

	/**
	 * @param list<CloakedPluginFinding> $currentFindings
	 */
	abstract protected function applyIdentityChange(
		CloakedPluginState $state,
		string $identity,
		array $currentFindings
	) :bool;

	abstract protected function successMessage() :string;

	private function fail( string $message, string $errorCode ) :void {
		$this->response()->setPayload( [
			'page_reload' => false,
			'error_code'  => $errorCode,
			'message'     => $message,
		] )->setPayloadSuccess( false );
	}
}
