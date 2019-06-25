<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class UnblockIpByFlag {

	use Shield\Modules\ModConsumer;

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oCon = $this->getCon();
		$oFS = Services::WpFs();

		$sPathUnblockFlag = $oFS->findFileInDir( 'unblock', $oCon->getPath_Flags() );
		if ( $oFS->exists( $sPathUnblockFlag ) ) {
			$sContent = $oFS->getFileContent( $sPathUnblockFlag );
			if ( !empty( $sContent ) ) {

				$aLines = array_map( 'trim', explode( "\n", $sContent ) );
				/** @var Shield\Databases\IPs\Handler $oDbH */
				$oDbH = $oMod->getDbHandler();
				$oIP = Services::IP();

				/** @var Shield\Databases\IPs\Delete $oDel */
				$oDel = $oDbH->getQueryDeleter();
				/** @var Shield\Databases\IPs\Select $oSel */
				$oSel = $oDbH->getQuerySelector();
				foreach ( $aLines as $sIp ) {
					if ( !empty( $sIp ) && $oIP->isViablePublicVisitorIp( $sIp ) && $oSel->getIpOnBlackLists( $sIp ) ) {
						$oDel->deleteIpFromBlacklists( $sIp );
						$oCon->fireEvent( 'ip_unblock_flag', [ 'audit' => [ 'ip' => $sIp ] ] );
					}
				}
			}
			$oFS->deleteFile( $sPathUnblockFlag );
		}
	}
}