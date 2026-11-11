<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;

/**
 * Ignores, or stops ignoring, maintenance items of one kind: one item, or, from "Ignore all" and "Stop ignoring
 * all", every item of that kind together as a list of identifiers.
 */
abstract class MaintenanceItemIgnoreBase extends BaseAction {

	use Traits\NonceVerifyRequired;
	use Traits\RequestedIdentifiers;

	public const ERROR_IDENTIFIER_UNAVAILABLE = 'maintenance_identifier_unavailable';
	public const ERROR_INVALID_KEY = 'maintenance_invalid_key';
	public const ERROR_MISSING_IDENTIFIER = 'maintenance_missing_identifier';

	protected function exec() {
		$provider = $this->buildMaintenanceIssueStateProvider();
		$key = \sanitize_key( $this->action_data[ 'maintenance_key' ] );

		if ( !$provider->canBeIgnored( $key ) ) {
			$this->fail( __( 'Invalid maintenance item.', 'wp-simple-firewall' ), self::ERROR_INVALID_KEY );
			return;
		}

		$hasSubItems = $provider->supportsSubItems( $key );
		$identifiers = $hasSubItems ? $this->requestedIdentifiers() : [ MaintenanceIssueStateProvider::SINGLETON_TOKEN ];
		if ( $hasSubItems && ( empty( $identifiers ) || \in_array( '', $identifiers, true ) ) ) {
			$this->fail( __( 'A specific maintenance item identifier is required.', 'wp-simple-firewall' ), self::ERROR_MISSING_IDENTIFIER );
			return;
		}

		$currentIssueIdentifiersByKey = $provider->currentIssueIdentifiersByKey();
		$unavailable = $this->unavailableMessage( $identifiers, $currentIssueIdentifiersByKey[ $key ], $hasSubItems );
		if ( $unavailable !== '' ) {
			$this->fail( $unavailable, self::ERROR_IDENTIFIER_UNAVAILABLE );
			return;
		}

		$ignoredItems = $provider->getNormalizedStoredIgnoredItems( $currentIssueIdentifiersByKey );
		$ignoredItems[ $key ] = $this->changeIgnored( $ignoredItems[ $key ], $identifiers );

		self::con()->opts->optSet(
			MaintenanceIssueStateProvider::OPT_KEY,
			$provider->normalizeIgnoredItems( $ignoredItems, $currentIssueIdentifiersByKey )
		);

		$this->response()->setPayload( [
			'page_reload' => false,
			'message'     => $this->successMessage(),
		] )->setPayloadSuccess( true );
	}

	protected function getRequiredDataKeys() :array {
		return [ 'maintenance_key' ];
	}

	protected function buildMaintenanceIssueStateProvider() :MaintenanceIssueStateProvider {
		return new MaintenanceIssueStateProvider();
	}

	/**
	 * Why the items can't be changed right now, or nothing when they can.
	 * @param list<string> $identifiers
	 * @param list<string> $currentIssueIdentifiers
	 */
	abstract protected function unavailableMessage( array $identifiers, array $currentIssueIdentifiers, bool $hasSubItems ) :string;

	/**
	 * @param list<string> $ignored
	 * @param list<string> $identifiers
	 * @return list<string>
	 */
	abstract protected function changeIgnored( array $ignored, array $identifiers ) :array;

	abstract protected function successMessage() :string;

	private function fail( string $message, string $errorCode ) :void {
		$this->response()->setPayload( [
			'page_reload' => false,
			'error_code'  => $errorCode,
			'message'     => $message,
		] )->setPayloadSuccess( false );
	}
}
