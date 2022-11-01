<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Legacy\RecaptchaJs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class Enqueue {

	use ModConsumer;

	/**
	 * @var bool
	 */
	private $bEnqueue;

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
		$mod = $this->getMod();
		$cfg = $mod->getCaptchaCfg();

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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$cfg = $mod->getCaptchaCfg();

		if ( $this->bEnqueue ) {
			$this->getCon()
				 ->getModule_Insights()
				 ->getActionRouter()
				 ->render( RecaptchaJs::SLUG, [
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
}