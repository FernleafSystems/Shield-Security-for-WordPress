<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops;

use FernleafSystems\Wordpress\Services\Services;

class Update extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Update {

	public function markNotified( Record $entry ) :bool {
		return $this->updateRecord( $entry, [
			'notified_at' => Services::Request()->ts()
		] );
	}

	public function markProblem( Record $entry ) :bool {
		return $this->updateRecord( $entry, [
			'detected_at' => Services::Request()->ts(),
			'notified_at' => 0
		] );
	}

	public function markReverted( Record $entry ) :bool {
		return $this->updateRecord( $entry, [
			'reverted_at' => Services::Request()->ts()
		] );
	}

	public function updateCurrentHash( Record $entry, string $hash = '' ) :bool {
		return $this->updateRecord( $entry, [
			'hash_current' => $hash,
			'detected_at'  => empty( $hash ) ? 0 : Services::Request()->ts(),
			'notified_at'  => 0,
		] );
	}
}