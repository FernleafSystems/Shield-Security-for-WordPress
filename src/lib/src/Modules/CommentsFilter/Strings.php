<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Strings extends Base\Strings {

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [
			'spam_block_human' => [
				__( 'Blocked human SPAM comment containing suspicious content.', 'wp-simple-firewall' )
			],
			'spam_block_checkbox' => [
				__( 'Blocked SPAM comment with missing Bot Checkbox.', 'wp-simple-firewall' )
			],
			'spam_block_honeypot' => [
				__( 'Blocked SPAM comment with honeypot capture.', 'wp-simple-firewall' )
			],
			'spam_block_token'    => [
				__( 'Blocked SPAM comment with missing token.', 'wp-simple-firewall' )
			],
			'spam_block_recaptcha'    => [
				__( 'Blocked SPAM comment that failed reCAPTCHA.', 'wp-simple-firewall' )
			],
		];
	}

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( $sSectionSlug ) {

		switch ( $sSectionSlug ) {

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

			case 'section_recaptcha' :
				$sTitle = 'Google reCAPTCHA';
				$sTitleShort = 'reCAPTCHA';
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Adds Google reCAPTCHA to the Comment Forms.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Keep this turned on.', 'wp-simple-firewall' ) ),
				];
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
				return parent::getSectionStrings( $sSectionSlug );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $sOptKey
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( $sOptKey ) {
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $sOptKey ) {

			case 'enable_comments_filter' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$sSummary = __( 'Enable (or Disable) The Comment SPAM Protection Feature', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), __( 'Comment SPAM Protection', 'wp-simple-firewall' ) );
				break;

			case 'trusted_commenter_minimum' :
				$sName = __( 'Trusted Commenter Minimum', 'wp-simple-firewall' );
				$sSummary = __( 'Minimum Number Of Approved Comments Before Commenter Is Trusted', 'wp-simple-firewall' );
				$sDescription = __( 'Specify how many approved comments must exist before a commenter is trusted and their comments are no longer scanned.', 'wp-simple-firewall' )
								.'<br />'.__( 'Normally WordPress will trust after 1 comment.', 'wp-simple-firewall' );
				break;

			case 'trusted_user_roles' :
				$sName = __( 'Trusted Users', 'wp-simple-firewall' );
				$sSummary = __( "Comments By Users With The Following Roles Will Never Be Scanned", 'wp-simple-firewall' );
				$sDescription = __( "Shield doesn't normally scan comments from logged-in or registered users.", 'wp-simple-firewall' )
								.'<br />'.__( "Specify user roles here that shouldn't be scanned.", 'wp-simple-firewall' )
								.'<br/>'.sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Take a new line for each user role.', 'wp-simple-firewall' ) )
								.'<br/>'.sprintf( '%s: %s', __( 'Available Roles', 'wp-simple-firewall' ), implode( ', ', Services::WpUsers()
																																  ->getAvailableUserRoles() ) );
				break;

			case 'enable_comments_human_spam_filter' :
				$sName = __( 'Human SPAM Filter', 'wp-simple-firewall' );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Feature', 'wp-simple-firewall' ), __( 'Human SPAM Filter', 'wp-simple-firewall' ) );
				$sDescription = __( 'Scans the content of WordPress comments for keywords that are indicative of SPAM and marks the comment according to your preferred setting below.', 'wp-simple-firewall' );
				break;

			case 'enable_comments_human_spam_filter_items' :
				$sName = __( 'Comment Filter Items', 'wp-simple-firewall' );
				$sSummary = __( 'Select The Items To Scan For SPAM', 'wp-simple-firewall' );
				$sDescription = __( 'When a user submits a comment, only the selected parts of the comment data will be scanned for SPAM content.', 'wp-simple-firewall' ).' '.sprintf( __( 'Recommended: %s', 'wp-simple-firewall' ), __( 'All', 'wp-simple-firewall' ) );
				break;

			case 'comments_default_action_human_spam' :
				$sName = __( 'Default SPAM Action', 'wp-simple-firewall' );
				$sSummary = __( 'How To Categorise Comments When Identified To Be SPAM', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.', 'wp-simple-firewall' ), '<span style"text-decoration:underline;">'.__( 'a human commenter', 'wp-simple-firewall' ).'</span>' );
				break;

			case 'enable_comments_gasp_protection' :
				$sName = __( 'SPAM Bot Protection', 'wp-simple-firewall' );
				$sSummary = __( 'Block 100% Comment SPAM From Automated Bots', 'wp-simple-firewall' );
				$sDescription = __( 'Highly effective detection for the most common types of comment SPAM.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( '%s: %s', __( 'Bonus', 'wp-simple-firewall' ), __( "Unlike Akismet, your data is never sent off-site to 3rd party processing servers.", 'wp-simple-firewall' ) );
				break;

			case 'comments_default_action_spam_bot' :
				$sName = __( 'Default SPAM Action', 'wp-simple-firewall' );
				$sSummary = __( 'How To Categorise Comments When Identified To Be SPAM', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.', 'wp-simple-firewall' ), '<span style"text-decoration:underline;">'.__( 'an automatic bot', 'wp-simple-firewall' ).'</span>' );
				break;

			case 'comments_cooldown_interval' :
				$sName = __( 'Comments Cooldown', 'wp-simple-firewall' );
				$sSummary = __( 'Limit posting comments to X seconds after the page has loaded', 'wp-simple-firewall' );
				$sDescription = __( "By forcing a comments cooldown period, you restrict a Spambot's ability to post multiple times to your posts.", 'wp-simple-firewall' );
				break;

			case 'comments_token_expire_interval' :
				$sName = __( 'Comment Token Expire', 'wp-simple-firewall' );
				$sSummary = __( 'A visitor has X seconds within which to post a comment', 'wp-simple-firewall' );
				$sDescription = __( "Default: 600 seconds (10 minutes). Each visitor is given a unique 'Token' so they can comment. This restricts spambots, but we need to force these tokens to expire and at the same time not bother the visitors.", 'wp-simple-firewall' );
				break;

			case 'custom_message_checkbox' :
				$sName = __( 'GASP Checkbox Message', 'wp-simple-firewall' );
				$sSummary = __( 'If you want a custom checkbox message, please provide this here', 'wp-simple-firewall' );
				$sDescription = __( "You can customise the message beside the checkbox.", 'wp-simple-firewall' )
								.'<br />'.sprintf( __( 'Default Message: %s', 'wp-simple-firewall' ), __( "Please check the box to confirm you're not a spammer", 'wp-simple-firewall' ) );
				break;

			case 'enable_google_recaptcha_comments' :
				$sName = 'Google reCAPTCHA';
				$sSummary = __( 'Enable Google reCAPTCHA For Comments', 'wp-simple-firewall' );
				$sDescription = __( 'Use Google reCAPTCHA on the comments form to prevent bot-spam comments.', 'wp-simple-firewall' );
				break;

			case 'google_recaptcha_style_comments' :
				$sName = __( 'reCAPTCHA Style', 'wp-simple-firewall' );
				$sSummary = __( 'How Google reCAPTCHA Will Be Displayed', 'wp-simple-firewall' );
				$sDescription = __( 'You can choose the reCAPTCHA display format that best suits your site, including the new Invisible Recaptcha', 'wp-simple-firewall' );
				break;

			case 'custom_message_alert' :
				$sName = __( 'GASP Alert Message', 'wp-simple-firewall' );
				$sSummary = __( 'If you want a custom alert message, please provide this here', 'wp-simple-firewall' );
				$sDescription = __( "This alert message is displayed when a visitor attempts to submit a comment without checking the box.", 'wp-simple-firewall' )
								.'<br />'.sprintf( __( 'Default Message: %s', 'wp-simple-firewall' ), __( "Please check the box to confirm you're not a spammer", 'wp-simple-firewall' ) );
				break;

			case 'custom_message_comment_wait' :
				$sName = __( 'GASP Wait Message', 'wp-simple-firewall' );
				$sSummary = __( 'If you want a custom submit-button wait message, please provide this here.', 'wp-simple-firewall' );
				$sDescription = __( "Where you see the '%s' this will be the number of seconds. You must ensure you include 1, and only 1, of these.", 'wp-simple-firewall' )
								.'<br />'.sprintf( __( 'Default Message: %s', 'wp-simple-firewall' ), __( 'Please wait %s seconds before posting your comment', 'wp-simple-firewall' ) );
				break;

			case 'custom_message_comment_reload' :
				$sName = __( 'GASP Reload Message', 'wp-simple-firewall' );
				$sSummary = __( 'If you want a custom message when the comment token has expired, please provide this here.', 'wp-simple-firewall' );
				$sDescription = __( 'This message is displayed on the submit-button when the comment token is expired', 'wp-simple-firewall' )
								.'<br />'.sprintf( __( 'Default Message: %s', 'wp-simple-firewall' ), __( "Please reload this page to post a comment", 'wp-simple-firewall' ) );
				break;

			default:
				return parent::getOptionStrings( $sOptKey );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}
}