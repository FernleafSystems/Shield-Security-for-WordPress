<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Services\Services;

class WpCoreUnrecognisedFile extends BaseScan {

	protected function canScan() :bool {
		return parent::canScan() &&
			   ( \strpos( $this->pathFull, path_join( ABSPATH, WPINC ) ) === 0
				 || \strpos( $this->pathFull, path_join( ABSPATH, 'wp-admin' ) ) === 0 );
	}

	/**
	 * @throws Exceptions\WpCoreFileUnrecognisedException
	 */
	protected function runScan() :bool {
		if ( !Services::CoreFileHashes()->isCoreFile( $this->pathFull ) ) {
			throw new Exceptions\WpCoreFileUnrecognisedException( $this->pathFull );
		}
		return false;
	}
}