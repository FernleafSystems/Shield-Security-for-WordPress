<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;

class BuildForChanges extends BuildBase {

	public function build() :array {
		$data = [];
		$zones = $this->report->areas[ Constants::REPORT_AREA_CHANGES ];
		foreach ( self::con()->comps->activity_log->getAuditors() as $auditor ) {
			if ( empty( $zones ) || \in_array( $auditor::Slug(), $zones ) ) {
				try {
					$reporter = $auditor->getReporter();
					$reporter->setFrom( $this->report->start_at );
					$reporter->setUntil( $this->report->end_at );
					$data[ $reporter::Slug() ] = [
						'title'       => $reporter->getZoneName(),
						'description' => $reporter->getZoneDescription(),
						'detailed'    => $reporter->buildChangeReportData( false ),
						'total'       => $reporter->countChanges(),
					];
				}
				catch ( \Exception $e ) {
				}
			}
		}
		return $data;
	}
}