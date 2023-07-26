<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;

/**
 * @deprecated 18.2
 */
class Upgrades extends Base {

	public function auditUpgrade2( $true, $hooksExtra ) {
		return $true;
	}

	/**
	 * @param \WP_Upgrader $handler
	 * @param array        $data
	 */
	public function auditUpgrades( $handler, $data ) {
	}

	private function handlePlugin( string $item ) {
	}

	/**
	 * uses "isset()" to prevent duplicates.
	 */
	private function handleTheme( string $item ) {
	}
}