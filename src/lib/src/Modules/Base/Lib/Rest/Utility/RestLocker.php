<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class RestLocker {

	use Modules\Base\Lib\Rest\Route\RestRouteConsumer;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function start() :bool {
		$count = 0;
		$max = 20;
		while ( $this->isLocked() ) {
			if ( $count++ > $max ) {
				throw new \Exception( 'Could not get a lock - there are too many requests processing. Please try again a bit later.', 403 );
			}
			usleep( 50000 );
		}
		return $this->writeLock();
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	private function isLocked() :bool {
		clearstatcache();
		$FS = Services::WpFs();
		$file = $this->getLockFile();
		return !empty( $file ) &&
			   $FS->exists( $file ) && ( Services::Request()->ts() - (int)$FS->getModifiedTime( $file ) < 10 );
	}

	private function writeLock() :bool {
		return (bool)Services::WpFs()->touch( $this->getLockFile() );
	}

	public function end() {
		$FS = Services::WpFs();
		$file = $this->getLockFile();
		if ( $FS->exists( $file ) ) {
			Services::WpFs()->deleteFile( $file );
		}
	}

	/**
	 * @return string|bool
	 */
	private function getLockFile() {
		try {
			$sBase = $this->getRestRoute()->getWorkingDir();
			$file = path_join( $sBase, 'rest_process.lock' );
		}
		catch ( \Exception $e ) {
			$file = false;
		}
		return $file;
	}
}