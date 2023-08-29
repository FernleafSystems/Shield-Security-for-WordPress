<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Legacy\RecaptchaJs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha\CaptchaConfigVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * @deprecated TODO: remove all of this crap
 */
class Enqueue {

	use ModConsumer;

	/**
	 * @var bool
	 */
	private $bEnqueue;

	private $context = 'user';

	public function __construct() {
		$this->bEnqueue = false;
		if ( Services::Request()->query( 'wp_service_worker', 0 ) != 1 ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'onWpEnqueueJs' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'onWpEnqueueJs' ] );
		}
	}

	/**
	 * TODO: Consider how to move this to our standardised Enqueue system.
	 */
	public function onWpEnqueueJs() {
		$cfg = $this->cfg();

		$uriJS = URL::Build( $cfg->url_api, [
			'hl'     => Services::WpGeneral()->getLocale( '-' ),
			'onload' => 'onLoadIcwpRecaptchaCallback',
			'render' => 'explicit',
		] );
		wp_register_script( $cfg->js_handle, $uriJS, [], false, true );
		wp_enqueue_script( $cfg->js_handle );

		Services::Includes()
				->addIncludeAttribute( $cfg->js_handle, 'async', 'async' )
				->addIncludeAttribute( $cfg->js_handle, 'defer', 'defer' );
		/**
		 * Change to recaptcha implementation now means
		 * 1 - the form will not submit unless the recaptcha has been executed (either invisible or manual)
		 */
		add_action( 'wp_footer', [ $this, 'maybeDequeueRecaptcha' ], -100 );
		add_action( 'login_footer', [ $this, 'maybeDequeueRecaptcha' ], -100 );
	}

	public function getCaptchaHtml() :string {
		return '<div class="icwpg-recaptcha"></div>';
	}

	/**
	 * If recaptcha is required, it prints the necessary snippet and does not remove the enqueue
	 *
	 * @throws \Exception
	 */
	public function maybeDequeueRecaptcha() {
		$cfg = $this->cfg();

		if ( $this->bEnqueue ) {
			echo self::con()->action_router->render( RecaptchaJs::SLUG, [
				'sitekey' => $cfg->key,
				'size'    => $cfg->invisible ? 'invisible' : '',
				'theme'   => $cfg->theme,
				'invis'   => $cfg->invisible,
			] );
		}
		else {
			wp_dequeue_script( $cfg->js_handle );
		}
	}

	/**
	 * @return $this
	 */
	public function setToEnqueue() {
		$this->bEnqueue = true;
		return $this;
	}

	private function cfg() :CaptchaConfigVO {
		return ( $this->context === 'user' ?
			self::con()->getModule_LoginGuard() : self::con()->getModule_Plugin() )->getCaptchaCfg();
	}
}