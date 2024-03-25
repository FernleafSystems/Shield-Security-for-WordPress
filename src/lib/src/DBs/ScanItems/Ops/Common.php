<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops;

trait Common {

	public function filterByScan( int $scanID ) {
		return $this->addWhereEquals( 'scan_ref', $scanID );
	}

	public function filterByFinished() {
		return $this->addWhereNewerThan( 0, 'finished_at' );
	}

	public function filterByNotFinished() {
		return $this->addWhereEquals( 'finished_at', 0 );
	}

	public function filterByStarted() {
		return $this->addWhereNewerThan( 0, 'started_at' );
	}

	public function filterByNotStarted() {
		return $this->addWhereEquals( 'started_at', 0 );
	}
}