<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class NotifyWhitelist {

	use ModConsumer;

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$oHttpReq = Services::HttpRequest();

		if ( $oMod->hasImportExportWhitelistSites() ) {

			$aQuery = [
				'blocking' => false,
				'body'     => [ 'shield_action' => 'importexport_updatenotified' ]
			];
			foreach ( $oMod->getImportExportWhitelist() as $sUrl ) {
				$oHttpReq->get( $sUrl, $aQuery );
			}

			$this->getCon()->fireEvent( 'import_notify_sent' );
		}
	}
}
