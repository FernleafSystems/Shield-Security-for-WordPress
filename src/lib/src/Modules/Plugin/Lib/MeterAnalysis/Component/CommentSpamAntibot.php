<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options;

class CommentSpamAntibot extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'comment_spam_antibot';
	public const WEIGHT = 7;

	protected function testIfProtected() :bool {
		$mod = self::con()->getModule_Comments();
		/** @var Options $opts */
		$opts = $mod->opts();
		return $mod->isModOptEnabled() && $opts->isEnabledAntiBot();
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