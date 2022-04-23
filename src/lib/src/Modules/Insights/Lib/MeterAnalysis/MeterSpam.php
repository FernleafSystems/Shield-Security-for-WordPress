<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

class MeterSpam extends MeterBase {

	const SLUG = 'spam';

	protected function title() :string {
		return __( 'SPAM Protection', 'wp-simple-firewall' );
	}

	protected function buildComponents() :array {
		$mod = $this->getCon()->getModule_Comments();
		/** @var CommentsFilter\Options $opts */
		$opts = $mod->getOptions();
		return [
			'antibot'          => [
				'title'            => __( 'Bot Comment SPAM', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Your site is protected against automated Comment SPAM by Bots.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Your site isn't protected against automated Comment SPAM by Bots.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'enable_antibot_comments' ),
				'protected'        => $opts->isEnabledAntiBot(),
				'weight'           => 75,
			],
			'human'            => [
				'title'            => __( 'Human Comment SPAM', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Your site is protected against Comment SPAM by humans.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Your site isn't protected against Comment SPAM by humans.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'enable_comments_human_spam_filter' ),
				'protected'        => $opts->isEnabledHumanCheck(),
				'weight'           => 25,
			],
//			'approved_minimum' => [
//				'title'            => __( 'Minimum Comment Auto-Approval', 'wp-simple-firewall' ),
//				'desc_protected'   => __( 'Comments are auto-approved only if they have at least 1 other approved comment.', 'wp-simple-firewall' ),
//				'desc_unprotected'   => __( "Comments are auto-approved only if they have at least 1 other approved comment.", 'wp-simple-firewall' ),
//				'href'             => $mod->getUrl_DirectLinkToOption( 'enable_comments_human_spam_filter' ),
//				'protected'        => $opts->getApprovedMinimum() > 1,
//				'weight'           => 10,
//			],
		];
	}
}