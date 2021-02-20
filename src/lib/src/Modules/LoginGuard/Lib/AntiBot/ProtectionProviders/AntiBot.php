<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

class AntiBot extends BaseProtectionProvider {

	/**
	 * @inheritDoc
	 */
	public function performCheck( $oForm ) {
		if ( $this->isFactorTested() ) {
			return;
		}
		$valid = $this->getCon()
					  ->getModule_IPs()
					  ->getBotSignalsController()
					  ->getHandlerNotBot()
					  ->verify();
		if ( !$valid ) {
			$this->processFailure();
			throw new \Exception( __( 'Failed AntiBot Test', 'wp-simple-firewall' ) );
		}
	}

	public function buildFormInsert( $oFormProvider ) {
		return '';
	}
}