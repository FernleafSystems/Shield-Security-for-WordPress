<?php

if ( class_exists( 'ICWP_WPSF_Processor_BaseWpsf', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

abstract class ICWP_WPSF_Processor_BaseWpsf extends ICWP_WPSF_Processor_Base {

	/**
	 * @var array
	 */
	private $aAuditEntry;

	/**
	 * @var array
	 */
	private $aStatistics;

	/**
	 * Resets the object values to be re-used anew
	 */
	public function init() {
		parent::init();
		$oFO = $this->getFeature();
		add_filter( $oFO->prefix( 'collect_audit_trail' ), array( $this, 'audit_Collect' ) );
		add_filter( $oFO->prefix( 'collect_stats' ), array( $this, 'stats_Collect' ) );
		add_filter( $oFO->prefix( 'collect_tracking_data' ), array( $this, 'tracking_DataCollect' ) );
	}

	/**
	 * @return int
	 */
	protected function getInstallationDays() {
		$nTimeInstalled = $this->getFeature()->getPluginInstallationTime();
		if ( empty( $nTimeInstalled ) ) {
			return 0;
		}
		return (int)round( ( $this->loadDataProcessor()->time() - $nTimeInstalled )/DAY_IN_SECONDS );
	}

	/**
	 * @return bool
	 */
	protected function getRecaptchaTheme() {
		/** @var ICWP_WPSF_FeatureHandler_BaseWpsf $oFO */
		$oFO = $this->getFeature();
		return $this->isRecaptchaInvisible() ? 'light' : $oFO->getGoogleRecaptchaStyle();
	}

	/**
	 * @return string
	 */
	protected function getRecaptchaResponse() {
		return $this->loadDataProcessor()->FetchPost( 'g-recaptcha-response' );
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	protected function checkRequestRecaptcha() {
		/** @var ICWP_WPSF_FeatureHandler_BaseWpsf $oFO */
		$oFO = $this->getFeature();

		$sCaptchaResponse = $this->getRecaptchaResponse();

		if ( empty( $sCaptchaResponse ) ) {
			throw new Exception( _wpsf__( 'Whoops.' ).' '._wpsf__( 'Google reCAPTCHA was not submitted.' ), 1 );
		}
		else {
			$oResponse = $this->loadGoogleRecaptcha()
							  ->getGoogleRecaptchaLib( $oFO->getGoogleRecaptchaSecretKey() )
							  ->verify( $sCaptchaResponse, $this->ip() );
			if ( empty( $oResponse ) || !$oResponse->isSuccess() ) {
				throw new Exception( _wpsf__( 'Whoops.' ).' '._wpsf__( 'Google reCAPTCHA verification failed.' ), 2 );
			}
		}
		return true;
	}

	/**
	 * @return bool
	 */
	protected function isRecaptchaInvisible() {
		/** @var ICWP_WPSF_FeatureHandler_BaseWpsf $oFO */
		$oFO = $this->getFeature();
		return ( $oFO->getGoogleRecaptchaStyle() == 'invisible' );
	}

	public function registerGoogleRecaptchaJs() {
		$sJsUri = add_query_arg(
			array(
				'hl'     => $this->getGoogleRecaptchaLocale(),
				'onload' => 'onLoadIcwpRecaptchaCallback',
				'render' => 'explicit',
			),
			'https://www.google.com/recaptcha/api.js'
		);
		wp_register_script( 'google-recaptcha', $sJsUri, array( 'jquery' ) );
		wp_enqueue_script( 'google-recaptcha' );

		/**
		 * Change to recaptcha implementation now means
		 * 1 - the form will not submit unless the recaptcha has been executed (either invisible or manual)
		 */

		/** @var ICWP_WPSF_FeatureHandler_BaseWpsf $oFO */
		$oFO = $this->getFeature();
		echo $this->loadRenderer( $this->getController()->getPath_Templates() )
				  ->setTemplateEnginePhp()
				  ->setRenderVars(
					  array(
						  'sitekey' => $oFO->getGoogleRecaptchaSiteKey(),
						  'size'    => $this->isRecaptchaInvisible() ? 'invisible' : '',
						  'theme'   => $this->getRecaptchaTheme(),
						  'invis'   => $this->isRecaptchaInvisible(),
					  )
				  )
				  ->setTemplate( 'snippets/google_recaptcha_js' )
				  ->render();
	}

	/**
	 * Filter used to collect plugin data for tracking.  Fired from the plugin processor only if the option is enabled
	 * - it is not enabled by default.
	 * Note that in this case we "mask" options that have been identified as "sensitive" - i.e. could contain
	 * identifiable data.
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		if ( !is_array( $aData ) ) {
			$aData = array();
		}
		$oFO = $this->getFeature();
		$aData[ $oFO->getFeatureSlug() ] = array( 'options' => $oFO->collectOptionsForTracking() );
		return $aData;
	}

	/**
	 * A filter used to collect all the stats gathered in the plugin.
	 * @param array $aStats
	 * @return array
	 */
	public function stats_Collect( $aStats ) {
		if ( !is_array( $aStats ) ) {
			$aStats = array();
		}
		$aThisStats = $this->stats_Get();
		if ( !empty( $aThisStats ) && is_array( $aThisStats ) ) {
			$aStats[] = $aThisStats;
		}
		return $aStats;
	}

	/**
	 * @param string $sStatKey
	 */
	private function stats_Increment( $sStatKey ) {
		$aStats = $this->stats_Get();
		if ( !isset( $aStats[ $sStatKey ] ) ) {
			$aStats[ $sStatKey ] = 0;
		}
		$aStats[ $sStatKey ] = $aStats[ $sStatKey ] + 1;
		$this->aStatistics = $aStats;
	}

	/**
	 * @return array
	 */
	public function stats_Get() {
		if ( !isset( $this->aStatistics ) || !is_array( $this->aStatistics ) ) {
			$this->aStatistics = array();
		}
		return $this->aStatistics;
	}

	/**
	 * This is the preferred method over $this->stat_Increment() since it handles the parent stat key
	 * @param string $sStatKey
	 * @param string $sParentStatKey
	 */
	protected function doStatIncrement( $sStatKey, $sParentStatKey = '' ) {
		$this->stats_Increment( $sStatKey.':'.( empty( $sParentStatKey ) ? $this->getFeature()
																				->getFeatureSlug() : $sParentStatKey ) );
	}

	/**
	 * @param array $aAuditEntries
	 * @return array
	 */
	public function audit_Collect( $aAuditEntries ) {
		if ( !is_array( $aAuditEntries ) ) {
			$aAuditEntries = array();
		}
		if ( isset( $this->aAuditEntry ) && is_array( $this->aAuditEntry ) ) {
			$aAuditEntries[] = $this->aAuditEntry;
		}
		return $aAuditEntries;
	}

	/**
	 * @param string $sAdditionalMessage
	 * @param int    $nCategory
	 * @param string $sEvent
	 * @param string $sWpUsername
	 */
	protected function addToAuditEntry( $sAdditionalMessage = '', $nCategory = 1, $sEvent = '', $sWpUsername = '' ) {
		if ( !isset( $this->aAuditEntry ) ) {

			if ( empty( $sWpUsername ) ) {
				$oUser = $this->loadWpUsers()->getCurrentWpUser();
				if ( $this->loadWp()->isCron() ) {
					$sWpUsername = 'WP Cron';
				}
				else {
					$sWpUsername = empty( $oUser ) ? 'unidentified' : $oUser->get( 'user_login' );
				}
			}

			$this->aAuditEntry = array(
				'created_at'  => $this->time(),
				'wp_username' => $sWpUsername,
				'context'     => 'wpsf',
				'event'       => $sEvent,
				'category'    => $nCategory,
				'message'     => array()
			);
		}

		$this->aAuditEntry[ 'message' ][] = esc_sql( $sAdditionalMessage );

		if ( $nCategory > $this->aAuditEntry[ 'category' ] ) {
			$this->aAuditEntry[ 'category' ] = $nCategory;
		}
		if ( !empty( $sEvent ) ) {
			$this->aAuditEntry[ 'event' ] = $sEvent;
		}
	}

	/**
	 * @param string $sSeparator
	 * @return string
	 */
	protected function getAuditMessage( $sSeparator = ' ' ) {
		return implode( $sSeparator, $this->getRawAuditMessage() );
	}

	/**
	 * @param string $sLinePrefix
	 * @return array
	 */
	protected function getRawAuditMessage( $sLinePrefix = '' ) {
		if ( isset( $this->aAuditEntry[ 'message' ] ) && is_array( $this->aAuditEntry[ 'message' ] ) && !empty( $sLinePrefix ) ) {
			$aAuditMessages = array();
			foreach ( $this->aAuditEntry[ 'message' ] as $sMessage ) {
				$aAuditMessages[] = $sLinePrefix.$sMessage;
			}
			return $aAuditMessages;
		}
		return isset( $this->aAuditEntry[ 'message' ] ) ? $this->aAuditEntry[ 'message' ] : array();
	}
}