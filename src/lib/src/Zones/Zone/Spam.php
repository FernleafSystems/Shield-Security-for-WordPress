<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Spam extends Base {

	public function components() :array {
		return [
			Component\CommentSpamBlockBot::class,
			Component\CommentSpamBlockHuman::class,
			Component\ContactFormSpamBlockBot::class,
		];
	}

	public function description() :array {
		return [
			__( '.', 'wp-simple-firewall' ),
		];
	}

	public function icon() :string {
		return 'shield-shaded';
	}

	public function title() :string {
		return __( 'SPAM', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Protection for users and their sessions.', 'wp-simple-firewall' );
	}
}