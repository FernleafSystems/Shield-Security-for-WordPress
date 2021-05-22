<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	protected function buildModCards() :array {
		/** @var CommentsFilter\ModCon $mod */
		$mod = $this->getMod();
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();

		$cards = [];

		if ( $mod->isModOptEnabled() ) {
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

		return $cards;
	}

	protected function getSectionTitle() :string {
		return __( 'SPAM Blocking', 'wp-simple-firewall' );
	}

	protected function getSectionSubTitle() :string {
		return __( 'Block Bot & Human Comment SPAM', 'wp-simple-firewall' );
	}
}