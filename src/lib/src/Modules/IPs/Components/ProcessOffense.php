<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class ProcessOffense {

	use Shield\Modules\ModConsumer;
	use IpAddressConsumer;

	public function run() {
		$con = $this->getCon();
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		try {
			$IP = ( new IPs\Lib\Ops\AddIp() )
				->setMod( $mod )
				->setIP( $this->getIP() )
				->toAutoBlacklist();
		}
		catch ( \Exception $e ) {
			$IP = null;
		}

		if ( !empty( $IP ) ) {
			$currentCount = $IP->transgressions;

			$offenseTracker = $mod->loadOffenseTracker();
			$newCount = $IP->transgressions + $offenseTracker->getOffenseCount();
			$toBlock = $offenseTracker->isBlocked() ||
					   ( $IP->blocked_at == 0 && ( $newCount >= $opts->getOffenseLimit() ) );

			/** @var Databases\IPs\Update $updater */
			$updater = $mod->getDbHandler_IPs()->getQueryUpdater();
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
				$updater = $mod->getDbHandler_IPs()->getQueryUpdater();
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
	}
}