<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Deprecated;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class BaseProcessor {

	use Modules\ModConsumer;

	/**
	 * @var BaseProcessor[]
	 */
	protected $aSubPros;

	/**
	 * @var bool
	 */
	private $bLoginCaptured;

	/**
	 * @var bool
	 */
	private $bHasExecuted;

	/**
	 * @param BaseModCon $oMod
	 */
	public function __construct( $oMod ) {
		$this->setMod( $oMod );

		add_action( 'init', [ $this, 'onWpInit' ], 9 );
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		{ // Capture Logins
			add_action( 'wp_login', [ $this, 'onWpLogin' ], 10, 2 );
			if ( !Services::WpUsers()->isProfilePage() ) { // This can be fired during profile update.
				add_action( 'set_logged_in_cookie', [ $this, 'onWpSetLoggedInCookie' ], 5, 4 );
			}
		}
		add_action( $oMod->prefix( 'plugin_shutdown' ), [ $this, 'onModuleShutdown' ] );
		add_action( $oMod->prefix( 'daily_cron' ), [ $this, 'runDailyCron' ] );
		add_action( $oMod->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
		add_action( $oMod->prefix( 'deactivate_plugin' ), [ $this, 'deactivatePlugin' ] );

		/**
		 * 2019-04-19:
		 * wp_service_worker: added to prevent infinite page reloads triggered by an error with the PWA plugin.
		 * It seems that using wp_localize_script() on a request with wp_service_worker=1 causes the worker
		 * reload the page. Why exactly this happens hasn't been investigated, so we just skip any FRONTend
		 * enqueues that might call wp_localize_script() for these requests.
		 */
		if ( Services::Request()->query( 'wp_service_worker', 0 ) != 1 ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'onWpEnqueueJs' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'onWpEnqueueJs' ] );
		}

		$this->bHasExecuted = false;
		$this->init();
	}

	public function onWpInit() {
	}

	public function onWpLoaded() {
	}

	public function onWpEnqueueJs() {
	}

	/**
	 * @param string   $sUsername
	 * @param \WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser ) {
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpSetLoggedInCookie( $sCookie, $nExpire, $nExpiration, $nUserId ) {
	}

	/**
	 * @return bool
	 */
	protected function isLoginCaptured() {
		return (bool)$this->bLoginCaptured;
	}

	public function runDailyCron() {
	}

	public function runHourlyCron() {
	}

	/**
	 * @return $this
	 */
	protected function setLoginCaptured() {
		$this->bLoginCaptured = true;
		return $this;
	}

	public function onModuleShutdown() {
	}

	/**
	 */
	public function init() {
	}

	/**
	 * @return bool
	 */
	public function isReadyToRun() {
		return true;
	}

	/**
	 * @return $this
	 */
	public function execute() {
		if ( !$this->bHasExecuted ) {
			$this->run();
			$this->bHasExecuted;
		}
		return $this;
	}

	/**
	 * Override to set what this processor does when it's "run"
	 */
	public function run() {
	}

	/**
	 * We don't handle locale derivatives (yet)
	 * @return string
	 */
	protected function getGoogleRecaptchaLocale() {
		return Services::WpGeneral()->getLocale( '-' );
	}

	/**
	 * @return \ICWP_WPSF_Processor_Email
	 */
	public function getEmailProcessor() {
		return $this->getMod()->getEmailProcessor();
	}

	/**
	 * @param string $sKey
	 * @return BaseProcessor|mixed|null
	 */
	protected function getSubPro( $sKey ) {
		$aProcessors = $this->getSubProcessors();
		if ( !isset( $aProcessors[ $sKey ] ) ) {
			$aMap = $this->getSubProMap();
			if ( !isset( $aMap[ $sKey ] ) ) {
				error_log( 'Sub processor key not set: '.$sKey );
			}
			$aProcessors[ $sKey ] = new $aMap[ $sKey ]( $this->getMod() );
		}
		return $aProcessors[ $sKey ];
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [];
	}

	/**
	 */
	public function deactivatePlugin() {
	}

	/**
	 * @return BaseProcessor[]
	 */
	protected function getSubProcessors() {
		if ( !isset( $this->aSubPros ) ) {
			$this->aSubPros = [];
		}
		return $this->aSubPros;
	}

	/**
	 * Will prefix and return any string with the unique plugin prefix.
	 * @param string $sSuffix
	 * @param string $sGlue
	 * @return string
	 * @deprecated
	 */
	protected function prefix( $sSuffix = '', $sGlue = '-' ) {
		return $this->getMod()->prefix( $sSuffix, $sGlue );
	}
}