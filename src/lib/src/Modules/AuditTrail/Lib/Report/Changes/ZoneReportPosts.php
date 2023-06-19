<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

class ZoneReportPosts extends BaseZoneReportPosts {

	public function getZoneName() :string {
		return __( 'Posts' );
	}

	protected function loadLogsFilterPostType() :string {
		return 'post';
	}
}