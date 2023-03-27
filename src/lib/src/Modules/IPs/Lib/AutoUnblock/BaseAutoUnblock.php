<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseAutoUnblock {

	use IPs\ModConsumer;

	public function canRunAutoUnblockProcess() :bool {
		return $this->isUnblockAvailable();
	}

	public function processAutoUnblockRequest() :bool {
		try {
			$unblocked = $this->preUnblockChecks() && $this->unblockIP();
		}
		catch ( \Exception $e ) {
			$unblocked = false;
		}
		return $unblocked;
	}

	public function isUnblockAvailable() :bool {
		$thisReq = $this->getCon()->this_req;
		try {
			$available = $thisReq->ip
						 && ( $thisReq->is_ip_blocked_crowdsec || $thisReq->is_ip_blocked_shield_auto );
			if ( $available ) {
				$this->timingChecks();
			}
		}
		catch ( \Exception $e ) {
			$available = false;
		}
		return $available;
	}

	/**
	 * @throws \Exception
	 */
	protected function preUnblockChecks() :bool {
		$this->timingChecks();
		$this->updateLastAttemptAt();
		return true;
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
		$this->mod()
			 ->getDbH_IPRules()
			 ->getQueryUpdater()
			 ->updateById( $this->getIpRecord()->id, [
				 'last_unblock_attempt_at' => Services::Request()->ts(),
			 ] );
	}

	/**
	 * @throws \Exception
	 */
	protected function unblockIP() :bool {
		$record = $this->getIpRecord();

		try {
			( new BotSignalsRecord() )
				->setIP( $this->getCon()->this_req->ip )
				->updateSignalField( 'unblocked_at' );
		}
		catch ( \LogicException $e ) {
			error_log( 'Error updating bot signal with column problem: '.$e->getMessage() );
		}
		catch ( \Exception $e ) {
//					error_log( 'Error updating bot signal: '.$e->getMessage() );
		}

		$unblocked = $this->mod()
						  ->getDbH_IPRules()
						  ->getQueryUpdater()
						  ->updateById( $record->id, [
							  'offenses'       => 0,
							  'unblocked_at'   => Services::Request()->ts(),
							  'last_access_at' => Services::Request()->ts(),
						  ] );

		$this->fireEvent();

		return $unblocked;
	}

	protected function fireEvent() {
		$this->getCon()->fireEvent( 'ip_unblock_auto', [
			'audit_params' => [
				'ip'     => $this->getCon()->this_req->ip,
				'method' => $this->getUnblockMethodName()
			]
		] );
	}

	protected function getUnblockMethodName() :string {
		return '';
	}

	/**
	 * @throws \Exception
	 */
	abstract protected function getIpRecord() :IpRuleRecord;
}