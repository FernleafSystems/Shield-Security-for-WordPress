<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Upgrade extends Base\Upgrade {

	protected function upgrade_905() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$opts->setOpt(
			'comments_default_action_human_spam',
			(string)$opts->getOpt( 'comments_default_action_human_spam' )
		);
		$opts->setOpt(
			'comments_default_action_spam_bot',
			(string)$opts->getOpt( 'comments_default_action_spam_bot' )
		);
	}

	protected function upgrade_900() {
		$opts = $this->getOptions();

		if ( $opts->getOpt( 'enable_google_recaptcha_comments' ) === 'N' ) {
			$opts->setOpt( 'google_recaptcha_style_comments', 'disabled' );
		}

		$map = [
			'comments_cooldown_interval'              => 'comments_cooldown',
			'comments_token_expire_interval'          => 'comments_expire',
			'enable_comments_human_spam_filter_items' => 'human_spam_items',
		];
		foreach ( $map as $from => $to ) {
			$val = $opts->getOpt( $from );
			if ( $val !== false ) {
				$opts->setOpt( $to, $val );
			}
		}
	}
}