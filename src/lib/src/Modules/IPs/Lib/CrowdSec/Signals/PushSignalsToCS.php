<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Signals;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	Lib\CrowdSec\Api\PushSignals,
	ModConsumer
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecSignals\Ops as CrowdsecSignalsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\PushSignalsFailedException;
use FernleafSystems\Wordpress\Services\Services;

class PushSignalsToCS {

	use ExecOnce;
	use ModConsumer;

	public const LIMIT = 100;

	private $distinctIPs;

	private $distinctScopes;

	private $groupedBy;

	public function __construct( string $groupedBy = '' ) {
		$this->groupedBy = $groupedBy;
	}

	protected function canRun() :bool {
		$mod = $this->mod();
		return self::con()->is_mode_live && $mod->getCrowdSecCon()->getApi()->isReady();
	}

	protected function run() {
		$api = $this->mod()->getCrowdSecCon()->getApi();

		$recordsCount = 0;
		do {
			$records = $this->getNextRecordSet();
			if ( !empty( $records ) ) {
				try {
					( new PushSignals( $api->getAuthorizationToken(), $api->getApiUserAgent() ) )
						->run( $this->convertRecordsToPayload( $records ) );
				}
				catch ( PushSignalsFailedException $e ) {
				}
				$this->deleteRecords( $records );

				$recordsCount += \count( $records );
			}
		} while ( !empty( $records ) );

		if ( !empty( $recordsCount ) ) {
			self::con()->fireEvent( 'crowdsec_signals_pushed', [
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
		$api = $this->mod()->getCrowdSecCon()->getApi();
		return \array_map(
			function ( CrowdsecSignalsDB\Record $record ) use ( $api ) {
				$carbon = Services::Request()->carbon();
				$carbon->setTimestamp( $record->created_at );
				$carbon->setTimezone( 'UTC' );
				$ts = \str_replace( '+00:00', sprintf( '.%sZ', $record->milli_at === 0 ? '000' : $record->milli_at ),
					\trim( $carbon->toRfc3339String(), 'Z' ) );
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
			\array_filter( $records, function ( CrowdsecSignalsDB\Record $record ) {
				return $this->shouldRecordBeSent( $record );
			} )
		);
	}

	private function shouldRecordBeSent( CrowdsecSignalsDB\Record $record ) :bool {
		$send = true;
		if ( $record->scenario === 'btxml' ) {
			$send = $this->opts()->isTrackOptImmediateBlock( 'track_xmlrpc' )
					|| $this->opts()->getOffenseCountFor( 'track_xmlrpc' ) > 0;
		}
		return $send;
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
		$dbhSignals = $this->mod()->getDbH_CrowdSecSignals();

		if ( !isset( $this->distinctScopes ) ) {
			$scopes = $dbhSignals->getQuerySelector()->getDistinctForColumn( 'scope' );
			$this->distinctScopes = \is_array( $scopes ) ? \array_filter( $scopes ) : [];
		}

		$records = [];
		if ( !empty( $this->distinctScopes ) ) {
			$count = 0;
			do {
				if ( $count++ > 10 ) {
					break;
				}
				$scope = \array_shift( $this->distinctScopes );
				/** @var CrowdsecSignalsDB\Select $selector */
				$selector = $dbhSignals->getQuerySelector();
				$records = $selector->filterByScope( $scope )
									->setLimit( self::LIMIT )
									->queryWithResult();
				if ( !empty( $records ) ) {
					\array_unshift( $this->distinctScopes, $scope );
					break;
				}
			} while ( !empty( $this->distinctScopes ) );
		}

		return \is_array( $records ) ? $records : [];
	}

	/**
	 * @return CrowdsecSignalsDB\Record[]
	 */
	private function getRecordsGroupedByIP() :array {
		$dbhSignals = $this->mod()->getDbH_CrowdSecSignals();

		if ( !isset( $this->distinctIPs ) ) {
			$ips = $dbhSignals->getQuerySelector()
							  ->addWhereEquals( 'scope', 'ip' )
							  ->addColumnToSelect( 'value' )
							  ->setIsDistinct( true )
							  ->queryWithResult();
			$this->distinctIPs = \is_array( $ips ) ? \array_filter( $ips ) : [];
		}

		/** @var CrowdsecSignalsDB\Record[] $records */
		$records = [];
		if ( !empty( $this->distinctIPs ) ) {
			$ip = \array_shift( $this->distinctIPs );
			/** @var CrowdsecSignalsDB\Select $selector */
			$selector = $dbhSignals->getQuerySelector();
			$records = $selector->filterByScope( 'ip' )
								->filterByValue( $ip )
								->queryWithResult();
		}

		return \is_array( $records ) ? $records : [];
	}

	private function deleteRecords( array $records ) {
		$this->mod()
			 ->getDbH_CrowdSecSignals()
			 ->getQueryDeleter()
			 ->filterByIDs( \array_map(
				 function ( $record ) {
					 return $record->id;
				 },
				 $records
			 ) )
			 ->query();
	}
}