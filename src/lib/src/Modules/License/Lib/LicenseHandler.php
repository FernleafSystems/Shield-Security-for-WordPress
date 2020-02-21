<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless\Lookup;

class LicenseHandler {

	use ModConsumer;

	/**
	 * @return \FernleafSystems\Wordpress\Services\Utilities\Licenses\EddLicenseVO
	 */
	public function lookup() {
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();
		$oCon = $this->getCon();
		$oOpts = $this->getOptions();

		$sPass = wp_generate_password( 16, false );
		$sUrl = Services::WpGeneral()->getHomeUrl( '', true );

		$oMod->setKeylessHandshakeNonce( sha1( $sPass.$sUrl ) );

		{
			$oLook = new Lookup();
			$oLook->lookup_url_stub = $oOpts->getDef( 'license_store_url_api' );
			$oLook->item_id = $oOpts->getDef( 'license_item_id' );
			$oLook->install_id = $oCon->getSiteInstallationId();
			$oLook->check_url = $sUrl;
			$oLook->nonce = $sPass;
			$oLook->meta = [
				'version_shield' => $oCon->getVersion(),
				'version_php'    => Services::Data()->getPhpVersionCleaned()
			];
			$oLicense = $oLook->lookup();
		}

		// clear the handshake data after the request has gone through
		$oMod->setKeylessHandshakeNonce( '' );

		return $oLicense;
	}

}
