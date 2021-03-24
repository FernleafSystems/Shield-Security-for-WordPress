<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	public function build() :array {
		/** @var CommentsFilter\ModCon $mod */
		$mod = $this->getMod();
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'SPAM Blocking', 'wp-simple-firewall' ),
			'subtitle'     => __( 'Block Bot & Human Comment SPAM', 'wp-simple-firewall' ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		if ( !$mod->isModOptEnabled() ) {
			$cards[ 'mod' ] = $this->getModDisabledCard();
		}
		else {
			$botSpamOn = $opts->isEnabledAntiBot() || $opts->isEnabledGaspCheck() || $mod->isEnabledCaptcha();
			$cards[ 'bot' ] = [
				'name'    => __( 'Bot SPAM', 'wp-simple-firewall' ),
				'state'   => $botSpamOn ? 1 : -1,
				'summary' => $botSpamOn ?
					__( 'Bot SPAM comments are blocked', 'wp-simple-firewall' )
					: __( 'There is no protection against Bot SPAM comments', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_bot_comment_spam_protection_filter' ),
			];
			$cards[ 'human' ] = [
				'name'    => __( 'Human SPAM', 'wp-simple-firewall' ),
				'state'   => $opts->isEnabledHumanCheck() ? 1 : -1,
				'summary' => $opts->isEnabledHumanCheck() ?
					__( 'Comments posted by humans are checked for SPAM', 'wp-simple-firewall' )
					: __( "Comments posted by humans aren't checked for SPAM", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_human_spam_filter' ),
			];
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'comments_filter' => $cardSection ];
	}
}