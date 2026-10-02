<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Malware\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Services\Services;

class MalaiEarlyRecheck {

	use PluginControllerConsumer;

	public const DELAY = 600;

	private const PAGE_SIZE = 200;

	public function hook() :string {
		return self::con()->prefix( 'malai_early_recheck' );
	}

	public static function needsFollowUp( Record $record ) :bool {
		return (int)$record->reported_at > 0
			&& (int)$record->last_malai_status_at < (int)$record->reported_at + self::DELAY
			&& ( new MalwareStatus() )->isPending( $record->malai_status );
	}

	public function schedule( bool $includeOverdue = true ) :void {
		$con = self::con();
		if ( !$con->caps->canScanMalwareMalai() ) {
			return;
		}
		$afs = $con->comps->scans->AFS();
		if ( $afs->isRestricted() ) {
			return;
		}

		$now = Services::Request()->ts();
		$earliest = 0;
		$lastSeenID = 0;
		do {
			$retriever = ( new RetrieveItems() )->setScanController( $afs );
			$retriever->limit = self::PAGE_SIZE;
			$retriever->addWheres( [
				sprintf( '`ri`.`id`>%d', $lastSeenID ),
				'`ri`.`ignored_at`=0',
				'`ri`.`auto_filtered_at`=0',
			] );
			$items = $retriever->retrieveLatestForFindings( [ 'is_mal' ] )->getAllItems();
			foreach ( $items as $item ) {
				if ( !$item instanceof ResultItem ) {
					throw new \UnexpectedValueException( 'AFS result set contained an invalid item type.' );
				}
				$lastSeenID = \max( $lastSeenID, (int)$item->VO->resultitem_id );
				$record = $item->getMalwareRecord();
				if ( $record instanceof Record && self::needsFollowUp( $record ) ) {
					$due = (int)$record->reported_at + self::DELAY;
					if ( $includeOverdue || $due > $now ) {
						$due = $due <= $now ? $now + 5 : $due;
						$earliest = $earliest === 0 ? $due : \min( $earliest, $due );
					}
				}
			}
		} while ( \count( $items ) === self::PAGE_SIZE );

		if ( $earliest > 0 ) {
			$existing = wp_next_scheduled( $this->hook() );
			if ( $existing === false || $existing > $earliest ) {
				// Remove the later event first: WordPress suppresses nearby single-event duplicates.
				if ( $existing !== false && !wp_unschedule_event( $existing, $this->hook() ) ) {
					throw new \RuntimeException( 'Could not replace the MALai early recheck event.' );
				}
				if ( !wp_schedule_single_event( $earliest, $this->hook() ) ) {
					throw new \RuntimeException( 'Could not schedule the MALai early recheck event.' );
				}
			}
		}
	}

	public function run() :void {
		try {
			( new RetrieveMalwareMalaiStatus() )->reconcileActiveResults( true );
		}
		catch ( \Throwable $e ) {
			error_log( 'Shield early malware recheck failed: '.$e->getMessage() );
		}
		finally {
			// Failed overdue work returns to normal scheduling, avoiding a rapid retry loop.
			$this->schedule( false );
		}
	}
}
