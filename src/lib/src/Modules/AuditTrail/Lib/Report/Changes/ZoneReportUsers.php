<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

class ZoneReportUsers extends BaseZoneReportUsers {

	public function getZoneName() :string {
		return __( 'Users' );
	}

	protected function loadLogs() :array {
		$logs = parent::loadLogs();
		return \array_filter(
			$logs,
			function ( $log ) {
				return !$this->isUserAdmin( $log->meta_data[ 'user_login' ] );
			}
		);
	}
}