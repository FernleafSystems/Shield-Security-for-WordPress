<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

class BuildForChanges extends BuildBase {

	public function build( array $zones = [] ) :array {
		$data = [];
		foreach ( $this->con()->getModule_AuditTrail()->getAuditCon()->getAuditors() as $auditor ) {
			if ( empty( $zones ) || \in_array( $auditor::Slug(), $zones ) ) {
				try {
					$reporter = $auditor->getReporter();
					$reporter->setFrom( $this->start );
					$reporter->setUntil( $this->end );
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