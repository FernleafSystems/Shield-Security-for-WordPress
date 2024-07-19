<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class MeterSpam extends MeterBase {

	public const SLUG = 'spam';

	public function title() :string {
		return __( 'Comment & Contact Form SPAM Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How all types of WordPress SPAM are handled', 'wp-simple-firewall' );
	}

	public function description() :array {
		$desc = [
			__( "WordPress comment SPAM is primarily done via automated Bots.", 'wp-simple-firewall' ),
			__( "With our powerful silentCAPTCHA we can detect nearly 100% of all bot SPAM.", 'wp-simple-firewall' ),
			__( "With our SPAM dictionary, we can identify human SPAM comments without sending any data off your site to 3rd parties.", 'wp-simple-firewall' ),
		];
		if ( !self::con()->comps->whitelabel->isEnabled() ) {
			$desc[] = sprintf( __( "With %s we can directly integrate with all the major Contact Form plugins to block Contact Form SPAM from automated Bots.", 'wp-simple-firewall' ), 'ShieldPRO' );
		}
		return $desc;
	}

	protected function getComponents() :array {
		return [
			Component\CommentSpamAntibot::class,
			Component\CommentSpamHuman::class,
			Component\ContactFormSpam::class,
			Component\CommentApprovedMinimum::class,
		];
	}
}