<?php

class ICWP_WPSF_WpCron {

	/**
	 * @var ICWP_WPSF_WpCron
	 */
	protected static $oInstance = null;

	/**
	 * @var int
	 */
	protected $nNextRun;

	/**
	 * @var string
	 */
	protected $sRecurrence;

	/**
	 * @var array
	 */
	protected $aSchedules;

	/**
	 * @return ICWP_WPSF_WpCron
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	private function __construct() {
		add_filter( 'cron_schedules', array( $this, 'addSchedules' ) );
	}

	/**
	 * @param array $aSchedules
	 * @return array
	 */
	public function addSchedules( $aSchedules ) {
		return array_merge( $aSchedules, $this->getSchedules() );
	}

	/**
	 * @param string $sSlug
	 * @param array  $aNewSchedule
	 * @return $this
	 */
	public function addNewSchedule( $sSlug, $aNewSchedule ) {
		if ( !empty( $aNewSchedule ) && is_array( $aNewSchedule ) ) {
			$aSchedules = $this->getSchedules();
			$aSchedules[ $sSlug ] = $aNewSchedule;
			$this->aSchedules = $aSchedules;
		}
		return $this;
	}

	/**
	 * @deprecated uses undocumented private WP function
	 * @return array
	 */
	public function getCrons() {
		return function_exists( '_get_cron_array' ) && is_array( _get_cron_array() ) ? _get_cron_array() : array();
	}

	/**
	 * @return array
	 */
	protected function getSchedules() {
		if ( !is_array( $this->aSchedules ) ) {
			$this->aSchedules = array();
		}
		return $this->aSchedules;
	}

	/**
	 * @param string $sCronName
	 * @return bool
	 */
	public function getIfCronExists( $sCronName ) {
		return (bool)wp_next_scheduled( $sCronName );
	}

	/**
	 * @return int
	 */
	public function getNextRun() {
		if ( is_null( $this->nNextRun ) ) {
			return strtotime( 'tomorrow 4am' ) - get_option( 'gmt_offset' )*HOUR_IN_SECONDS;
		}
		return $this->nNextRun;
	}

	/**
	 * @return string
	 */
	public function getRecurrence() {
		if ( empty( $this->sRecurrence ) ) {
			return 'daily';
		}
		return $this->sRecurrence;
	}

	/**
	 * @param int $nNextRun
	 * @return $this
	 */
	public function setNextRun( $nNextRun ) {
		$this->nNextRun = $nNextRun;
		return $this;
	}

	/**
	 * @param string $sRecurrence
	 * @return $this
	 */
	public function setRecurrence( $sRecurrence ) {
		$this->sRecurrence = $sRecurrence;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function reset() {
		return $this
			->setNextRun( null )
			->setRecurrence( null );
	}

	/**
	 * @param string   $sUniqueCronName
	 * @param callable $cCallback
	 * @return $this
	 * @throws Exception
	 */
	public function createCronJob( $sUniqueCronName, $cCallback ) {
		if ( !is_callable( $cCallback ) ) {
			throw new Exception( sprintf( 'Tried to schedule a new cron but the Callback function is not callable: %s', print_r( $cCallback, true ) ) );
		}
		add_action( $sUniqueCronName, $cCallback );
		return $this->setCronSchedule( $sUniqueCronName );
	}

	/**
	 * @param string $sUniqueCronName
	 * @return $this
	 */
	public function deleteCronJob( $sUniqueCronName ) {
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( $sUniqueCronName );
		}
		wp_clear_scheduled_hook( $sUniqueCronName );
		return $this;
	}

	/**
	 * @param string $sUniqueCronActionName
	 * @return $this
	 */
	protected function setCronSchedule( $sUniqueCronActionName ) {
		if ( !wp_next_scheduled( $sUniqueCronActionName ) && !defined( 'WP_INSTALLING' ) ) {
			wp_schedule_event( $this->getNextRun(), $this->getRecurrence(), $sUniqueCronActionName );
			$this->reset();
		}
		return $this;
	}
}