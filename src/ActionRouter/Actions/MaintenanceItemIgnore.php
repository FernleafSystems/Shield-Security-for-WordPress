<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;

class MaintenanceItemIgnore extends BaseAction {

	use Traits\NonceVerifyRequired;

	public const SLUG = 'maintenance_item_ignore';
	public const ERROR_IDENTIFIER_UNAVAILABLE = 'maintenance_identifier_unavailable';
	public const ERROR_INVALID_KEY = 'maintenance_invalid_key';
	public const ERROR_MISSING_IDENTIFIER = 'maintenance_missing_identifier';

	protected function exec() {
		$provider = $this->buildMaintenanceIssueStateProvider();
		$key = \sanitize_key( $this->action_data[ 'maintenance_key' ] );
		$identifier = \trim( (string)( $this->action_data[ 'identifier' ] ?? '' ) );

		if ( !$provider->isKnownMaintenanceKey( $key ) ) {
			$this->fail( __( 'Invalid maintenance item.', 'wp-simple-firewall' ), self::ERROR_INVALID_KEY );
			return;
		}

		$currentIssueIdentifiersByKey = $provider->currentIssueIdentifiersByKey();
		$currentIssueIdentifiers = $currentIssueIdentifiersByKey[ $key ];

		if ( $provider->supportsSubItems( $key ) ) {
			if ( $identifier === '' ) {
				$this->fail( __( 'A specific maintenance item identifier is required.', 'wp-simple-firewall' ), self::ERROR_MISSING_IDENTIFIER );
				return;
			}
			if ( !\in_array( $identifier, $currentIssueIdentifiers, true ) ) {
				$this->fail( __( 'The specified maintenance item cannot be ignored right now.', 'wp-simple-firewall' ), self::ERROR_IDENTIFIER_UNAVAILABLE );
				return;
			}
		}
		else {
			$identifier = MaintenanceIssueStateProvider::SINGLETON_TOKEN;
			if ( !\in_array( $identifier, $currentIssueIdentifiers, true ) ) {
				$this->fail( __( 'This maintenance item cannot be ignored right now.', 'wp-simple-firewall' ), self::ERROR_IDENTIFIER_UNAVAILABLE );
				return;
			}
		}

		$ignoredItems = $provider->getNormalizedStoredIgnoredItems( $currentIssueIdentifiersByKey );
		$ignoredItems[ $key ][] = $identifier;

		self::con()->opts->optSet(
			MaintenanceIssueStateProvider::OPT_KEY,
			$provider->normalizeIgnoredItems( $ignoredItems, $currentIssueIdentifiersByKey )
		);

		$this->response()->setPayload( [
			'page_reload' => false,
			'message'     => __( 'Maintenance item ignored.', 'wp-simple-firewall' ),
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
