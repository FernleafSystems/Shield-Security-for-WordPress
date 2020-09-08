<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\ShieldUI {

	public function getInsightsOverviewCards() :array {
		/** @var \ICWP_WPSF_FeatureHandler_CommentsFilter $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
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
			$cards[ 'bot' ] = [
				'name'    => __( 'Bot SPAM', 'wp-simple-firewall' ),
				'state' => ( $opts->isEnabledGaspCheck() || $mod->isEnabledCaptcha() ) ? 1 : -1,
				'summary' => ( $opts->isEnabledGaspCheck() || $mod->isEnabledCaptcha() ) ?
					__( 'Bot SPAM comments are blocked', 'wp-simple-firewall' )
					: __( 'There is no protection against Bot SPAM comments', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_bot_comment_spam_protection_filter' ),
			];
			$cards[ 'human' ] = [
				'name'    => __( 'Human SPAM', 'wp-simple-firewall' ),
				'state' => $opts->isEnabledHumanCheck() ? 1 : -1,
				'summary' => $opts->isEnabledHumanCheck() ?
					__( 'Comments posted by humans are checked for SPAM', 'wp-simple-firewall' )
					: __( "Comments posted by humans aren't checked for SPAM", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_human_spam_filter' ),
			];
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'comments_filter' => $cardSection ];
	}

	/**
	 * @return array
	 */
	public function getInsightsConfigCardData() :array {
		/** @var \ICWP_WPSF_FeatureHandler_CommentsFilter $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$data = [
			'strings'      => [
				'title' => __( 'SPAM Blocking', 'wp-simple-firewall' ),
				'sub'   => __( 'Block Bot & Human Comment SPAM', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $mod->getUrl_AdminPage()
		];

		if ( !$mod->isModOptEnabled() ) {
			$data[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$data[ 'key_opts' ][ 'bot' ] = [
				'name'    => __( 'Bot SPAM', 'wp-simple-firewall' ),
				'enabled' => $opts->isEnabledGaspCheck() || $mod->isEnabledCaptcha(),
				'summary' => ( $opts->isEnabledGaspCheck() || $mod->isEnabledCaptcha() ) ?
					__( 'Bot SPAM comments are blocked', 'wp-simple-firewall' )
					: __( 'There is no protection against Bot SPAM comments', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_bot_comment_spam_protection_filter' ),
			];
			$data[ 'key_opts' ][ 'human' ] = [
				'name'    => __( 'Human SPAM', 'wp-simple-firewall' ),
				'enabled' => $opts->isEnabledHumanCheck(),
				'summary' => $opts->isEnabledHumanCheck() ?
					__( 'Comments posted by humans are checked for SPAM', 'wp-simple-firewall' )
					: __( "Comments posted by humans aren't checked for SPAM", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_human_spam_filter' ),
			];
		}

		return $data;
	}
}