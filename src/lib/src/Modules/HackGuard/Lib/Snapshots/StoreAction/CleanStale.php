<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

class CleanStale extends Base {

	public function run() {
		try {
			if ( !$this->isTempDirAvailable() ) {
				throw new \Exception( 'temporary directory is unavailable' );
			}

			$boundary = Services::Request()
								->carbon()
								->subDay()->timestamp;
			$IT = StandardDirectoryIterator::create( $this->getTempDir() );
			foreach ( $IT as $file ) {
				/** @var \SplFileInfo $file */
				if ( $boundary > $file->getMTime() ) {
					Services::WpFs()->deleteFile( $file->getPathname() );
				}
			}
		}
		catch ( \Exception $e ) {
		}
	}
}