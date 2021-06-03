<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Commit {

	use HandlerConsumer;

	/**
	 * @param AuditTrail\EntryVO[] $events
	 */
	public function commitAudits( array $events ) {
		if ( is_array( $events ) ) {
			foreach ( $events as $entry ) {
				if ( $entry instanceof AuditTrail\EntryVO ) {
					$this->commitAudit( $entry );
				}
			}
		}
	}

	public function commitAudit( AuditTrail\EntryVO $entry ) {
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

		$latest = null;
		$canCount = in_array( $entry->event, $this->getCanCountEvents() );
		if ( $canCount ) {
			/** @var AuditTrail\Select $select */
			$select = $this->getDbHandler()->getQuerySelector();
			$latest = $select->filterByEvent( $entry->event )
							 ->filterByIp( $entry->ip )
							 ->filterByCreatedAt( Services::Request()->carbon()->subDay()->timestamp, '>' )
							 ->first();
			$canCount = ( $latest instanceof AuditTrail\EntryVO )
						&& (  $latest->ip === $entry->ip );
		}

		if ( $canCount ) {
			/** @var AuditTrail\Update $updater */
			$updater = $this->getDbHandler()->getQueryUpdater();
			$updater->updateCount( $latest );
		}
		else {
			/** @var AuditTrail\Insert $inserter */
			$inserter = $this->getDbHandler()->getQueryInserter();
			$inserter->insert( $entry );
		}
	}

	/**
	 * TODO: This should be a config
	 * @return string[]
	 */
	private function getCanCountEvents() :array {
		return [ 'conn_kill' ];
	}
}
