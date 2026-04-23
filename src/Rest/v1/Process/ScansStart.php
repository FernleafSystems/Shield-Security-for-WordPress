<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;

class ScansStart extends ScanBase {

	public const SUBCODE_ALREADY_RUNNING = 1;
	public const SUBCODE_NO_SELECTION = 2;
	public const SUBCODE_START_BLOCKED = 3;
	public const SUBCODE_START_FAILED = 4;

	protected function process() :array {
		if ( $this->getScansStatus()[ 'enqueued_count' ] > 0 ) {
			throw new ApiException( 'Scans are already running.', 409, self::SUBCODE_ALREADY_RUNNING );
		}
		$blocked = self::con()->comps->scans->getStartBlockedMessage();
		if ( $blocked !== '' ) {
			throw new ApiException( $blocked, 503, self::SUBCODE_START_BLOCKED );
		}

		$result = self::con()->comps->scans->startNewScans( (array)$this->getWpRestRequest()->get_param( 'scan_slugs' ) );
		if ( !$result->hasRequestedScans() ) {
			throw new ApiException( $result->getMessage(), 400, self::SUBCODE_NO_SELECTION );
		}
		if ( !$result->hasStarted() ) {
			throw new ApiException( $result->getMessage(), 409, self::SUBCODE_START_FAILED );
		}

		$status = $this->getScansStatus();
		if ( $result->isPartialSuccess() ) {
			$status[ 'message' ] = $result->getMessage();
		}
		return $status;
	}
}
