<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;

class ScansStart extends ScanBase {

	protected function process() :array {
		if ( $this->getScansStatus()[ 'enqueued_count' ] > 0 ) {
			throw new ApiException( 'Scans are already running.' );
		}
		$blocked = self::con()->comps->scans->getStartBlockedMessage();
		if ( $blocked !== '' ) {
			throw new ApiException( $blocked );
		}
		if ( !self::con()->comps->scans->startNewScans( $this->getWpRestRequest()->get_param( 'scan_slugs' ) ) ) {
			throw new ApiException( 'No scans were selected.' );
		}
		return $this->getScansStatus();
	}
}
