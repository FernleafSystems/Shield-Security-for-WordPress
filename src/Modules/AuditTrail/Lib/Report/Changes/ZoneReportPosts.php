<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes;

class ZoneReportPosts extends BaseZoneReportPosts {

	public function getZoneName() :string {
		return __( 'Posts', 'wp-simple-firewall' );
	}

	protected function loadLogsFilterPostType() :string {
		return 'post';
	}
}