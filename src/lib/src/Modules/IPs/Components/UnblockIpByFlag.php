<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class UnblockIpByFlag {

	use Shield\Modules\ModConsumer;

	public function run() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$FS = Services::WpFs();

		$path = $FS->findFileInDir( 'unblock', $this->getCon()->paths->forFlag() );
		if ( !empty( $path ) && $FS->isFile( $path ) ) {
			$sContent = $FS->getFileContent( $path );
			if ( !empty( $sContent ) ) {

				$aLines = array_map( 'trim', explode( "\n", $sContent ) );
				foreach ( $aLines as $sIp ) {
					$bRemoved = ( new IPs\Lib\Ops\DeleteIp() )
						->setDbHandler( $mod->getDbHandler_IPs() )
						->setIP( $sIp )
						->fromBlacklist();
					if ( $bRemoved ) {
						$this->getCon()->fireEvent( 'ip_unblock_flag', [ 'audit' => [ 'ip' => $sIp ] ] );
					}
				}
			}
			$FS->deleteFile( $path );
		}
	}
}