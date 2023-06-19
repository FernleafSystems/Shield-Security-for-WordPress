<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

class ZoneReportAdmins extends BaseZoneReportUsers {

	public function getZoneName() :string {
		return __( 'Admins' );
	}

	protected function loadLogs() :array {
		$logs = parent::loadLogs();
		return \array_filter(
			$logs,
			function ( $log ) {
				return $this->isUserAdmin( $log->meta_data[ 'user_login' ] );
			}
		);
	}
}