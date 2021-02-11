<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class ImportIpsFromFile {

	use Shield\Modules\ModConsumer;

	public function run() {
		foreach ( [ 'black', 'white' ] as $type ) {
			$this->runFileImport( $type );
		}
	}

	private function runFileImport( string $type ) {
		$FS = Services::WpFs();

		$fileImport = $FS->findFileInDir( 'ip_import_'.$type, $this->getCon()->paths->forFlag() );
		if ( $FS->isFile( $fileImport ) ) {
			$content = $FS->getFileContent( $fileImport );
			if ( !empty( $content ) ) {
				$oAdd = ( new IPs\Lib\Ops\AddIp() )->setMod( $this->getMod() );
				foreach ( array_map( 'trim', explode( "\n", $content ) ) as $sIP ) {
					$oAdd->setIP( $sIP );
					try {
						$type == 'white' ? $oAdd->toManualWhitelist( 'file import' )
							: $oAdd->toManualBlacklist( 'file import' );
					}
					catch ( \Exception $e ) {
					}
				}
			}
			$FS->deleteFile( $fileImport );
		}
	}
}