<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless\Lookup;

class LookupRequest {

	use ModConsumer;

	/**
	 * @return \FernleafSystems\Wordpress\Services\Utilities\Licenses\EddLicenseVO
	 */
	public function lookup() {
		$oCon = $this->getCon();
		$oOpts = $this->getOptions();

		{
			$oLook = new Lookup();
			$oLook->lookup_url_stub = $oOpts->getDef( 'license_store_url_api' );
			$oLook->item_id = $oOpts->getDef( 'license_item_id' );
			$oLook->install_id = $oCon->getSiteInstallationId();
			$oLook->url = Services::WpGeneral()->getHomeUrl( '', true );
			$oLook->nonce = ( new HandshakingNonce() )->setMod( $this->getMod() )->create();
			$oLook->meta = [
				'version_shield' => $oCon->getVersion(),
				'version_php'    => Services::Data()->getPhpVersionCleaned()
			];
			$oLicense = $oLook->lookup();
		}

		return $oLicense;
	}

	/**
	 * @param string $sNonce - empty string to clear the nonce
	 */
	private function setKeylessHandshakeNonce( $sNonce = '' ) {
		$oOpts = $this->getOptions();
		$oOpts->setOpt( 'keyless_handshake_hash', $sNonce )
			  ->setOpt( 'keyless_handshake_until',
				  empty( $sNonce ) ? 0 : Services::Request()->ts() + $oOpts->getDef( 'keyless_handshake_expire' )
			  );
		$this->getMod()->saveModOptions();
	}
}
