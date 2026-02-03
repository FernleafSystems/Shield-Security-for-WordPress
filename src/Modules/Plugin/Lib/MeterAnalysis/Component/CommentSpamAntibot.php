<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class CommentSpamAntibot extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'comment_spam_antibot';
	public const WEIGHT = 7;

	protected function testIfProtected() :bool {
		return self::con()->comps->opts_lookup->enabledAntiBotCommentSpam();
	}

	protected function getOptConfigKey() :string {
		return 'enable_antibot_comments';
	}

	public function title() :string {
		return __( 'Bot Comment SPAM', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Your site is protected against automated Comment SPAM by Bots.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Your site isn't protected against automated Comment SPAM by Bots.", 'wp-simple-firewall' );
	}
}