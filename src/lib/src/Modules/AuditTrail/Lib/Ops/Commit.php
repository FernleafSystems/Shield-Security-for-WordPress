<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Commit {

	use HandlerConsumer;

	/**
	 * @param AuditTrail\EntryVO[] $aEvents
	 */
	public function commitAudits( $aEvents ) {
		if ( is_array( $aEvents ) ) {
			foreach ( $aEvents as $oEntry ) {
				if ( $oEntry instanceof AuditTrail\EntryVO ) {
					$this->commitAudit( $oEntry );
				}
			}
		}
	}

	/**
	 * @param AuditTrail\EntryVO $oEntry
	 */
	public function commitAudit( $oEntry ) {
		$oWp = Services::WpGeneral();
		$oWpUsers = Services::WpUsers();

		if ( empty( $oEntry->ip ) ) {
			$oEntry->ip = Services::IP()->getRequestIp();
		}
		if ( empty( $oEntry->message ) ) {
			$oEntry->message = '';
		}
		if ( empty( $oEntry->wp_username ) ) {
			if ( $oWpUsers->isUserLoggedIn() ) {
				$sUser = $oWpUsers->getCurrentWpUsername();
			}
			elseif ( $oWp->isCron() ) {
				$sUser = 'WP Cron';
			}
			elseif ( $oWp->isWpCli() ) {
				$sUser = 'WP CLI';
			}
			else {
				$sUser = '-';
			}
			$oEntry->wp_username = $sUser;
		}

		$oLatest = null;
		$bCanCount = in_array( $oEntry->event, $this->getCanCountEvents() );
		if ( $bCanCount ) {
			/** @var AuditTrail\Select $oSel */
			$oSel = $this->getDbHandler()->getQuerySelector();
			$oLatest = $oSel->filterByEvent( $oEntry->event )
							->filterByIp( $oEntry->ip )
							->filterByCreatedAt( Services::Request()->carbon()->subDay()->timestamp, '>' )
							->first();
			$bCanCount = ( $oLatest instanceof AuditTrail\EntryVO )
						 && ( $oLatest->event === $oEntry->event && $oLatest->ip === $oEntry->ip );
		}

		if ( $bCanCount ) {
			/** @var AuditTrail\Update $oQU */
			$oQU = $this->getDbHandler()->getQueryUpdater();
			$oQU->updateCount( $oLatest );
		}
		else {
			/** @var AuditTrail\Insert $oQI */
			$oQI = $this->getDbHandler()->getQueryInserter();
			$oQI->insert( $oEntry );
		}
	}

	/**
	 * TODO: This should be a config
	 * @return string[]
	 */
	private function getCanCountEvents() {
		return [ 'conn_kill' ];
	}
}
