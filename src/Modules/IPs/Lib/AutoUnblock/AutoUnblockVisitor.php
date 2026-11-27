<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockVisitor extends BaseAutoUnblockShield {

	public function canRunAutoUnblockProcess() :bool {
		return parent::canRunAutoUnblockProcess() && Services::Request()->isPost();
	}

	public function isUnblockAvailable() :bool {
		return \in_array( 'gasp', self::con()->opts->optGet( 'user_auto_recover' ) ) && parent::isUnblockAvailable();
	}

	protected function preUnblockChecks() :bool {
		parent::preUnblockChecks();

		if ( ( $this->submission[ '_confirm' ] ?? '' ) !== 'Y' ) {
			throw new \Exception( __( 'No confirmation checkbox.', 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: caught by BaseAutoUnblock::processAutoUnblockRequest(), which returns false, never shown.
		}
		if ( !empty( $this->submission[ 'email' ] ) || !empty( $this->submission[ 'name' ] ) ) {
			throw new \Exception( __( 'Oh so yummy.', 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: caught by BaseAutoUnblock::processAutoUnblockRequest(), which returns false, never shown.
		}

		return true;
	}

	protected function getUnblockMethodName() :string {
		return __( 'Visitor Auto-Unblock', 'wp-simple-firewall' );
	}
}
