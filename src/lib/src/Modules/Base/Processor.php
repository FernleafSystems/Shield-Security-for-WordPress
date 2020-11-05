<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

abstract class Processor {

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
	 * @param ModCon $mod
	 */
	public function __construct( $mod ) {
		$this->setMod( $mod );

		add_action( 'init', [ $this, 'onWpInit' ], 9 );
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		{ // Capture Logins
			add_action( 'wp_login', [ $this, 'onWpLogin' ], 10, 2 );
			if ( !Services::WpUsers()->isProfilePage() ) { // This can be fired during profile update.
				add_action( 'set_logged_in_cookie', [ $this, 'onWpSetLoggedInCookie' ], 5, 4 );
			}
		}
		add_action( $mod->prefix( 'plugin_shutdown' ), [ $this, 'onModuleShutdown' ] );
		add_action( $mod->prefix( 'daily_cron' ), [ $this, 'runDailyCron' ] );
		add_action( $mod->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
		add_action( $mod->prefix( 'deactivate_plugin' ), [ $this, 'deactivatePlugin' ] );

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
	 * @param string   $username
	 * @param \WP_User $user
	 */
	public function onWpLogin( $username, $user ) {
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

	public function deactivatePlugin() {
	}
}