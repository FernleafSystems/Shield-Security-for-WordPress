<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\HashesStorageDir;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

class CleanStale extends BaseExec {

	protected function run() {
		try {
			$boundary = Services::Request()
								->carbon()
								->subDay()->timestamp;
			foreach ( StandardDirectoryIterator::create( ( new HashesStorageDir() )->getTempDir() ) as $file ) {
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