<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

class PaidMembershipsPro extends Base {

	protected function checkout() {
		foreach ( [ 'pmpro_checkout_checks', 'pmpro_billing_update_checks' ] as $filter ) {
			\add_filter( $filter, fn( $checksPassed = null ) => $this->checkCheckout( $checksPassed ) );
		}
	}

	protected function checkCheckout( $checksPassed = null ) {
		if ( \is_bool( $checksPassed ) && $checksPassed && $this->setAuditAction( 'checkout' )->isBotBlockRequired() ) {
			$checksPassed = false;
			$this->fireEventBlockCheckout();
		}
		return $checksPassed;
	}

	public static function IsProviderInstalled() :bool {
		return \defined( 'PMPRO_DIR' ) && \defined( 'PMPRO_VERSION' ) && \version_compare( PMPRO_VERSION, '3.5' );
	}

	protected static function ProviderMeetsRequirements() :bool {
		return \defined( 'PMPRO_VERSION' ) && \version_compare( PMPRO_VERSION, '3.5' );
	}
}