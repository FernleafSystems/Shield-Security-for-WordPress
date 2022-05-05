<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions\RealtimeFileDiscoveredException;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Services\Services;

class RealtimeFile extends BaseScan {

	/**
	 * @throws RealtimeFileDiscoveredException
	 */
	public function scan() :bool {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$FS = Services::WpFs();

		$mtime = $FS->isFile( $this->pathFull ) ? $FS->getModifiedTime( $this->pathFull ) : 0;
		if ( $mtime > $action->realtime_scan_last_at ) {
			throw new RealtimeFileDiscoveredException( $this->pathFull, [ 'mtime' => $mtime ] );
		}
		return true;
	}
}