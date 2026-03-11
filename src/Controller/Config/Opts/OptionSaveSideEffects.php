<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaEmailSendVerification;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Delete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\CleanLockRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class OptionSaveSideEffects {

	use PluginControllerConsumer;

	public function run() :void {
		$this->login();
		$this->ips();
		$this->securityAdmin();
		$this->scanners();
	}

	private function login() :void {
		if ( self::con()->opts->optChanged( 'enable_email_authentication' ) ) {
			try {
				self::con()->action_router->action( MfaEmailSendVerification::class );
			}
			catch ( \Exception $e ) {
			}
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
			$con->comps->file_locker->clearLocks();
			$lockFiles = $opts->optGet( 'file_locker' );
			if ( \count( $lockFiles ) === 0 || !$con->comps->shieldnet->canHandshake() ) {
				$con->comps->file_locker->purge();
			}
			else {
				( new CleanLockRecords() )->run();
			}
		}
	}
}
