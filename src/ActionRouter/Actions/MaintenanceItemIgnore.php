<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class MaintenanceItemIgnore extends MaintenanceItemIgnoreBase {

	public const SLUG = 'maintenance_item_ignore';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	/**
	 * Only items that need attention now can be ignored.
	 */
	protected function unavailableMessage( array $identifiers, array $currentIssueIdentifiers, bool $hasSubItems ) :string {
		if ( empty( \array_diff( $identifiers, $currentIssueIdentifiers ) ) ) {
			return '';
		}
		return $hasSubItems
			? __( 'The specified maintenance item cannot be ignored right now.', 'wp-simple-firewall' )
			: __( 'This maintenance item cannot be ignored right now.', 'wp-simple-firewall' );
	}

	protected function changeIgnored( array $ignored, array $identifiers ) :array {
		return \array_values( \array_merge( $ignored, $identifiers ) );
	}

	protected function successMessage() :string {
		return __( 'Maintenance item ignored.', 'wp-simple-firewall' );
	}
}
