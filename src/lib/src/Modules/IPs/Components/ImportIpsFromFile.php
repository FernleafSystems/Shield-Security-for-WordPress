<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ImportIpsFromFile {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return self::con()->caps->hasCap( 'ips_import_from_file' );
	}

	protected function run() {
		foreach ( [ 'black', 'white', 'block', 'bypass' ] as $type ) {
			$this->runFileImport( $type );
		}
	}

	private function runFileImport( string $type ) {
		$FS = Services::WpFs();

		$fileImport = $FS->findFileInDir( 'ip_import_'.$type, self::con()->paths->forFlag() );
		if ( !empty( $fileImport ) && $FS->isAccessibleFile( $fileImport ) ) {
			$content = $FS->getFileContent( $fileImport );
			$FS->deleteFile( $fileImport );
			if ( !empty( $content ) ) {
				foreach ( \array_map( '\trim', \explode( "\n", $content ) ) as $ip ) {
					$adder = ( new AddRule() )->setIP( $ip );
					try {
						\in_array( $type, [ 'white', 'bypass' ] ) ?
							$adder->toManualWhitelist( 'file import' )
							: $adder->toManualBlacklist( 'file import' );
					}
					catch ( \Exception $e ) {
					}
				}
			}
		}
	}
}