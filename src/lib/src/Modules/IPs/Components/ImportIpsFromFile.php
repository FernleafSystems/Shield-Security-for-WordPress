<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class ImportIpsFromFile extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	protected function canRun() :bool {
		return $this->con()->isPremiumActive();
	}

	protected function run() {
		foreach ( [ 'black', 'white', 'block', 'bypass' ] as $type ) {
			$this->runFileImport( $type );
		}
	}

	private function runFileImport( string $type ) {
		$FS = Services::WpFs();

		$fileImport = $FS->findFileInDir( 'ip_import_'.$type, $this->con()->paths->forFlag() );
		if ( $FS->isFile( $fileImport ) ) {
			$content = $FS->getFileContent( $fileImport );
			if ( !empty( $content ) ) {
				$adder = new IPs\Lib\IpRules\AddRule();
				foreach ( array_map( 'trim', explode( "\n", $content ) ) as $ip ) {
					$adder->setIP( $ip );
					try {
						in_array( $type, [ 'white', 'bypass' ] ) ?
							$adder->toManualWhitelist( 'file import' )
							: $adder->toManualBlacklist( 'file import' );
					}
					catch ( \Exception $e ) {
					}
				}
			}
			$FS->deleteFile( $fileImport );
		}
	}
}