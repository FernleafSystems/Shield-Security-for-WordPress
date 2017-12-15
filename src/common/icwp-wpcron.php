<?php
if ( !class_exists( 'ICWP_WPSF_WpCron', false ) ):

	class ICWP_WPSF_WpCron {

		/**
		 * @var ICWP_WPSF_WpCron
		 */
		protected static $oInstance = NULL;
		private function __construct() {}

		/**
		 * @var int
		 */
		protected $nNextRun;

		/**
		 * @var string
		 */
		protected $sRecurrence;

		/**
		 * @return ICWP_WPSF_WpCron
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
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
				return strtotime( 'tomorrow 4am' ) - get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
			}
			return $this->nNextRun;
		}

		/**
		 * @return string
		 */
		public function getRecurrence() {
			if ( empty( $this->sRecurrence ) || !in_array( $this->sRecurrence, $this->getPermittedRecurrences() ) ) {
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
		 * @param callback $cCallback
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
		 */
		public function deleteCronJob( $sUniqueCronName ) {
			wp_clear_scheduled_hook( $sUniqueCronName );
		}

		/**
		 * @param string $sUniqueCronActionName
		 * @return $this
		 */
		protected function setCronSchedule( $sUniqueCronActionName ) {
			if ( ! wp_next_scheduled( $sUniqueCronActionName ) && ! defined( 'WP_INSTALLING' ) ) {
				wp_schedule_event( $this->getNextRun(), $this->getRecurrence(), $sUniqueCronActionName );
				$this->reset();
			}
			return $this;
		}

		/**
		 * @return array
		 */
		private function getPermittedRecurrences() {
			return array( 'hourly', 'twicedaily', 'daily' );
		}
	}

endif;