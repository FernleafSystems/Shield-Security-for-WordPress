<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;

class WpCoreUnrecognisedFile extends BaseScan {

	/**
	 * @throws Exceptions\WpCoreFileUnrecognisedException
	 */
	public function scan() :bool {
		$valid = false;
		// TODO WP Content?
		if ( strpos( $this->pathFull, path_join( ABSPATH, WPINC ) ) === 0
			 || strpos( $this->pathFull, path_join( ABSPATH, 'wp-admin' ) ) === 0 ) {
			if ( !Services::CoreFileHashes()->isCoreFile( $this->pathFull ) ) {
				throw new Exceptions\WpCoreFileUnrecognisedException( $this->pathFull );
			}
			$valid = true;
		}
		return $valid;
	}
}