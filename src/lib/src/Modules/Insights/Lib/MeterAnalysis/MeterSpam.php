<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterSpam extends MeterBase {

	const SLUG = 'spam';

	protected function title() :string {
		return __( 'Comment & Contact Form SPAM Protection', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'How all types of WordPress SPAM are handled', 'wp-simple-firewall' );
	}

	protected function description() :array {
		$desc= [
			__( "WordPress comment SPAM is primarily done via automated Bots.", 'wp-simple-firewall' ),
			__( "With our powerful AntiBot Detection Engine we can thwart nearly 100% of all bot SPAM.", 'wp-simple-firewall' ),
			__( "With our SPAM dictionary, we can identify human SPAM comments without sending any data off your site to 3rd parties.", 'wp-simple-firewall' ),
		];

		if (!$this->getCon()->getModule_SecAdmin()->getWhiteLabelController()->isEnabled()) {
			$desc[] = sprintf( __( "With %s we can directly integrate with all the major Contact Form plugins to block Contact Form SPAM from automated Bots.", 'wp-simple-firewall' ), 'ShieldPRO' );
		}
		return $desc;
	}

	protected function getComponentSlugs() :array {
		return [
			'comment_spam_antibot',
			'comment_spam_human',
			'contact_forms_spam',
			'comment_approved_minimum',
		];
	}
}