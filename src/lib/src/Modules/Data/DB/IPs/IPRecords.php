<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class IPRecords {

	use ModConsumer;

	private static $ips = [];

	public function loadIP( string $ip, bool $autoCreate = true, bool $cacheRecord = true ) :Ops\Record {

		if ( !empty( self::$ips[ $ip ] ) ) {
			$record = self::$ips[ $ip ];
		}
		else {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			$dbh = $mod->getDbH_IPs();
			/** @var Ops\Select $select */
			$select = $dbh->getQuerySelector();
			$record = $select->filterByIPHuman( $ip )->first();

			if ( empty( $record ) && $autoCreate ) {
				$this->addIP( $ip );
				$record = $this->loadIP( $ip, false );
			}

			if ( empty( $record ) ) {
				throw new \Exception( 'IP Record unavailable: '.$ip );
			}

			if ( $cacheRecord ) {
				self::$ips[ $ip ] = $record;
			}
		}

		return $record;
	}

	public function addIP( string $ip ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_IPs();
		/** @var Ops\Insert $insert */
		$insert = $dbh->getQueryInserter();
		/** @var Ops\Record $record */
		$record = $dbh->getRecord();
		$record->ip = $ip;
		$insert->insert( $record );
	}
}