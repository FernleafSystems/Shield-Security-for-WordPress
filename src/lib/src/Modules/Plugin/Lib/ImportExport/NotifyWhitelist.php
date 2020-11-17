<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class NotifyWhitelist {

	use ModConsumer;

	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$oHttpReq = Services::HttpRequest();

		if ( $mod->hasImportExportWhitelistSites() ) {

			$aQuery = [
				'blocking' => false,
				'body'     => [ 'shield_action' => 'importexport_updatenotified' ]
			];
			foreach ( $mod->getImportExportWhitelist() as $sUrl ) {
				$oHttpReq->get( $sUrl, $aQuery );
			}

			$this->getCon()->fireEvent( 'import_notify_sent' );
		}
	}
}
