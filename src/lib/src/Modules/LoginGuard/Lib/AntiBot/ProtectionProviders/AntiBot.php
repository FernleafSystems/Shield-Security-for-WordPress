<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

class AntiBot extends BaseProtectionProvider {

	public function performCheck( $formProvider ) {
		if ( $this->isFactorTested() ) {
			return;
		}
		$isBot = $this->con()
					  ->getModule_IPs()
					  ->getBotSignalsController()
					  ->isBot( $this->con()->this_req->ip );
		if ( $isBot ) {
			$this->processFailure();
			throw new \Exception( __( 'Failed AntiBot Test', 'wp-simple-firewall' ) );
		}
	}

	public function buildFormInsert( $formProvider ) :string {
		return '';
	}
}