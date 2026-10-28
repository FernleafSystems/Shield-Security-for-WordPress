<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * Adds one request's offenses and evidence to an IP's auto-block record, in the one write offense
 * processing already makes.
 */
class RecordEvidence {

	use PluginControllerConsumer;

	public function toRecord( IpRulesDB\Record $record, int $offenses, EvidenceLedger $ledger, int $now ) :bool {
		$weak = ( new StoredEvidence( $record ) )->weakSignals( $now );
		foreach ( $ledger->weakSignals() as $signal => $weakOffenses ) {
			$weak[ $signal ] = \min( EvidenceWeights::WEAK_SIGNAL_CAP, ( $weak[ $signal ] ?? 0.0 ) + $weakOffenses );
		}

		$opts = self::con()->comps->opts_lookup;
		/** @var IpRulesDB\Update $updater */
		$updater = self::con()->db_con->ip_rules->getQueryUpdater();
		return $updater->addOffenseEvidence(
			$record,
			$offenses,
			$ledger->offensesFor( EvidenceWeights::NORMAL ),
			$ledger->offensesFor( EvidenceWeights::STRONG ),
			$weak,
			(float)\max( 0, $opts->getIpAutoBlockOffenseLimit() ),
			$opts->getIpAutoBlockTTL(),
			$now
		);
	}
}
