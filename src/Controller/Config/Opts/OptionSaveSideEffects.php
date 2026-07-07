<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Delete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\CleanLockRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\EmailDeliveryVerification;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class OptionSaveSideEffects {

	use PluginControllerConsumer;

	public function run() :void {
		$this->login();
		$this->integrations();
		$this->importExport();
		$this->ips();
		$this->securityAdmin();
		$this->scanners();
	}

	private function login() :void {
		$opts = self::con()->opts;
		if ( $opts->optChanged( 'enable_email_authentication' ) && $opts->optIs( 'enable_email_authentication', 'N' ) ) {
			( new EmailDeliveryVerification() )->clearSent();
		}
	}

	private function integrations() :void {
		$opts = self::con()->opts;
		if ( $opts->optChanged( 'enable_auto_integrations' ) ) {
			$opts->optSet( 'auto_integrations_track', [] );
		}
	}

	private function importExport() :void {
		$opts = self::con()->opts;
		if ( $opts->optChanged( 'importexport_enable' ) && $opts->optIs( 'importexport_enable', 'N' ) ) {
			( new NetworkInviteRepository() )->clearAll( false );
		}
		if ( $opts->optChanged( 'importexport_masterurl' ) && \trim( (string)$opts->optGet( 'importexport_masterurl' ) ) !== '' ) {
			( new NetworkInviteRepository() )->clearAll( false );
		}
	}

	private function ips() :void {
		$opts = self::con()->opts;
		$dbhIPRules = self::con()->db_con->ip_rules;

		if ( $opts->optChanged( 'cs_block' ) && $opts->optIs( 'cs_block', 'disabled' ) ) {
			/** @var Delete $deleter */
			$deleter = $dbhIPRules->getQueryDeleter();
			$deleter->filterByType( $dbhIPRules::T_CROWDSEC )->query();
		}
		if ( $opts->optChanged( 'transgression_limit' ) && $opts->optGet( 'transgression_limit' ) === 0 ) {
			/** @var Delete $deleter */
			$deleter = $dbhIPRules->getQueryDeleter();
			$deleter->filterByType( $dbhIPRules::T_AUTO_BLOCK )->query();
		}
	}

	private function securityAdmin() :void {
		if ( self::con()->opts->optChanged( 'enable_mu' ) ) {
			self::con()->comps->mu->run();
		}
	}

	private function scanners() :void {
		$con = self::con();
		$opts = $con->opts;

		if ( $opts->optChanged( 'scan_frequency' ) ) {
			$con->comps->scans->deleteCron();
		}

		if ( $opts->optChanged( 'file_locker' ) ) {
			$lockFiles = $opts->optGet( 'file_locker' );
			if ( \count( $lockFiles ) === 0 || !$con->comps->shieldnet->canHandshake() ) {
				$con->comps->file_locker->purge();
			}
			else {
				$dbh = $con->db_con->file_locker;
				$schema = $dbh->getTableSchema();
				$dbh::GetTableReadyCache()->setReady( $schema, false );
				Services::WpDb()->clearResultShowTables();
				$con->db_con->loadDbH( $schema->slug, true );
				$con->comps->file_locker->clearLocks();

				( new CleanLockRecords() )->run();
			}
		}
	}
}
