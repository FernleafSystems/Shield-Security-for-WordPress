<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\{
	AddRule,
	IpRulesCache
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ProcessOffense {

	use ExecOnce;
	use IpAddressConsumer;
	use PluginControllerConsumer;

	protected function run() {
		try {
			$this->incrementOffenses(
				self::con()->comps->offense_tracker->getOffenseCount(),
				self::con()->comps->offense_tracker->isBlocked()
			);
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	public function incrementOffenses( int $incrementBy, bool $blockIP = false, bool $fireEvents = true ) :void {
		$con = self::con();
		$limit = $con->comps->opts_lookup->getIpAutoBlockOffenseLimit();

		$IP = ( new AddRule() )
			->setIP( $this->getIP() )
			->toAutoBlacklist();

		$originalCount = $IP->offenses;

		$newCount = $originalCount + $incrementBy;
		$toBlock = $blockIP
				   || ( $newCount >= $limit && $IP->blocked_at <= $IP->unblocked_at );

		if ( $toBlock ) {
			$newCount = (int)\max( 1, $newCount ); // Ensure there's an offense registered for immediate blocks
		}

		if ( $fireEvents ) {
			$con->fireEvent( $toBlock ? 'ip_blocked' : 'ip_offense',
				[
					'audit_params' => [
						'from' => $originalCount,
						'to'   => $newCount,
					]
				]
			);
		}

		/** @var IpRulesDB\Update $updater */
		$updater = $con->db_con->ip_rules->getQueryUpdater();
		$updater->updateTransgressions( $IP, $newCount );

		/**
		 * When we block, we also want to increment offense stat, but we don't
		 * want to also audit the offense (only audit the block),
		 * so we fire ip_offense but suppress the audit
		 */
		if ( $toBlock ) {
			/** @var IpRulesDB\Update $updater */
			$updater = $con->db_con->ip_rules->getQueryUpdater();
			$updater->setBlocked( $IP );

			if ( $fireEvents ) {
				$con->fireEvent( 'ip_offense',
					[
						'suppress_audit' => true,
						'audit_params'   => [
							'from' => $originalCount,
							'to'   => $newCount,
						]
					]
				);
			}
		}

		/** This isn't really needed here, but to make absolutely sure, we remove the IP from the no-rules cache */
		IpRulesCache::Delete( $this->getIP(), IpRulesCache::GROUP_NO_RULES );
	}
}