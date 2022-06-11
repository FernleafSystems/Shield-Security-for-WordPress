<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Signals;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecSignals\Ops as CrowdsecSignalsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\FailedToPushSignalsException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class PushSignalsToCS {

	const LIMIT = 100;
	use ModConsumer;

	private $distinctIPs;

	private $distinctScopes;

	private $groupedBy;

	public function push( string $groupedBy = '' ) {
		$this->groupedBy = $groupedBy;
		$this->run();
	}

	protected function run() {
		$count = 0;
		do {
			if ( $count++ > 50 ) {
				break;
			}
			$records = $this->getNextRecordSet();
			if ( !empty( $records ) ) {
				$payload = [];
				try {
//					( new PushSignals( $mod->getCrowdSecCon()->getApi()->getAuthorizationToken() ) )->run( $payload );
				}
				catch ( FailedToPushSignalsException $e ) {
				}
				$this->deleteRecords( $records );
			}
		} while ( !empty( $records ) );
	}

	/**
	 * @return CrowdsecSignalsDB\Record[]
	 */
	private function getNextRecordSet() :array {
		switch ( $this->groupedBy ) {
			case 'ip':
				$records = $this->getRecordsGroupedByIP();
				break;
			default:
				$records = $this->getRecordsByScope();
				break;
		}
		return $records;
	}

	/**
	 * @return CrowdsecSignalsDB\Record[]
	 */
	private function getRecordsByScope() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbhSignals = $mod->getDbH_CrowdSecSignals();

		if ( !isset( $this->distinctScopes ) ) {
			$scopes = $dbhSignals->getQuerySelector()->getDistinctForColumn( 'scope' );
			$this->distinctScopes = is_array( $scopes ) ? array_filter( $scopes ) : [];
		}

		$records = [];
		if ( !empty( $this->distinctScopes ) ) {
			$count = 0;
			do {
				if ( $count++ > 50 ) {
					break;
				}
				$scope = array_shift( $this->distinctScopes );
				/** @var CrowdsecSignalsDB\Select $selector */
				$selector = $dbhSignals->getQuerySelector();
				$records = $selector->filterByScope( $scope )
									->setLimit( self::LIMIT )
									->queryWithResult();
				if ( !empty( $records ) ) {
					array_unshift( $this->distinctScopes, $scope );
					break;
				}
			} while ( !empty( $this->distinctScopes ) );
		}

		return is_array( $records ) ? $records : [];
	}

	/**
	 * @return CrowdsecSignalsDB\Record[]
	 */
	private function getRecordsGroupedByIP() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbhSignals = $mod->getDbH_CrowdSecSignals();

		if ( !isset( $this->distinctIPs ) ) {
			$ips = $dbhSignals->getQuerySelector()
							  ->addWhereEquals( 'scope', 'ip' )
							  ->addColumnToSelect( 'value' )
							  ->setIsDistinct( true )
							  ->queryWithResult();
			$this->distinctIPs = is_array( $ips ) ? array_filter( $ips ) : [];
		}

		/** @var CrowdsecSignalsDB\Record[] $records */
		$records = [];
		if ( !empty( $this->distinctIPs ) ) {
			$ip = array_shift( $this->distinctIPs );
			/** @var CrowdsecSignalsDB\Select $selector */
			$selector = $dbhSignals->getQuerySelector();
			$records = $selector->filterByScope( 'ip' )
								->filterByValue( $ip )
								->queryWithResult();
		}

		return is_array( $records ) ? $records : [];
	}

	private function deleteRecords( array $records ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getDbH_CrowdSecSignals()
			->getQueryDeleter()
			->filterByIDs( array_map(
				function ( $record ) {
					return $record->id;
				},
				$records
			) )
			->query();
	}
}