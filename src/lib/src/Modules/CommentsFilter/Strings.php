<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Strings extends Base\Strings {

	public function getEventStrings() :array {
		return [
			'spam_block_antibot'       => [
				'name'  => sprintf( '%s: %s',
					__( 'SPAM Blocked', 'wp-simple-firewall' ),
					__( 'AntiBot System', 'wp-simple-firewall' )
				),
				'audit' => [
					__( 'Blocked SPAM comment that failed AntiBot tests.', 'wp-simple-firewall' )
				],
			],
			'spam_block_human'         => [
				'name'  => sprintf( '%s: %s',
					__( 'SPAM Blocked', 'wp-simple-firewall' ),
					__( 'Human', 'wp-simple-firewall' )
				),
				'audit' => [
					__( 'Blocked human SPAM comment containing suspicious content.', 'wp-simple-firewall' ),
					__( 'Human SPAM filter found "{{word}}" in "{{key}}"', 'wp-simple-firewall' ),
				],
			],
			'spam_block_humanrepeated' => [
				'name'  => sprintf( '%s: %s',
					__( 'SPAM Blocked', 'wp-simple-firewall' ),
					__( 'Repeated Human SPAM', 'wp-simple-firewall' )
				),
				'audit' => [
					__( 'Blocked repeated attempts by the same visitor to post multiple SPAM comments.', 'wp-simple-firewall' ),
				],
			],
			'spam_block_bot'           => [
				'name'  => sprintf( '%s: %s',
					__( 'SPAM Blocked', 'wp-simple-firewall' ),
					__( 'Bot', 'wp-simple-firewall' )
				),
				'audit' => [
					__( 'Blocked SPAM comment from Bot.', 'wp-simple-firewall' ),
				],
			],
			'spam_block_recaptcha'     => [
				'name'  => sprintf( '%s: %s',
					__( 'SPAM Blocked', 'wp-simple-firewall' ),
					__( 'CAPTCHA', 'wp-simple-firewall' )
				),
				'audit' => [
					__( 'Blocked SPAM comment that failed reCAPTCHA.', 'wp-simple-firewall' ),
				],
			],
			'comment_spam_block'       => [
				'name'  => __( 'Comment SPAM Blocked.', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Comment SPAM Blocked.', 'wp-simple-firewall' ),
				],
			],
		];
	}

	public function getSectionStrings( string $section ) :array {

		switch ( $section ) {

			case 'section_enable_plugin_feature_spam_comments_protection_filter' :
				$titleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), __( 'Comments SPAM Protection', 'wp-simple-firewall' ) );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'The Comments Filter can block 100% of automated spam bots and also offer the option to analyse human-generated spam.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Comments Filter', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_bot_comment_spam_common' :
				$titleShort = __( 'Common Settings', 'wp-simple-firewall' );
				$title = __( 'Common Settings For All SPAM Scanning', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Settings that apply to all comment SPAM scanning.', 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_bot_comment_spam_protection_filter' :
				$title = sprintf( __( '%s Comment SPAM Protection', 'wp-simple-firewall' ), __( 'Automatic Bot', 'wp-simple-firewall' ) );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Blocks 100% of all automated bot-generated comment SPAM.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) )
				];
				$titleShort = __( 'Bot SPAM', 'wp-simple-firewall' );
				break;

			case 'section_human_spam_filter' :
				$title = sprintf( __( '%s Comment SPAM Protection', 'wp-simple-firewall' ), __( 'Human', 'wp-simple-firewall' ) );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Uses a 3rd party SPAM dictionary to detect human-based comment SPAM.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
					__( 'This tool, unlike other SPAM tools such as Akismet, will not send your comment data to 3rd party services for analysis.', 'wp-simple-firewall' )
				];
				$titleShort = __( 'Human SPAM', 'wp-simple-firewall' );
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $title,
			'title_short' => $titleShort,
			'summary'     => $summary ?? [],
		];
	}

	public function getOptionStrings( string $key ) :array {
		$modName = $this->getMod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_comments_filter' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = __( 'Enable (or Disable) The Comment SPAM Protection Feature', 'wp-simple-firewall' );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), __( 'Comment SPAM Protection', 'wp-simple-firewall' ) ) ];
				break;

			case 'trusted_commenter_minimum' :
				$name = __( 'Trusted Commenter Minimum', 'wp-simple-firewall' );
				$summary = __( 'Minimum Number Of Approved Comments Before Commenter Is Trusted', 'wp-simple-firewall' );
				$desc = [
					__( 'Specify how many approved comments must exist before a commenter is trusted and their comments are no longer scanned.', 'wp-simple-firewall' ),
					__( 'Normally WordPress will trust after 1 comment.', 'wp-simple-firewall' )
				];
				break;

			case 'trusted_user_roles' :
				$name = __( 'Trusted User Roles', 'wp-simple-firewall' );
				$summary = __( "Comments From Users With These Roles Will Never Be Scanned", 'wp-simple-firewall' );
				$desc = [
					__( "Shield doesn't normally scan comments from logged-in or registered users.", 'wp-simple-firewall' ),
					__( "Specify user roles here that shouldn't be scanned.", 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Important', 'wp-simple-firewall' ), __( 'Take a new line for each user role.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Available Roles', 'wp-simple-firewall' ),
						implode( ', ', Services::WpUsers()->getAvailableUserRoles() ) )
				];
				break;

			case 'enable_antibot_comments' :
				$name = __( 'AntiBot Detection Engine (ADE)', 'wp-simple-firewall' );
				$summary = __( "Use ADE To Detect SPAM Bots And Block Comment SPAM", 'wp-simple-firewall' );
				$desc = [
					sprintf( __( "AntiBot Detection Engine is %s's exclusive bot-detection technology that removes the needs for CAPTCHA and other challenges.", 'wp-simple-firewall' ),
						$this->getCon()->getHumanName() ),
					__( 'This feature is designed to replace the CAPTCHA and Bot Protection options.', 'wp-simple-firewall' ),
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
				$desc = [ sprintf( __( 'When a comment is detected as being SPAM from %s, the comment will be categorised based on this setting.', 'wp-simple-firewall' ), '<span style"text-decoration:underline;">'.__( 'a human commenter', 'wp-simple-firewall' ).'</span>' ) ];
				break;

			case 'comments_default_action_spam_bot' :
				$name = __( 'SPAM Action', 'wp-simple-firewall' );
				$summary = __( 'Where To Put SPAM Comments', 'wp-simple-firewall' );
				$desc = [
					sprintf( __( 'When a comment is detected as being SPAM, %s will put the comment in the specified folder.', 'wp-simple-firewall' ),
						$this->getCon()->getHumanName() )
				];
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