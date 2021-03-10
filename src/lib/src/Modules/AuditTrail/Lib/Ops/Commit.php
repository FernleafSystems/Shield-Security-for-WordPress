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
			foreach ( $aEvents as $entry ) {
				if ( $entry instanceof AuditTrail\EntryVO ) {
					$this->commitAudit( $entry );
				}
			}
		}
	}

	/**
	 * @param AuditTrail\EntryVO $entry
	 */
	public function commitAudit( $entry ) {
		$WP = Services::WpGeneral();
		$WPU = Services::WpUsers();

		if ( empty( $entry->ip ) ) {
			$entry->ip = Services::IP()->getRequestIp();
		}
		if ( empty( $entry->wp_username ) ) {
			if ( $WPU->isUserLoggedIn() ) {
				$sUser = $WPU->getCurrentWpUsername();
			}
			elseif ( $WP->isCron() ) {
				$sUser = 'WP Cron';
			}
			elseif ( $WP->isWpCli() ) {
				$sUser = 'WP CLI';
			}
			else {
				$sUser = '-';
			}
			$entry->wp_username = $sUser;
		}

		$oLatest = null;
		$bCanCount = in_array( $entry->event, $this->getCanCountEvents() );
		if ( $bCanCount ) {
			/** @var AuditTrail\Select $oSel */
			$oSel = $this->getDbHandler()->getQuerySelector();
			$oLatest = $oSel->filterByEvent( $entry->event )
							->filterByIp( $entry->ip )
							->filterByCreatedAt( Services::Request()->carbon()->subDay()->timestamp, '>' )
							->first();
			$bCanCount = ( $oLatest instanceof AuditTrail\EntryVO )
						 && ( $oLatest->event === $entry->event && $oLatest->ip === $entry->ip );
		}

		if ( $bCanCount ) {
			/** @var AuditTrail\Update $oQU */
			$oQU = $this->getDbHandler()->getQueryUpdater();
			$oQU->updateCount( $oLatest );
		}
		else {
			/** @var AuditTrail\Insert $oQI */
			$oQI = $this->getDbHandler()->getQueryInserter();
			$oQI->insert( $entry );
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
