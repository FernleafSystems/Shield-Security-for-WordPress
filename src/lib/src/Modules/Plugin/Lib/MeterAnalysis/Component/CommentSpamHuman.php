<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options;

class CommentSpamHuman extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'comment_spam_human';
	public const WEIGHT = 2;

	protected function testIfProtected() :bool {
		$mod = self::con()->getModule_Comments();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		return $mod->isModOptEnabled() && $opts->isEnabledHumanCheck();
	}

	protected function getOptConfigKey() :string {
		return 'enable_comments_human_spam_filter';
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