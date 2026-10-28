<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules;

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

		$extraOffenses = 0;
		$idsToDelete = [];

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
				$extraOffenses += $record->offenses;
				$idsToDelete[] = $record->id;
			}
		}

		if ( !empty( $toKeep ) && !empty( $idsToDelete ) ) {
			$dbh->getQueryDeleter()
				->addWhereIn( 'id', $idsToDelete )
				->query();

			$updateData = \array_merge( [
				'offenses' => $toKeep->offenses + $extraOffenses,
			], $this->mergeEvidence( $records, $toKeep ) );
			if ( $updateData[ 'offenses' ] >= self::con()->comps->opts_lookup->getIpAutoBlockOffenseLimit()
				 && $toKeep->blocked_at <= $toKeep->unblocked_at ) {
				$updateData[ 'blocked_at' ] = Services::Request()->ts();
			}

			$dbh->getQueryUpdater()->updateRecord( $toKeep, $updateData );
			$dbh->getQuerySelector()->byId( $toKeep->id );
		}
	}

	/**
	 * Every record's evidence faded to now and added together, keeping weak signal caps and the latest hold.
	 * @param IpRuleRecord[] $records
	 */
	private function mergeEvidence( array $records, IpRuleRecord $toKeep ) :array {
		$now = Services::Request()->ts();
		$blockSeconds = self::con()->comps->opts_lookup->getIpAutoBlockTTL();

		$normal = 0.0;
		$strong = 0.0;
		$weak = [];
		$hostileUntil = 0;
		foreach ( $records as $record ) {
			$stored = new StoredEvidence( $record );
			$normal += $stored->offensesFor( EvidenceWeights::NORMAL, $now );
			$strong += $stored->offensesFor( EvidenceWeights::STRONG, $now );
			foreach ( $stored->weakSignals( $now ) as $signal => $offenses ) {
				$weak[ $signal ] = \min( EvidenceWeights::WEAK_SIGNAL_CAP, ( $weak[ $signal ] ?? 0.0 ) + $offenses );
			}
			$hostileUntil = \max( $hostileUntil, $stored->hostileUntil( $blockSeconds ) );
		}

		$meta = $toKeep->meta;
		$meta[ StoredEvidence::META_WEAK ] = $weak;
		return [
			'normal_evidence' => $normal,
			'strong_evidence' => $strong,
			'evidence_at'     => $now,
			'hostile_until'   => $hostileUntil,
			'meta'            => (string)$toKeep->arrayDataWrap( $meta ),
		];
	}
}
