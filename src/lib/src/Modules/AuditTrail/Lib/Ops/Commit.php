<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Commit {

	use HandlerConsumer;

	/**
	 * @var AuditTrail\EntryVO|null
	 */
	private $oLatest;

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
			$oLatest = $this->latest();
			if ( $oLatest instanceof AuditTrail\EntryVO ) {
				foreach ( [ 'event', 'ip' ] as $sCol ) {
					$bCanCount = $bCanCount && ( $oLatest->{$sCol} === $oEntry->{$sCol} );
				}
			}
			else {
				$bCanCount = false;
			}
		}

		if ( $bCanCount && $oLatest instanceof AuditTrail\EntryVO ) {
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

	/**
	 * @return AuditTrail\EntryVO|false
	 */
	private function latest() {
		if ( is_null( $this->oLatest ) ) {
			$this->oLatest = $this->getDbHandler()
								  ->getQuerySelector()
								  ->selectLatestById();
			if ( empty( $this->oLatest ) ) {
				$this->oLatest = false;
			}
		}
		return $this->oLatest;
	}
}
