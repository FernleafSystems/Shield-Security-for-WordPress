<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Adds one request's evidence to an IP's auto-block record. Must run before the request's offenses are
 * added to the record, so a record from before visitor standing carries over only its earlier offenses.
 */
class RecordEvidence {

	use PluginControllerConsumer;

	public function toRecord( IpRulesDB\Record $record, EvidenceLedger $ledger, int $now ) :bool {
		$stored = new StoredEvidence( $record );

		$isWeakOnly = $ledger->offensesFor( EvidenceWeights::NORMAL ) <= 0 && $ledger->offensesFor( EvidenceWeights::STRONG ) <= 0;
		$isRecentlyWritten = $record->evidence_at > $now - EvidenceWeights::WEAK_WRITE_INTERVAL;
		if ( $ledger->isEmpty() || ( $isWeakOnly && $isRecentlyWritten && !$stored->isLegacy() ) ) {
			return true;
		}

		$weak = $stored->weakSignals( $now );
		foreach ( $ledger->weakSignals() as $signal => $offenses ) {
			$weak[ $signal ] = \min( EvidenceWeights::WEAK_SIGNAL_CAP, ( $weak[ $signal ] ?? 0.0 ) + $offenses );
		}

		$opts = self::con()->comps->opts_lookup;
		/** @var IpRulesDB\Update $updater */
		$updater = self::con()->db_con->ip_rules->getQueryUpdater();
		return $updater->addEvidence(
			$record,
			$ledger->offensesFor( EvidenceWeights::NORMAL ),
			$ledger->offensesFor( EvidenceWeights::STRONG ),
			$weak,
			(float)\max( 0, $opts->getIpAutoBlockOffenseLimit() ),
			$opts->getIpAutoBlockTTL(),
			$now
		);
	}
}
