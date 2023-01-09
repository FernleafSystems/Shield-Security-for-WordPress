<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options;

class CommentSpamAntibot extends Base {

	public const SLUG = 'comment_spam_antibot';
	public const WEIGHT = 75;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_Comments();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isEnabledAntiBot();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_Comments();
		return $mod->isModOptEnabled() ? $this->link( 'enable_antibot_comments' ) : $this->link( 'enable_comments_filter' );
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