<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Services\Services;

class AntiBot extends BaseProtectionProvider {

	/**
	 * @inheritDoc
	 */
	public function performCheck( $form ) {
		if ( $this->isFactorTested() ) {
			return;
		}
		$isBot = $this->getCon()
					  ->getModule_IPs()
					  ->getBotSignalsController()
					  ->isBot( Services::IP()->getRequestIp() );
		if ( $isBot ) {
			$this->processFailure();
			throw new \Exception( __( 'Failed AntiBot Test', 'wp-simple-firewall' ) );
		}
	}

	public function buildFormInsert( $oFormProvider ) {
		return '';
	}
}