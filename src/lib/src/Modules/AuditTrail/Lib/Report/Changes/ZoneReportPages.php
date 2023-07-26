<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

class ZoneReportPages extends BaseZoneReportPosts {

	public function getZoneName() :string {
		return __( 'Pages' );
	}

	protected function loadLogsFilterPostType() :string {
		return 'page';
	}
}