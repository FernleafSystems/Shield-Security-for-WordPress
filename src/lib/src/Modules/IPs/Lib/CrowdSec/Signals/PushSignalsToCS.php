<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Signals;

use AptowebDeps\CrowdSec\CapiClient\ClientException;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\CrowdSecSignals\Ops as CrowdsecSignalsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * https://crowdsecurity.github.io/api_doc/capi/#/watchers/post_signals
 */
class PushSignalsToCS {

	use ExecOnce;
	use PluginControllerConsumer;

	public const LIMIT = 100;

	private $distinctIPs;

	private $distinctScopes;

	private $groupedBy;

	public function __construct( string $groupedBy = '' ) {
		$this->groupedBy = $groupedBy;
	}

	protected function canRun() :bool {
		return self::con()->is_mode_live && self::con()->comps->crowdsec->getApi()->isReady();
	}

	protected function run() {
		$watcher = self::con()->comps->crowdsec->getWatcher();

		$pushCount = 0;
		do {
			$records = $this->getNextRecordSet();
			$this->deleteRecords( $records );

			$toPush = \array_map(
				fn( CrowdsecSignalsDB\Record $record ) => $this->convertRecordsToSignal( $record ),
				\array_filter(
					$records,
					fn( CrowdsecSignalsDB\Record $record ) => $this->shouldRecordBeSent( $record )
				)
			);

			if ( !empty( $toPush ) ) {
				try {
					$watcher->pushSignals( $toPush );
					$pushCount += \count( $toPush );
				}
				catch ( ClientException $e ) {
				}
			}
		} while ( !empty( $records ) );

		if ( $pushCount > 0 ) {
			self::con()->comps->events->fireEvent( 'crowdsec_signals_pushed', [
				'audit_params' => [
					'count' => $pushCount
				]
			] );
		}
	}

	private function convertRecordsToSignal( CrowdsecSignalsDB\Record $record ) :array {
		$carbon = Services::Request()->carbon();
		$carbon->setTimezone( 'UTC' );
		$carbon->setTimestamp( $record->created_at );
		$ts = \str_replace( '+00:00', sprintf( '.%sZ', $record->milli_at === 0 ? '000' : $record->milli_at ),
			\trim( $carbon->toRfc3339String(), 'Z' ) );

		return self::con()->comps->crowdsec->getWatcher()->buildSignal(
			[
				'scenario'         => 'shield/'.$record->scenario,
				'scenario_version' => '0.1',
				'message'          => 'Shield reporting scenario '.$record->scenario,
				'created_at'       => $ts,
				'start_at'         => $ts,
				'stop_at'          => $ts,
				/** doesn't appear to be used in CAPI wrapper */
				'context'          => $this->buildContext( $record ),
			],
			[
				'id'    => $record->id,
				'scope' => $record->scope,
				'value' => $record->value,
			]
		);
	}

	private function buildContext( CrowdsecSignalsDB\Record $record ) :array {
		$context = [];
		foreach ( $record->meta[ 'context' ] ?? [] as $key => $value ) {
			$context[] = [
				'key'   => $key,
				'value' => \strval( $value ),
			];
		}
		return $context;
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

	private function shouldRecordBeSent( CrowdsecSignalsDB\Record $record ) :bool {
		if ( $record->scenario === 'btxml' ) {
			$send = self::con()->comps->opts_lookup->getBotTrackOffenseCountFor( 'track_xmlrpc' ) > 0;
		}
		else {
			$send = true;
		}
		return $send;
	}

	/**
	 * @return CrowdsecSignalsDB\Record[]
	 */
	private function getRecordsByScope() :array {
		$dbhSignals = self::con()->db_con->crowdsec_signals;

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
		$dbhSignals = self::con()->db_con->crowdsec_signals;

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
		if ( !empty( $records ) ) {
			self::con()
				->db_con
				->crowdsec_signals
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
}