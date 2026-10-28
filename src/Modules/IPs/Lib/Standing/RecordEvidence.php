<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Adds one request's evidence to an IP's auto-block record.
 */
class RecordEvidence {

	use PluginControllerConsumer;

	public function toRecord( IpRulesDB\Record $record, EvidenceLedger $ledger ) :bool {
		if ( $ledger->isEmpty() ) {
			return true;
		}

		$now = Services::Request()->ts();
		$opts = self::con()->comps->opts_lookup;

		$weak = ( new StoredEvidence( $record ) )->weakSignals( $now );
		foreach ( $ledger->weakSignals() as $signal => $units ) {
			$weak[ $signal ] = \min( EvidenceWeights::WEAK_SIGNAL_CAP, ( $weak[ $signal ] ?? 0 ) + $units );
		}

		/** @var IpRulesDB\Update $updater */
		$updater = self::con()->db_con->ip_rules->getQueryUpdater();
		return $updater->addEvidence(
			$record,
			$ledger->unitsFor( EvidenceWeights::NORMAL ),
			$ledger->unitsFor( EvidenceWeights::STRONG ),
			$weak,
			\max( 0, $opts->getIpAutoBlockOffenseLimit() )*EvidenceWeights::UNITS_PER_OFFENSE,
			$now + $opts->getIpAutoBlockTTL()
		);
	}
}
