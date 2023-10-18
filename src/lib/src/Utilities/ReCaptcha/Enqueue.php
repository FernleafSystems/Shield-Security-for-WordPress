<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha\CaptchaConfigVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

/**
 * @deprecated 18.5
 */
class Enqueue {

	use ModConsumer;

	/**
	 * @var bool
	 */
	private $bEnqueue;

	private $context = 'user';

	public function __construct() {
	}

	/**
	 * TODO: Consider how to move this to our standardised Enqueue system.
	 */
	public function onWpEnqueueJs() {
	}

	/**
	 * If recaptcha is required, it prints the necessary snippet and does not remove the enqueue
	 *
	 * @throws \Exception
	 */
	public function maybeDequeueRecaptcha() {
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