<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class UnblockIpByFlag {

	use Shield\Modules\ModConsumer;

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oFS = Services::WpFs();

		$sPathUnblockFlag = $oFS->findFileInDir( 'unblock', $this->getCon()->getPath_Flags() );
		if ( $oFS->isFile( $sPathUnblockFlag ) ) {
			$sContent = $oFS->getFileContent( $sPathUnblockFlag );
			if ( !empty( $sContent ) ) {

				$aLines = array_map( 'trim', explode( "\n", $sContent ) );
				foreach ( $aLines as $sIp ) {
					$bRemoved = ( new IPs\Lib\Ops\DeleteIp() )
						->setDbHandler( $oMod->getDbHandler_IPs() )
						->setIP( $sIp )
						->fromBlacklist();
					if ( $bRemoved ) {
						$this->getCon()->fireEvent( 'ip_unblock_flag', [ 'audit' => [ 'ip' => $sIp ] ] );
					}
				}
			}
			$oFS->deleteFile( $sPathUnblockFlag );
		}
	}
}