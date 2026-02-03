<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops;

trait Common {

	public function filterByScan( string $scan ) {
		return $this->addWhereEquals( 'scan', $scan );
	}

	public function filterByScans( array $scans ) {
		return $this->addWhereIn( 'scan', $scans );
	}

	public function filterByNotFinished() {
		return $this->addWhereEquals( 'finished_at', 0 );
	}

	public function filterByNotReady() {
		return $this->addWhereEquals( 'ready_at', 0 );
	}

	public function filterByFinished() {
		return $this->addWhereNewerThan( 0, 'finished_at' );
	}

	public function filterByReady() {
		return $this->addWhereNewerThan( 0, 'ready_at' );
	}
}