<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;

class WpCoreFile extends BaseScan {

	/**
	 * @throws Exceptions\WpCoreFileChecksumFailException
	 * @throws Exceptions\WpCoreFileMissingException
	 */
	public function scan() :bool {
		$valid = false;

		$WPH = Services::CoreFileHashes();
		if ( $WPH->isCoreFile( $this->pathFull ) ) {
			if ( !Services::WpFs()->isFile( $this->pathFull ) ) {
				throw new Exceptions\WpCoreFileMissingException( $this->pathFull );
			}
			if ( !$WPH->isCoreFileHashValid( $this->pathFull ) ) {
				throw new Exceptions\WpCoreFileChecksumFailException( $this->pathFull );
			}
			$valid = true;
		}

		return $valid;
	}
}