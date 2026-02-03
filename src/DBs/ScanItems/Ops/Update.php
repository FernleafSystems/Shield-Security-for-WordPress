<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops;

use FernleafSystems\Wordpress\Services\Services;

class Update extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Update {

	public function setFinished( Record $record ) :bool {
		return $this->updateRecord( $record, [ 'finished_at' => Services::Request()->ts() ] );
	}

	public function setStarted( Record $record ) :bool {
		return $this->updateRecord( $record, [ 'started_at' => Services::Request()->ts() ] );
	}
}