<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	CloakedPluginFinding,
	CloakedPluginState
};

/**
 * @phpstan-import-type CloakedPluginFindingState from CloakedPluginState
 */
abstract class CloakedPluginIgnoreBase extends BaseAction {

	use Traits\NonceVerifyRequired;

	public const ERROR_IDENTIFIER_UNAVAILABLE = 'cloaked_plugin_identifier_unavailable';
	public const ERROR_MISSING_IDENTIFIER = 'cloaked_plugin_missing_identifier';

	protected function exec() {
		$identity = \trim( (string)$this->action_data[ 'finding_id' ] );
		if ( $identity === '' ) {
			$this->fail( __( 'A cloaked plugin identifier is required.', 'wp-simple-firewall' ), self::ERROR_MISSING_IDENTIFIER );
			return;
		}

		$state = $this->currentFindingState();
		if ( !$this->applyIdentityChange(
			$this->buildCloakedPluginState(),
			$identity,
			\array_merge( $state[ 'active' ], $state[ 'ignored' ] )
		) ) {
			$this->fail( __( 'The specified cloaked plugin cannot be changed right now.', 'wp-simple-firewall' ), self::ERROR_IDENTIFIER_UNAVAILABLE );
			return;
		}

		$this->response()->setPayload( [
			'page_reload' => false,
			'message'     => $this->successMessage(),
		] )->setPayloadSuccess( true );
	}

	protected function getRequiredDataKeys() :array {
		return [ 'finding_id' ];
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
