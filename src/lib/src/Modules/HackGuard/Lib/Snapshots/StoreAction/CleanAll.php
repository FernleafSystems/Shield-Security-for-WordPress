<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers\StandardDirectoryIterator;
use FernleafSystems\Wordpress\Services\Services;

class CleanAll extends BaseBulk {

	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		try {
			$boundary = Services::Request()
								 ->carbon()
								 ->subDay()->timestamp;
			$IT = StandardDirectoryIterator::create( $mod->getPtgSnapsBaseDir() );
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