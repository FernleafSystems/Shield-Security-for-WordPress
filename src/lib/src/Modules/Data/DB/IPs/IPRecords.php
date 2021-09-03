<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class IPRecords {

	use ModConsumer;

	public function loadIP( string $ip, bool $autoCreate = true ) :Ops\Record {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_IPs();
		/** @var Ops\Select $select */
		$select = $dbh->getQuerySelector();
		$record = $select->filterByIPHuman( $ip )->first();

		if ( empty( $record ) && $autoCreate && $this->addIP( $ip ) ) {
			$record = $this->loadIP( $ip, false );
		}

		if ( empty( $record ) ) {
			throw new \Exception( 'IP Record unavailable' );
		}

		return $record;
	}

	public function addIP( string $ip ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_IPs();
		/** @var Ops\Insert $insert */
		$insert = $dbh->getQueryInserter();
		$record = new Ops\Record();
		$record->ip = $ip;
		return $insert->insert( $record );
	}
}