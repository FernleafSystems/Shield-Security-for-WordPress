<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing\{
	EvidenceWeights,
	StoredEvidence
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class MergeAutoBlockRules {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function byIP( string $ip ) :void {
		$loader = ( new LoadIpRules() )->setIP( $ip );
		$loader->wheres = [
			"`ir`.`is_range`='0'"
		];
		$this->byRecords( $loader->select() );
	}

	/**
	 * @throws \Exception
	 */
	public function byRecords( array $records ) :void {
		$dbh = self::con()->db_con->ip_rules;

		if ( \count( $records ) < 2 ) {
			throw new \Exception( __( 'At least two records are required to merge.', 'wp-simple-firewall' ) );
		}

		$workingIP = null;
		$toKeep = null;
		$duplicates = [];

		foreach ( $records as $record ) {

			if ( empty( $workingIP ) ) {
				$workingIP = $record->ip;
			}
			elseif ( $workingIP !== $record->ip ) {
				throw new \Exception( __( 'The records do not pertain to the same IP address.', 'wp-simple-firewall' ) );
			}

			if ( !isset( $toKeep ) ) {
				$toKeep = $record;
			}
			else {
				$duplicates[] = $record;
			}
		}

		if ( !empty( $toKeep ) && !empty( $duplicates ) ) {
			$now = Services::Request()->ts();
			$blockSeconds = self::con()->comps->opts_lookup->getIpAutoBlockTTL();

			$offenses = 0;
			$normal = 0.0;
			$strong = 0.0;
			$hostileUntil = 0;
			$weak = ( new StoredEvidence( $toKeep ) )->weakSignals( $now );
			$merged = false;
			foreach ( $duplicates as $duplicate ) {
				/**
				 * Only a duplicate this merge deleted is counted, so two merges at once can't count it twice.
				 * Its offenses and evidence are added to the kept record, so writes to that record aren't lost.
				 * A write to a duplicate between loading and deleting it is lost, as its offenses always were.
				 */
				if ( $dbh->getQueryDeleter()->deleteById( $duplicate->id ) ) {
					$merged = true;
					$stored = new StoredEvidence( $duplicate );
					$offenses += $duplicate->offenses;
					$normal += $stored->offensesFor( EvidenceWeights::NORMAL, $now );
					$strong += $stored->offensesFor( EvidenceWeights::STRONG, $now );
					foreach ( $stored->weakSignals( $now ) as $signal => $weakOffenses ) {
						$weak[ $signal ] = \min( EvidenceWeights::WEAK_SIGNAL_CAP, ( $weak[ $signal ] ?? 0.0 ) + $weakOffenses );
					}
					$hostileUntil = \max( $hostileUntil, $stored->hostileUntil( $blockSeconds ) );
				}
			}

			if ( $merged ) {
				/** @var IpRulesDB\Update $updater */
				$updater = $dbh->getQueryUpdater();
				$updater->addMergedEvidence( $toKeep, $offenses, $normal, $strong, $weak, $hostileUntil, $blockSeconds, $now );

				if ( $toKeep->offenses + $offenses >= self::con()->comps->opts_lookup->getIpAutoBlockOffenseLimit()
					 && $toKeep->blocked_at <= $toKeep->unblocked_at ) {
					$updater->updateById( $toKeep->id, [ 'blocked_at' => $now ] );
				}
			}
		}
	}
}
