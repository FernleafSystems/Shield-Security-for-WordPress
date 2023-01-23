<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options;

class CommentSpamHuman extends Base {

	public const SLUG = 'comment_spam_human';
	public const WEIGHT = 25;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_Comments();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isEnabledHumanCheck();
	}

	public function href() :string {
		$mod = $this->getCon()->getModule_Comments();
		return $mod->isModOptEnabled() ? $this->link( 'enable_comments_human_spam_filter' ) : $this->link( 'enable_comments_filter' );
	}

	public function title() :string {
		return __( 'Human Comment SPAM', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Your site is protected against Comment SPAM by humans.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Your site isn't protected against Comment SPAM by humans.", 'wp-simple-firewall' );
	}
}