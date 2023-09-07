<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModConsumer;
use IPLib\Factory;

class IPRecords {

	use ModConsumer;

	private static $ips = [];

	/**
	 * @throws \Exception
	 */
	public function loadIP( string $ip, bool $autoCreate = true, bool $cacheRecord = true ) :Ops\Record {

		if ( !empty( self::$ips[ $ip ] ) ) {
			$record = self::$ips[ $ip ];
		}
		else {
			$parsedRange = Factory::parseRangeString( $ip );
			if ( empty( $parsedRange ) ) {
				throw new \Exception( 'Not a valid IP range' );
			}
			$ip = \explode( '/', $parsedRange->asSubnet()->toString() )[ 0 ];

			$dbh = self::con()->getModule_Data()->getDbH_IPs();
			/** @var Ops\Select $select */
			$select = $dbh->getQuerySelector();
			$select->filterByIPHuman( $ip );
			if ( \method_exists( $select, 'setNoOrderBy' ) ) {
				$select->setNoOrderBy();
			}
			$record = $select->first();

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
		$dbh = self::con()->getModule_Data()->getDbH_IPs();
		/** @var Ops\Insert $insert */
		$insert = $dbh->getQueryInserter();
		/** @var Ops\Record $record */
		$record = $dbh->getRecord();
		$record->ip = $ip;
		$insert->insert( $record );
	}
}