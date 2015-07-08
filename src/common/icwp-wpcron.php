<?php
if ( !class_exists( 'ICWP_WPSF_WpCron', false ) ):

	class ICWP_WPSF_WpCron {

		/**
		 * @var ICWP_WPSF_WpCron
		 */
		protected static $oInstance = NULL;

		private function __construct() {}

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
		 * @param string $sUniqueCronName
		 * @param array|string $sCallback
		 * @param string $sRecurrence
		 * @throws Exception
		 */
		public function createCronJob( $sUniqueCronName, $sCallback, $sRecurrence = 'daily' ) {
			if ( !is_callable( $sCallback ) ) {
				throw new Exception( sprintf( 'Tried to schedule a new cron but the Callback function is not callable: %s', print_r( $sCallback, true ) ) );
			}
			add_action( $sUniqueCronName, $sCallback );
			$this->setCronSchedule( $sUniqueCronName, $sRecurrence );
		}

		/**
		 * @param string $sUniqueCronName
		 */
		public function deleteCronJob( $sUniqueCronName ) {
			wp_clear_scheduled_hook( $sUniqueCronName );
		}

		/**
		 * @param $sUniqueCronActionName
		 * @param $sRecurrence				- one of hourly, twicedaily, daily
		 */
		protected function setCronSchedule( $sUniqueCronActionName, $sRecurrence ) {
			if ( ! wp_next_scheduled( $sUniqueCronActionName ) && ! defined( 'WP_INSTALLING' ) ) {
				$nNextRun = strtotime( 'tomorrow 6am' ) - get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
				wp_schedule_event( $nNextRun, $sRecurrence, $sUniqueCronActionName );
			}
		}
	}

endif;