<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;

class MaintenanceItemUnignore extends SecurityAdminBase {

	public const SLUG = 'maintenance_item_unignore';

	protected function exec() {
		$provider = $this->buildMaintenanceIssueStateProvider();
		$key = \sanitize_key( $this->action_data[ 'maintenance_key' ] );
		$identifier = \trim( (string)( $this->action_data[ 'identifier' ] ?? '' ) );

		if ( !$provider->isKnownMaintenanceKey( $key ) ) {
			$this->fail( __( 'Invalid maintenance item.', 'wp-simple-firewall' ) );
			return;
		}

		if ( $provider->supportsSubItems( $key ) ) {
			if ( $identifier === '' ) {
				$this->fail( __( 'A specific maintenance item identifier is required.', 'wp-simple-firewall' ) );
				return;
			}
		}
		else {
			$identifier = MaintenanceIssueStateProvider::SINGLETON_TOKEN;
		}

		$currentIssueIdentifiersByKey = $provider->currentIssueIdentifiersByKey();
		$ignoredItems = $provider->normalizeIgnoredItems(
			self::con()->opts->optGet( MaintenanceIssueStateProvider::OPT_KEY ),
			$currentIssueIdentifiersByKey
		);
		$ignoredItems[ $key ] = \array_values( \array_diff( $ignoredItems[ $key ], [ $identifier ] ) );

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

	private function fail( string $message ) :void {
		$this->response()->setPayload( [
			'page_reload' => false,
			'message'     => $message,
		] )->setPayloadSuccess( false );
	}
}
