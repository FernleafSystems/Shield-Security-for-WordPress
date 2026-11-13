<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Spam extends Base {

	public function components() :array {
		return [
			Component\CommentSpamBlockBot::class,
			Component\CommentSpamBlockHuman::class,
			Component\ContactFormSpamBlockBot::class,
			Component\TrustedCommenters::class,
		];
	}

	public function title() :string {
		return __( 'SPAM', 'wp-simple-firewall' );
	}
}
