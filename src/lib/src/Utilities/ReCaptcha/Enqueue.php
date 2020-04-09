<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

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

	public function onWpEnqueueJs() {
		/** @var \ICWP_WPSF_FeatureHandler_BaseWpsf $oMod */
		$oMod = $this->getMod();
		$oCFG = $oMod->getCaptchaCfg();

		$sJsUri = add_query_arg(
			[
				'hl'     => Services::WpGeneral()->getLocale( '-' ),
				'onload' => 'onLoadIcwpRecaptchaCallback',
				'render' => 'explicit',
			],
			$oCFG->url_api
		);
		wp_register_script( $oCFG->js_handle, $sJsUri, [], false, true );
		wp_enqueue_script( $oCFG->js_handle );

		Services::Includes()
				->addIncludeAttribute( $oCFG->js_handle, 'async', 'async' )
				->addIncludeAttribute( $oCFG->js_handle, 'defer', 'defer' );
		/**
		 * Change to recaptcha implementation now means
		 * 1 - the form will not submit unless the recaptcha has been executed (either invisible or manual)
		 */
		add_action( 'wp_footer', [ $this, 'maybeDequeueRecaptcha' ], -100 );
		add_action( 'login_footer', [ $this, 'maybeDequeueRecaptcha' ], -100 );
	}

	/**
	 * @return string
	 */
	public function getCaptchaHtml() {
		return '<div class="icwpg-recaptcha"></div>';
	}

	/**
	 * If recaptcha is required, it prints the necessary snippet and does not remove the enqueue
	 *
	 * @throws \Exception
	 */
	public function maybeDequeueRecaptcha() {
		/** @var \ICWP_WPSF_FeatureHandler_BaseWpsf $oMod */
		$oMod = $this->getMod();
		$oCFG = $oMod->getCaptchaCfg();

		if ( $this->bEnqueue ) {
			echo $oMod->renderTemplate(
				'snippets/google_recaptcha_js',
				[
					'sitekey' => $oCFG->key,
					'size'    => $oCFG->invisible ? 'invisible' : '',
					'theme'   => $oCFG->invisible ? 'light' : $oCFG->theme,
					'invis'   => $oCFG->invisible,
				]

			);
		}
		else {
			wp_dequeue_script( $oCFG->js_handle );
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