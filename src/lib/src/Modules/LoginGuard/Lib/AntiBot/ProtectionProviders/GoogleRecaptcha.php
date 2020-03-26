<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha\TestRequest;
use FernleafSystems\Wordpress\Services\Services;

class GoogleRecaptcha extends BaseProtectionProvider {

	const RECAPTCHA_JS_HANDLE = 'icwp-google-recaptcha';

	public function onWpEnqueueJs() {
		/** @var \ICWP_WPSF_FeatureHandler_BaseWpsf $oMod */
		$oMod = $this->getMod();
		$sJsUri = add_query_arg(
			[
				'hl'     => Services::WpGeneral()->getLocale( '-' ),
				'onload' => 'onLoadIcwpRecaptchaCallback',
				'render' => 'explicit',
			],
			'https://www.google.com/recaptcha/api.js'
		);
		wp_register_script( self::RECAPTCHA_JS_HANDLE, $sJsUri, [], false, true );
		wp_enqueue_script( self::RECAPTCHA_JS_HANDLE );

		// This also gives us the chance to remove recaptcha before it's printed, if it isn't needed
//		add_action( 'wp_footer', [ $this, 'maybeDequeueRecaptcha' ], -100 );
//		add_action( 'login_footer', [ $this, 'maybeDequeueRecaptcha' ], -100 );

		Services::Includes()
				->addIncludeAttribute( self::RECAPTCHA_JS_HANDLE, 'async', 'async' )
				->addIncludeAttribute( self::RECAPTCHA_JS_HANDLE, 'defer', 'defer' );
		/**
		 * Change to recaptcha implementation now means
		 * 1 - the form will not submit unless the recaptcha has been executed (either invisible or manual)
		 */
		$bInvisible = $this->isInvisible();
		echo $oMod->renderTemplate(
			'snippets/google_recaptcha_js',
			[
				'sitekey' => $oMod->getGoogleRecaptchaSiteKey(),
				'size'    => $bInvisible ? 'invisible' : '',
				'theme'   => $bInvisible ? 'light' : $oMod->getGoogleRecaptchaStyle(),
				'invis'   => $bInvisible,
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function performCheck( $oForm ) {
		if ( !$this->isFactorTested() ) {
			$this->setFactorTested( true );
			try {
				( new TestRequest() )
					->setMod( $this->getMod() )
					->test();
			}
			catch ( \Exception $oE ) {
				$this->processFailure();
				throw $oE;
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function buildFormInsert( $oFormProvider ) {
		return $this->getGoogleRecaptchaHtml();
	}

	/**
	 * @return string
	 */
	private function getGoogleRecaptchaHtml() {
		if ( $this->isInvisible() ) {
			$sExtraStyles = '';
		}
		else {
			$sExtraStyles = '<style>@media screen {#rc-imageselect, .icwpg-recaptcha iframe {transform:scale(0.90);-webkit-transform:scale(0.90);transform-origin:0 0;-webkit-transform-origin:0 0;}</style>';
		}
		return $sExtraStyles.'<div class="icwpg-recaptcha"></div>';
	}

	/**
	 * @return bool
	 */
	private function isInvisible() {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		return $oMod->getGoogleRecaptchaStyle() == 'invisible';
	}
}