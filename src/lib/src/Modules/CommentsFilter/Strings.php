<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Strings extends Base\Strings {

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() :array {
		return [
			'spam_block_antibot'   => [ __( 'Blocked SPAM comment that failed AntiBot tests.', 'wp-simple-firewall' ) ],
			'spam_block_human'     => [
				__( 'Blocked human SPAM comment containing suspicious content.', 'wp-simple-firewall' ),
				__( 'Human SPAM filter found "%s" in "%s"', 'wp-simple-firewall' )
			],
			'spam_block_bot'       => [
				__( 'Blocked SPAM comment from Bot.', 'wp-simple-firewall' )
			],
			'spam_block_recaptcha' => [
				__( 'Blocked SPAM comment that failed reCAPTCHA.', 'wp-simple-firewall' )
			],
		];
	}

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_enable_plugin_feature_spam_comments_protection_filter' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), __( 'Comments SPAM Protection', 'wp-simple-firewall' ) );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The Comments Filter can block 100% of automated spam bots and also offer the option to analyse human-generated spam.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Comments Filter', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_bot_comment_spam_common' :
				$sTitleShort = __( 'Common Settings', 'wp-simple-firewall' );
				$sTitle = __( 'Common Settings For All SPAM Scanning', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Settings that apply to all comment SPAM scanning.', 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_bot_comment_spam_protection_filter' :
				$sTitle = sprintf( __( '%s Comment SPAM Protection', 'wp-simple-firewall' ), __( 'Automatic Bot', 'wp-simple-firewall' ) );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Blocks 100% of all automated bot-generated comment SPAM.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				$sTitleShort = __( 'Bot SPAM', 'wp-simple-firewall' );
				break;

			case 'section_human_spam_filter' :
				$sTitle = sprintf( __( '%s Comment SPAM Protection Filter', 'wp-simple-firewall' ), __( 'Human', 'wp-simple-firewall' ) );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Uses a 3rd party SPAM dictionary to detect human-based comment SPAM.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
					__( 'This tool, unlike other SPAM tools such as Akismet, will not send your comment data to 3rd party services for analysis.', 'wp-simple-firewall' )
				];
				$sTitleShort = __( 'Human SPAM', 'wp-simple-firewall' );
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$modName = $this->getMod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_comments_filter' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = __( 'Enable (or Disable) The Comment SPAM Protection Feature', 'wp-simple-firewall' );
				$desc = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), __( 'Comment SPAM Protection', 'wp-simple-firewall' ) );
				break;

			case 'trusted_commenter_minimum' :
				$name = __( 'Trusted Commenter Minimum', 'wp-simple-firewall' );
				$summary = __( 'Minimum Number Of Approved Comments Before Commenter Is Trusted', 'wp-simple-firewall' );
				$desc = __( 'Specify how many approved comments must exist before a commenter is trusted and their comments are no longer scanned.', 'wp-simple-firewall' )
						.'<br />'.__( 'Normally WordPress will trust after 1 comment.', 'wp-simple-firewall' );
				break;

			case 'trusted_user_roles' :
				$name = __( 'Trusted User Roles', 'wp-simple-firewall' );
				$summary = __( "Comments From Users With These Roles Will Never Be Scanned", 'wp-simple-firewall' );
				$desc = __( "Shield doesn't normally scan comments from logged-in or registered users.", 'wp-simple-firewall' )
						.'<br />'.__( "Specify user roles here that shouldn't be scanned.", 'wp-simple-firewall' )
						.'<br/>'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Take a new line for each user role.', 'wp-simple-firewall' ) )
						.'<br/>'.sprintf( '%s: %s', __( 'Available Roles', 'wp-simple-firewall' ), implode( ', ', Services::WpUsers()
																														  ->getAvailableUserRoles() ) );
				break;

			case 'enable_antibot_check' :
				$name = __( 'AntiBot Detection Engine', 'wp-simple-firewall' );
				$summary = __( "Use AntiBot Detection Engine To Detect SPAM Bots", 'wp-simple-firewall' );
				$desc = [
					sprintf( __( "AntiBot Detection Engine is %s's exclusive bot-detection technology that removes the needs for CAPTCHA and other challenges.", 'wp-simple-firewall' ),
						$this->getCon()->getHumanName() ),
					__( 'This feature is designed to replace the CAPTCHA and Bot Protection options.', 'wp-simple-firewall' ),
					sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ),
						__( "Switching on this feature will disable the CAPTCHA and Bot Protection settings for the selected forms.", 'wp-simple-firewall' ) )
				];
				break;

			case 'enable_comments_human_spam_filter' :
				$name = __( 'Human SPAM Filter', 'wp-simple-firewall' );
				$summary = sprintf( __( 'Enable (or Disable) The %s Feature', 'wp-simple-firewall' ), __( 'Human SPAM Filter', 'wp-simple-firewall' ) );
				$desc = [
					__( 'Most SPAM is automatic, by bots, but sometimes Humans also post comments to your site and these bypass Bot Detection rules.', 'wp-simple-firewall' ),
					__( 'When this happens, you can scan the content for keywords that are typical of SPAM.', 'wp-simple-firewall' ),
				];
				break;

			case 'comments_default_action_human_spam' :
				$name = __( 'SPAM Action', 'wp-simple-firewall' );
				$summary = __( 'How To Categorise Comments When Identified To Be SPAM', 'wp-simple-firewall' );
				$desc = sprintf( __( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.', 'wp-simple-firewall' ), '<span style"text-decoration:underline;">'.__( 'a human commenter', 'wp-simple-firewall' ).'</span>' );
				break;

			case 'enable_comments_gasp_protection' :
				$name = __( 'SPAM Bot Protection', 'wp-simple-firewall' );
				$summary = __( 'Block 100% Comment SPAM From Automated Bots', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Use the newer AntiBot Detection Engine to detect SPAM instead of CAPTCHAs.", 'wp-simple-firewall' ) ),

					__( 'Highly effective detection for the most common types of comment SPAM.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Bonus', 'wp-simple-firewall' ), __( "Unlike Akismet, your data is never sent off-site to 3rd party processing servers.", 'wp-simple-firewall' ) )
				];
				break;

			case 'comments_default_action_spam_bot' :
				$name = __( 'SPAM Action', 'wp-simple-firewall' );
				$summary = __( 'Where To Put SPAM Comments', 'wp-simple-firewall' );
				$desc = sprintf( __( 'When a comment is detected as being SPAM, %s will put the comment in the specified folder.', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName() );
				break;

			case 'custom_message_checkbox' :
				$name = __( 'GASP Checkbox Message', 'wp-simple-firewall' );
				$summary = __( 'If you want a custom checkbox message, please provide this here', 'wp-simple-firewall' );
				$desc = __( "You can customise the message beside the checkbox.", 'wp-simple-firewall' )
						.'<br />'.sprintf( __( 'Default Message: %s', 'wp-simple-firewall' ), __( "Please check the box to confirm you're not a spammer", 'wp-simple-firewall' ) );
				break;

			case 'google_recaptcha_style_comments' :
				$name = __( 'CAPTCHA', 'wp-simple-firewall' );
				$summary = __( 'Enable CAPTCHA To Protect Against SPAM Comments', 'wp-simple-firewall' );
				$desc = [
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ),
						__( "Use the newer AntiBot Detection Engine to detect SPAM instead of CAPTCHAs.", 'wp-simple-firewall' ) ),
					__( 'You can choose the CAPTCHA display format that best suits your site, including the newer Invisible CAPTCHA, when you upgrade to PRO.', 'wp-simple-firewall' )
				];
				if ( !$mod->getCaptchaCfg()->ready ) {
					$desc[] = sprintf( '<a href="%s">%s</a>',
						$this->getCon()
							 ->getModule_Plugin()
							 ->getUrl_DirectLinkToSection( 'section_third_party_captcha' ),
						__( 'Please remember to provide your CAPTCHA keys.', 'wp-simple-firewall' )
					);
				}
				break;

			case 'custom_message_alert' :
				$name = __( 'GASP Alert Message', 'wp-simple-firewall' );
				$summary = __( 'If you want a custom alert message, please provide this here', 'wp-simple-firewall' );
				$desc = __( "This alert message is displayed when a visitor attempts to submit a comment without checking the box.", 'wp-simple-firewall' )
						.'<br />'.sprintf( __( 'Default Message: %s', 'wp-simple-firewall' ), __( "Please check the box to confirm you're not a spammer", 'wp-simple-firewall' ) );
				break;

			case 'custom_message_comment_wait' :
				$name = __( 'GASP Wait Message', 'wp-simple-firewall' );
				$summary = __( 'If you want a custom submit-button wait message, please provide this here.', 'wp-simple-firewall' );
				$desc = __( "Where you see the '%s' this will be the number of seconds. You must ensure you include 1, and only 1, of these.", 'wp-simple-firewall' )
						.'<br />'.sprintf( __( 'Default Message: %s', 'wp-simple-firewall' ), __( 'Please wait %s seconds before posting your comment', 'wp-simple-firewall' ) );
				break;

			case 'custom_message_comment_reload' :
				$name = __( 'GASP Reload Message', 'wp-simple-firewall' );
				$summary = __( 'If you want a custom message when the comment token has expired, please provide this here.', 'wp-simple-firewall' );
				$desc = __( 'This message is displayed on the submit-button when the comment token is expired', 'wp-simple-firewall' )
						.'<br />'.sprintf( __( 'Default Message: %s', 'wp-simple-firewall' ), __( "Please reload this page to post a comment", 'wp-simple-firewall' ) );
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $name,
			'summary'     => $summary,
			'description' => $desc,
		];
	}
}