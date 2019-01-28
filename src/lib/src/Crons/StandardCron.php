<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Crons;

use FernleafSystems\Wordpress\Services\Services;

trait StandardCron {

	protected function setupCron() {
		try {
			$sRecurrence = $this->getCronRecurrence();
			if ( strpos( $sRecurrence, 'per-day' ) > 0 ) {
				// It's a custom schedule so we need to set the next run time more specifically
				$nNext = Services::Request()->ts() + ( DAY_IN_SECONDS/$this->getCronFrequency() );
			}
			else {
				$nNext = null;
			}
			Services::WpCron()
					->setRecurrence( $sRecurrence )
					->setNextRun( $nNext )
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
	protected function getNextCronRun() {
		$nNext = wp_next_scheduled( $this->getCronName() );
		return is_numeric( $nNext ) ? $nNext : 0;
	}

	/**
	 * @throws \Exception
	 */
	public function deleteCron() {
		Services::WpCron()->deleteCronJob( $this->getCronName() );
	}

	/**
	 */
	public function runCron() {
	}
}