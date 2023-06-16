<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report;

class ZoneReportDB extends BaseZoneReport {

	public function getZoneName() :string {
		return __( 'Database' );
	}
}