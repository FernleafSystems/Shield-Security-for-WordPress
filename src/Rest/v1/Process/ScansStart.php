<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;

class ScansStart extends ScanBase {

	protected function process() :array {
		if ( $this->getScansStatus()[ 'enqueued_count' ] > 0 ) {
			throw new ApiException( 'Scans are already running.' );
		}
		self::con()->comps->scans->startNewScans( $this->getWpRestRequest()->get_param( 'scan_slugs' ) );
		return $this->getScansStatus();
	}
}