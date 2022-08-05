<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops as IpRulesDB;

class ProcessOffense {

	use Shield\Modules\ModConsumer;
	use IpAddressConsumer;

	public function run() {
		$con = $this->getCon();
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_IPRules();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		try {
			$IP = ( new IPs\Lib\Ops\AddIP() )
				->setMod( $mod )
				->setIP( $this->getIP() )
				->toAutoBlacklist();

			$currentCount = $IP->offenses;

			$offenseTracker = $mod->loadOffenseTracker();
			$newCount = $IP->offenses + $offenseTracker->getOffenseCount();
			$toBlock = $offenseTracker->isBlocked() ||
					   ( $IP->blocked_at == 0 && ( $newCount >= $opts->getOffenseLimit() ) );

			if ( $toBlock ) {
				$newCount = (int)max( 1, $newCount ); // Ensure there's an offense registered for immediate blocks
			}

			/** @var IpRulesDB\Update $updater */
			$updater = $dbh->getQueryUpdater();
			$updater->updateTransgressions( $IP, $newCount );

			$con->fireEvent( $toBlock ? 'ip_blocked' : 'ip_offense',
				[
					'audit_params' => [
						'from' => $currentCount,
						'to'   => $newCount,
					]
				]
			);

			/**
			 * When we block, we also want to increment offense stat, but we don't
			 * want to also audit the offense (only audit the block),
			 * so we fire ip_offense but suppress the audit
			 */
			if ( $toBlock ) {
				/** @var IpRulesDB\Update $updater */
				$updater = $dbh->getQueryUpdater();
				$updater->setBlocked( $IP );
				$con->fireEvent( 'ip_offense',
					[
						'suppress_audit' => true,
						'audit_params'   => [
							'from' => $currentCount,
							'to'   => $newCount,
						]
					]
				);
			}
		}
		catch ( \Exception $e ) {
		}

	}
}