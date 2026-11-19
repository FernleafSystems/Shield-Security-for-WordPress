<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

/**
 * Turns the Actions Queue's alerts for one kind of plugin or theme housekeeping off, from the queue itself.
 * It takes the kind out of the Review Next Items setting, which is where a kind is turned back on.
 */
class MaintenanceAlertsTurnOff extends BaseAction {

	public const SLUG = 'maintenance_alerts_turn_off';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$key = \sanitize_key( $this->fields()->string( 'maintenance_key', '' ) );
		if ( !( new MaintenanceIssueStateProvider() )->canBeLeftOut( $key ) ) {
			$this->response()->setPayload( [
				'page_reload' => false,
				'message'     => __( 'Invalid maintenance item.', 'wp-simple-firewall' ),
			] )->setPayloadSuccess( false );
			return;
		}

		$opts = self::con()->opts;
		$opts->optSet(
			MaintenanceIssueStateProvider::OPT_KEY_REPORTED_KINDS,
			\array_values( \array_diff( $opts->optGet( MaintenanceIssueStateProvider::OPT_KEY_REPORTED_KINDS ), [ $key ] ) )
		)->store();

		$this->response()->setPayload( [
			'page_reload' => false,
			'message'     => __( 'Alerts turned off.', 'wp-simple-firewall' ),
		] )->setPayloadSuccess( true );
	}
}
