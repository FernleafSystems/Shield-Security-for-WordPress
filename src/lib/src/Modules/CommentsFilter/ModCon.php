<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha\CaptchaConfigVO;

class ModCon extends BaseShield\ModCon {

	public function getCaptchaCfg() :CaptchaConfigVO {
		$cfg = parent::getCaptchaCfg();
		$sStyle = $this->getOptions()->getOpt( 'google_recaptcha_style_comments' );
		if ( $sStyle !== 'default' && $this->isPremium() ) {
			$cfg->theme = $sStyle;
			$cfg->invisible = $cfg->theme == 'invisible';
		}
		return $cfg;
	}

	public function ensureCorrectCaptchaConfig() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$style = $opts->getOpt( 'google_recaptcha_style_comments' );
		if ( $this->isPremium() ) {
			$cfg = $this->getCaptchaCfg();
			if ( $cfg->provider == $cfg::PROV_GOOGLE_RECAP2 ) {
				if ( !$cfg->invisible && $style == 'invisible' ) {
					$opts->setOpt( 'google_recaptcha_style_comments', 'default' );
				}
			}
		}
		elseif ( !in_array( $style, [ 'disabled', 'default' ] ) ) {
			$opts->setOpt( 'google_recaptcha_style_comments', 'default' );
		}
	}

	public function getTextOptDefault( string $key ) :string {

		switch ( $key ) {
			case 'custom_message_checkbox':
				$text = __( "I'm not a spammer.", 'wp-simple-firewall' );
				break;
			case 'custom_message_alert':
				$text = __( "Please check the box to confirm you're not a spammer.", 'wp-simple-firewall' );
				break;
			case 'custom_message_comment_wait':
				$text = __( "Please wait %s seconds before posting your comment.", 'wp-simple-firewall' );
				break;
			case 'custom_message_comment_reload':
				$text = __( "Please reload this page to post a comment.", 'wp-simple-firewall' );
				break;
			default:
				$text = parent::getTextOptDefault( $key );
				break;
		}
		return $text;
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
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

		if ( $opts->isEnabledAntiBot() ) {
			$opts->setOpt( 'google_recaptcha_style_comments', 'disabled' );
			$opts->setOpt( 'enable_comments_gasp_protection', 'N' );
		}
	}

	public function isEnabledCaptcha() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $this->isModOptEnabled() && $opts->isEnabledCaptcha()
			   && $this->getCaptchaCfg()->ready;
	}

	public function setEnabledAntiBot( bool $enabled = true ) {
		$this->getOptions()->setOpt( 'enable_antibot_check', $enabled ? 'Y' : 'N' );
	}

	/**
	 * @return string
	 */
	public function getSpamBlacklistFile() {
		return $this->getCon()->getPluginCachePath( 'spamblacklist.txt' );
	}
}