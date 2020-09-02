<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

class ICWP_WPSF_FeatureHandler_CommentsFilter extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return Shield\Modules\Plugin\Lib\Captcha\CaptchaConfigVO
	 */
	public function getCaptchaCfg() {
		$oCfg = parent::getCaptchaCfg();
		$sStyle = $this->getOpt( 'google_recaptcha_style_comments' );
		if ( $sStyle !== 'default' && $this->isPremium() ) {
			$oCfg->theme = $sStyle;
			$oCfg->invisible = $oCfg->theme == 'invisible';
		}
		return $oCfg;
	}

	public function ensureCorrectCaptchaConfig() {
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();

		$sStyle = $opts->getOpt( 'google_recaptcha_style_comments' );
		if ( $this->isPremium() ) {
			$oCfg = $this->getCaptchaCfg();
			if ( $oCfg->provider == $oCfg::PROV_GOOGLE_RECAP2 ) {
				if ( !$oCfg->invisible && $sStyle == 'invisible' ) {
					$opts->setOpt( 'google_recaptcha_style_comments', 'default' );
				}
			}
		}
		elseif ( !in_array( $sStyle, [ 'disabled', 'default' ] ) ) {
			$opts->setOpt( 'google_recaptcha_style_comments', 'default' );
		}
	}

	/**
	 * @param string $sOptKey
	 * @return string
	 */
	public function getTextOptDefault( $sOptKey ) {

		switch ( $sOptKey ) {
			case 'custom_message_checkbox':
				$sText = __( "I'm not a spammer.", 'wp-simple-firewall' );
				break;
			case 'custom_message_alert':
				$sText = __( "Please check the box to confirm you're not a spammer.", 'wp-simple-firewall' );
				break;
			case 'custom_message_comment_wait':
				$sText = __( "Please wait %s seconds before posting your comment.", 'wp-simple-firewall' );
				break;
			case 'custom_message_comment_reload':
				$sText = __( "Please reload this page to post a comment.", 'wp-simple-firewall' );
				break;
			default:
				$sText = parent::getTextOptDefault( $sOptKey );
				break;
		}
		return $sText;
	}

	protected function preProcessOptions() {
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();

		// clean roles
		$opts->setOpt( 'trusted_user_roles',
			array_unique( array_filter( array_map(
				function ( $sRole ) {
					return sanitize_key( strtolower( $sRole ) );
				},
				$opts->getTrustedRoles()
			) ) )
		);

		$this->ensureCorrectCaptchaConfig();
	}

	/**
	 * @return bool
	 */
	public function isEnabledCaptcha() {
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();
		return $this->isModOptEnabled() && !$opts->isEnabledCaptcha()
			   && $this->getCaptchaCfg()->ready;
	}

	/**
	 * @param bool $bEnabled
	 * @return $this
	 */
	public function setEnabledGasp( $bEnabled = true ) {
		return $this->setOpt( 'enable_comments_gasp_protection', $bEnabled ? 'Y' : 'N' );
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'CommentsFilter';
	}

	/**
	 * @return string
	 */
	public function getSpamBlacklistFile() {
		return $this->getCon()->getPluginCachePath( 'spamblacklist.txt' );
	}
}