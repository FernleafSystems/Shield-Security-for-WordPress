<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request\RequestVO;

class Start extends Base {

	protected function process() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var RequestVO $req */
		$req = $this->getRequestVO();

		if ( $this->getScansStatus()[ 'enqueued_count' ] > 0 ) {
			throw new \Exception( 'Scans are already running.' );
		}
		$mod->getScansCon()->startNewScans( $req->scan_slugs );

		return $this->getScansStatus();
	}
}