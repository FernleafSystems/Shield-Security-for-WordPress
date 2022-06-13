<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Signals;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecSignals\Ops as CrowdsecSignalsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api\PushSignals;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\PushSignalsFailedException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class PushSignalsToCS extends ExecOnceModConsumer {

	const LIMIT = 100;

	private $distinctIPs;

	private $distinctScopes;

	private $groupedBy;

	public function __construct( string $groupedBy = '' ) {
		$this->groupedBy = $groupedBy;
	}

	protected function canRun() :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getCrowdSecCon()->getApi()->isReady();
	}

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$authToken = $mod->getCrowdSecCon()->getApi()->getAuthorizationToken();
		$count = 0;
		$recordsCount = 0;
		do {
			if ( $count++ > 2 ) {
				break;
			}
			$records = $this->getNextRecordSet();
			if ( !empty( $records ) ) {
				try {
					( new PushSignals( $authToken ) )->run( $this->convertRecordsToPayload( $records ) );
				}
				catch ( PushSignalsFailedException $e ) {
				}
				$this->deleteRecords( $records );

				$recordsCount += count( $records );
			}
		} while ( !empty( $records ) );

		if ( !empty( $recordsCount ) ) {
			$this->getCon()->fireEvent( 'crowdsec_signals_pushed', [
				'audit_params' => [
					'count' => $recordsCount
				]
			] );
		}
	}

	/**
	 * @param CrowdsecSignalsDB\Record[] $records
	 * @return CrowdsecSignalsDB\Record[]
	 */
	private function convertRecordsToPayload( array $records ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$api = $mod->getCrowdSecCon()->getApi();
		return array_map(
			function ( $record ) use ( $api ) {
				$carbon = Services::Request()->carbon();
				$carbon->setTimestamp( $record->created_at );
				$carbon->setTimezone( 'UTC' );
				$ts = str_replace( '+00:00', '.000Z', trim( $carbon->toRfc3339String(), 'Z' ) );
				return [
					'machine_id'       => $api->getMachineID(),
					'scenario'         => 'shield/'.$record->scenario,
					'message'          => 'Shield reporting scenario '.$record->scenario,
					'scenario_hash'    => '',
					'scenario_version' => '0.1',
					'source'           => [
						'id'    => $record->id,
						'scope' => $record->scope,
						'value' => $record->value,
						'ip'    => $record->value,
					],
					'start_at'         => $ts,
					'stop_at'          => $ts
				];
			},
			$records
		);
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
				if ( $count++ > 10 ) {
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