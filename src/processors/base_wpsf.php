<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

abstract class ICWP_WPSF_Processor_BaseWpsf extends ICWP_WPSF_Processor_Base {

	const RECAPTCHA_JS_HANDLE = 'icwp-google-recaptcha';

	/**
	 * @var array
	 */
	private $aStatistics;

	/**
	 * @var bool
	 */
	private static $bRecaptchaEnqueue = false;

	/**
	 * @var bool
	 */
	private $bLogRequest;

	/**
	 * Resets the object values to be re-used anew
	 */
	public function init() {
		parent::init();
		$oFO = $this->getMod();
		add_filter( $oFO->prefix( 'collect_tracking_data' ), [ $this, 'tracking_DataCollect' ] );
	}

	/**
	 * @return int
	 */
	protected function getInstallationDays() {
		$nTimeInstalled = $this->getCon()
							   ->loadCorePluginFeatureHandler()
							   ->getInstallDate();
		if ( empty( $nTimeInstalled ) ) {
			return 0;
		}
		return (int)round( ( $this->loadRequest()->ts() - $nTimeInstalled )/DAY_IN_SECONDS );
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function isUserSubjectToLoginIntent( $oUser = null ) {
		$bIsSubject = false;

		if ( !$oUser instanceof WP_User ) {
			$oUser = Services::WpUsers()->getCurrentWpUser();
		}
		if ( $oUser instanceof WP_User ) {
			$bIsSubject = apply_filters( $this->getCon()->prefix( 'user_subject_to_login_intent' ), false, $oUser );
		}

		return $bIsSubject;
	}

	/**
	 * @return bool
	 */
	protected function getRecaptchaTheme() {
		/** @var \ICWP_WPSF_FeatureHandler_BaseWpsf $oFO */
		$oFO = $this->getMod();
		return $this->isRecaptchaInvisible() ? 'light' : $oFO->getGoogleRecaptchaStyle();
	}

	/**
	 * @return bool
	 */
	protected function getIfLogRequest() {
		return isset( $this->bLogRequest ) ? (bool)$this->bLogRequest : !Services::WpGeneral()->isCron();
	}

	/**
	 * @param bool $bLog
	 * @return $this
	 */
	protected function setIfLogRequest( $bLog ) {
		$this->bLogRequest = $bLog;
		return $this;
	}

	/**
	 * @return bool
	 */
	protected function isRecaptchaInvisible() {
		/** @var \ICWP_WPSF_FeatureHandler_BaseWpsf $oFO */
		$oFO = $this->getMod();
		return ( $oFO->getGoogleRecaptchaStyle() == 'invisible' );
	}

	public function registerGoogleRecaptchaJs() {
		$sJsUri = add_query_arg(
			[
				'hl'     => $this->getGoogleRecaptchaLocale(),
				'onload' => 'onLoadIcwpRecaptchaCallback',
				'render' => 'explicit',
			],
			'https://www.google.com/recaptcha/api.js'
		);
		wp_register_script( self::RECAPTCHA_JS_HANDLE, $sJsUri, [], false, true );
		wp_enqueue_script( self::RECAPTCHA_JS_HANDLE );

		// This also gives us the chance to remove recaptcha before it's printed, if it isn't needed
		add_action( 'wp_footer', [ $this, 'maybeDequeueRecaptcha' ], -100 );
		add_action( 'login_footer', [ $this, 'maybeDequeueRecaptcha' ], -100 );

		Services::Includes()
				->addIncludeAttribute( self::RECAPTCHA_JS_HANDLE, 'async', 'async' )
				->addIncludeAttribute( self::RECAPTCHA_JS_HANDLE, 'defer', 'defer' );
		/**
		 * Change to recaptcha implementation now means
		 * 1 - the form will not submit unless the recaptcha has been executed (either invisible or manual)
		 */
	}

	/**
	 * @return array
	 */
	public function stats_Get() {
		if ( !isset( $this->aStatistics ) || !is_array( $this->aStatistics ) ) {
			$this->aStatistics = [];
		}
		return $this->aStatistics;
	}

	/**
	 * Filter used to collect plugin data for tracking.  Fired from the plugin processor only if the option is enabled
	 * - it is not enabled by default.
	 * Note that in this case we "mask" options that have been identified as "sensitive" - i.e. could contain
	 * identifiable data.
	 *
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		if ( !is_array( $aData ) ) {
			$aData = [];
		}
		$oFO = $this->getMod();
		$aData[ $oFO->getSlug() ] = [ 'options' => $oFO->collectOptionsForTracking() ];
		return $aData;
	}

	/**
	 * If recaptcha is required, it prints the necessary snippet and does not remove the enqueue
	 *
	 * @throws \Exception
	 */
	public function maybeDequeueRecaptcha() {

		if ( $this->isRecaptchaEnqueue() ) {
			/** @var ICWP_WPSF_FeatureHandler_BaseWpsf $oFO */
			$oFO = $this->getMod();
			echo $oFO->renderTemplate(
				'snippets/google_recaptcha_js',
				[
					'sitekey' => $oFO->getGoogleRecaptchaSiteKey(),
					'size'    => $this->isRecaptchaInvisible() ? 'invisible' : '',
					'theme'   => $this->getRecaptchaTheme(),
					'invis'   => $this->isRecaptchaInvisible(),
				]

			);
		}
		else {
			wp_dequeue_script( self::RECAPTCHA_JS_HANDLE );
		}
	}

	/**
	 * @return bool
	 */
	public function isRecaptchaEnqueue() {
		return self::$bRecaptchaEnqueue;
	}

	/**
	 * Note we don't provide a 'false' option here as if it's set to be needed somewhere,
	 * it shouldn't be unset anywhere else.
	 *
	 * @return $this
	 */
	public function setRecaptchaToEnqueue() {
		self::$bRecaptchaEnqueue = true;
		return $this;
	}
}