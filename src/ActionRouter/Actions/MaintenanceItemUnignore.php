<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class MaintenanceItemUnignore extends MaintenanceItemIgnoreBase {

	public const SLUG = 'maintenance_item_unignore';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	/**
	 * Any item can be restored, even one that no longer needs attention.
	 */
	protected function unavailableMessage( array $identifiers, array $currentIssueIdentifiers, bool $hasSubItems ) :string {
		return '';
	}

	protected function changeIgnored( array $ignored, array $identifiers ) :array {
		return \array_values( \array_diff( $ignored, $identifiers ) );
	}

	protected function successMessage() :string {
		return __( 'Maintenance item restored.', 'wp-simple-firewall' );
	}
}
