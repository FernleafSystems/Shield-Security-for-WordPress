<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Crons;

use FernleafSystems\Wordpress\Services\Services;

trait StandardCron {

	/**
	 * @var int
	 */
	private $nFirstRun;

	protected function setupCron() {
		try {
			Services::WpCron()
					->setRecurrence( $this->getCronRecurrence() )
					->setNextRun( $this->getFirstRunTimestamp() )
					->createCronJob( $this->getCronName(), array( $this, 'runCron' ) );
		}
		catch ( \Exception $oE ) {
		}
		add_action( $this->prefix( 'deactivate_plugin' ), array( $this, 'deleteCron' ) );
	}

	/**
	 * @return string
	 */
	protected function getCronRecurrence() {
		$sFreq = $this->getCronFrequency();
		$aStdIntervals = array_keys( wp_get_schedules() );
		return in_array( $sFreq, $aStdIntervals ) ? $sFreq : $this->prefix( sprintf( 'per-day-%s', $sFreq ) );
	}

	/**
	 * @return int|string
	 */
	protected function getCronFrequency() {
		return 'daily';
	}

	/**
	 * @return string
	 */
	abstract protected function getCronName();

	/**
	 * @return int
	 */
	public function getFirstRunTimestamp() {
		return empty( $this->nFirstRun ) ? ( Services::Request()->ts() + MINUTE_IN_SECONDS ) : $this->nFirstRun;
	}

	/**
	 * @return int
	 */
	protected function getNextCronRun() {
		$nNext = wp_next_scheduled( $this->getCronName() );
		return is_numeric( $nNext ) ? $nNext : 0;
	}

	/**
	 */
	public function deleteCron() {
		Services::WpCron()->deleteCronJob( $this->getCronName() );
	}

	protected function resetCron() {
		$this->deleteCron();
		$this->setupCron();
	}

	/**
	 */
	public function runCron() {
		// Override to run the actual Cron activity
	}

	/**
	 * @param int $nFirstRun
	 * @return $this
	 */
	public function setFirstRun( $nFirstRun ) {
		$this->nFirstRun = $nFirstRun;
		return $this;
	}
}