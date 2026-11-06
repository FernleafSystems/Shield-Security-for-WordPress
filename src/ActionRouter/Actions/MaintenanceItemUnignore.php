<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;

class MaintenanceItemUnignore extends BaseAction {

	use Traits\MaintenanceItemIdentifiers;
	use Traits\NonceVerifyRequired;

	public const SLUG = 'maintenance_item_unignore';
	public const ERROR_INVALID_KEY = 'maintenance_invalid_key';
	public const ERROR_MISSING_IDENTIFIER = 'maintenance_missing_identifier';

	protected function exec() {
		$provider = $this->buildMaintenanceIssueStateProvider();
		$key = \sanitize_key( $this->action_data[ 'maintenance_key' ] );
		$identifiers = $this->requestedIdentifiers();

		if ( !$provider->isKnownMaintenanceKey( $key ) ) {
			$this->fail( __( 'Invalid maintenance item.', 'wp-simple-firewall' ), self::ERROR_INVALID_KEY );
			return;
		}

		if ( $provider->supportsSubItems( $key ) ) {
			if ( empty( $identifiers ) || \in_array( '', $identifiers, true ) ) {
				$this->fail( __( 'A specific maintenance item identifier is required.', 'wp-simple-firewall' ), self::ERROR_MISSING_IDENTIFIER );
				return;
			}
		}
		else {
			$identifiers = [ MaintenanceIssueStateProvider::SINGLETON_TOKEN ];
		}

		$currentIssueIdentifiersByKey = $provider->currentIssueIdentifiersByKey();
		$ignoredItems = $provider->getNormalizedStoredIgnoredItems( $currentIssueIdentifiersByKey );
		$ignoredItems[ $key ] = \array_values( \array_diff( $ignoredItems[ $key ], $identifiers ) );

		self::con()->opts->optSet(
			MaintenanceIssueStateProvider::OPT_KEY,
			$provider->normalizeIgnoredItems( $ignoredItems, $currentIssueIdentifiersByKey )
		);

		$this->response()->setPayload( [
			'page_reload' => false,
			'message'     => __( 'Maintenance item restored.', 'wp-simple-firewall' ),
		] )->setPayloadSuccess( true );
	}

	protected function getRequiredDataKeys() :array {
		return [ 'maintenance_key' ];
	}

	protected function buildMaintenanceIssueStateProvider() :MaintenanceIssueStateProvider {
		return new MaintenanceIssueStateProvider();
	}

	private function fail( string $message, string $errorCode ) :void {
		$this->response()->setPayload( [
			'page_reload' => false,
			'error_code'  => $errorCode,
			'message'     => $message,
		] )->setPayloadSuccess( false );
	}
}
