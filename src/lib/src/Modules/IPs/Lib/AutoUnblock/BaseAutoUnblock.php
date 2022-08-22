<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseAutoUnblock extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return !empty( $this->getCon()->this_req->ip );
	}

	protected function run() {
		try {
			if ( $this->processAutoUnblockRequest() ) {
				Services::Response()->redirectToHome();
			}
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function processAutoUnblockRequest() :bool {
		try {
			$unblocked = $this->canRunUnblock() && $this->unblockIP();
		}
		catch ( \Exception $e ) {
			$unblocked = false;
		}
		return $unblocked;
	}

	/**
	 * @throws \Exception
	 */
	protected function canRunUnblock() :bool {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$canRunUnblock = false;

		$nonceActionID = $this->getNonceAction();
		if ( $req->post( 'action' ) == $mod->getCon()->prefix() && $req->post( 'exec' ) == $nonceActionID ) {

			$this->timingChecks();
			$this->updateLastAttemptAt();

			if ( wp_verify_nonce( $req->post( 'exec_nonce' ), $nonceActionID ) !== 1 ) {
				throw new \Exception( 'Nonce failed' );
			}
			if ( $req->post( '_confirm' ) !== 'Y' ) {
				throw new \Exception( 'No confirmation checkbox.' );
			}
			if ( !empty( $req->post( 'email' ) ) || !empty( $req->post( 'name' ) ) ) {
				throw new \Exception( 'Oh so yummy.' );
			}

			$canRunUnblock = true;
		}

		return $canRunUnblock;
	}

	/**
	 * @throws \Exception
	 */
	protected function timingChecks() {
		$carbon = Services::Request()->carbon();
		$ipRecord = $this->getIpRecord();
		if ( $carbon->subMinute()->timestamp < $ipRecord->last_unblock_attempt_at ) {
			throw new \Exception( 'IP has recently attempted an unblock.' );
		}
		if ( $carbon->subHour()->timestamp < $ipRecord->unblocked_at ) {
			throw new \Exception( 'IP has already been unblocked recently.' );
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function updateLastAttemptAt() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$mod->getDbH_IPRules()
			->getQueryUpdater()
			->updateById( $this->getIpRecord()->id, [
				'last_unblock_attempt_at' => Services::Request()->ts(),
			] );
	}

	/**
	 * @throws \Exception
	 */
	protected function unblockIP() :bool {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();

		$record = $this->getIpRecord();

		if ( empty( $record ) ) {
			$unblocked = false;
		}
		else {
			try {
				( new BotSignalsRecord() )
					->setMod( $this->getMod() )
					->setIP( $this->getCon()->this_req->ip )
					->updateSignalField( 'unblocked_at' );
			}
			catch ( \LogicException $e ) {
				error_log( 'Error updating bot signal with column problem: '.$e->getMessage() );
			}
			catch ( \Exception $e ) {
//					error_log( 'Error updating bot signal: '.$e->getMessage() );
			}

			$unblocked = $mod->getDbH_IPRules()
							 ->getQueryUpdater()
							 ->updateById( $record->id, [
								 'offenses'       => 0,
								 'unblocked_at'   => Services::Request()->ts(),
								 'last_access_at' => Services::Request()->ts(),
							 ] );
		}

		return $unblocked;
	}

	/**
	 * @throws \Exception
	 */
	abstract protected function getIpRecord() :IpRuleRecord;

	protected function getNonceAction() :string {
		return 'uau-'.$this->getCon()->this_req->ip;
	}
}