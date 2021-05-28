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
			$content = $FS->getFileContent( $path );
			if ( !empty( $content ) ) {

				foreach ( array_map( 'trim', explode( "\n", $content ) ) as $sIp ) {
					$removed = ( new IPs\Lib\Ops\DeleteIp() )
						->setMod( $mod )
						->setIP( $sIp )
						->fromBlacklist();
					if ( $removed ) {
						$this->getCon()->fireEvent( 'ip_unblock_flag', [ 'audit' => [ 'ip' => $sIp ] ] );
					}
				}
			}
			$FS->deleteFile( $path );
		}
	}
}