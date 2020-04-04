<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class ImportIpsFromFile {

	use Shield\Modules\ModConsumer;

	public function run() {
		foreach ( [ 'black', 'white' ] as $sType ) {
			$this->runFileImport( $sType );
		}
	}

	/**
	 * @param string $sType
	 */
	private function runFileImport( $sType ) {
		$oFS = Services::WpFs();

		$sImportFile = $oFS->findFileInDir( 'ip_import_'.$sType, $this->getCon()->getPath_Flags() );
		if ( $oFS->isFile( $sImportFile ) ) {
			$sContent = $oFS->getFileContent( $sImportFile );
			if ( !empty( $sContent ) ) {
				$oAdd = ( new IPs\Lib\Ops\AddIp() )->setMod( $this->getMod() );
				foreach ( array_map( 'trim', explode( "\n", $sContent ) ) as $sIP ) {
					$oAdd->setIP( $sIP );
					try {
						$sType == 'white' ? $oAdd->toManualWhitelist( 'file import' )
							: $oAdd->toManualBlacklist( 'file import' );
					}
					catch ( \Exception $oE ) {
					}
				}
			}
			$oFS->deleteFile( $sImportFile );
		}
	}
}