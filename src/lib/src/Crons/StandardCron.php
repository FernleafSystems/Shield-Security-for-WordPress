<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Crons;

use FernleafSystems\Wordpress\Services\Services;

trait StandardCron {

	/**
	 * @var int
	 */
	private $nCronFirstRun;

	protected function setupCron() {
		try {
			Services::WpCron()
					->setRecurrence( $this->getCronRecurrence() )
					->setNextRun( $this->getFirstRunTimestamp() )
					->createCronJob( $this->getCronName(), [ $this, 'runCron' ] );
		}
		catch ( \Exception $e ) {
		}
		add_action( self::con()->prefix( 'deactivate_plugin' ), [ $this, 'deleteCron' ] );
	}

	/**
	 * @return string
	 */
	protected function getCronRecurrence() {
		$frequency = $this->getCronFrequency();
		return \in_array( $frequency, \array_keys( wp_get_schedules() ) ) ? $frequency
			: self::con()->prefix( sprintf( 'per-day-%s', $frequency ) );
	}

	/**
	 * @return int|string
	 */
	protected function getCronFrequency() {
		return 'daily';
	}

	abstract protected function getCronName() :string;

	public function getFirstRunTimestamp() :int {
		return empty( $this->nCronFirstRun ) ?
			( Services::Request()->ts() + \MINUTE_IN_SECONDS )
			: $this->nCronFirstRun;
	}

	/**
	 * @return int
	 */
	protected function getNextCronRun() {
		$nNext = wp_next_scheduled( $this->getCronName() );
		return is_numeric( $nNext ) ? $nNext : 0;
	}

	public function deleteCron() {
		Services::WpCron()->deleteCronJob( $this->getCronName() );
	}

	protected function resetCron() {
		$this->deleteCron();
		$this->setupCron();
	}

	public function runCron() {
		// Override to run the actual Cron activity
	}

	public function setFirstRun( int $firstRunAt ) :self {
		$this->nCronFirstRun = $firstRunAt;
		return $this;
	}
}