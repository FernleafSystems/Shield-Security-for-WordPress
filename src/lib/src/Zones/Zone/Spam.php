<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

class Spam extends Base {

	public function tooltip() :string {
		return 'Edit settings for the entire SPAM blocking zone';
	}

	public function components() :array {
		return [
			Component\CommentSpamBlockBot::class,
			Component\CommentSpamBlockHuman::class,
			Component\ContactFormSpamBlockBot::class,
		];
	}

	public function description() :array {
		return [
			__( 'SPAM is perpetrated in 2 main ways: by bots and humans.', 'wp-simple-firewall' ),
			sprintf( __( "With %s's built-in silentCAPTCHA technology, stopping bots is actually quite easy to do now.", 'wp-simple-firewall' ), self::con()->labels->Name ),
			\implode( ' ', [
				__( "It's human SPAM which still causes most frustration, as they usually don't trigger our defenses.", 'wp-simple-firewall' ),
				sprintf( __( "%s uses a regularly-updated dictionary of commonly-used SPAM content to block this type of SPAM content.", 'wp-simple-firewall' ), self::con()->labels->Name ),
				__( "This approach helps retain your data and privacy as it doesn't send comments offsite to be scanned.", 'wp-simple-firewall' ),
			] ),
			__( "Contact Form SPAM can only be blocked with a custom-built integration for each form provider - if your form isn't supported, please reach out to our support team.", 'wp-simple-firewall' ),
		];
	}

	public function icon() :string {
		return 'chat-left-dots';
	}

	public function title() :string {
		return __( 'SPAM', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Block WordPress Comment SPAM and Contact Form SPAM.', 'wp-simple-firewall' );
	}

	protected function getUnderlyingModuleZone() :?string {
		return Component\Modules\ModuleSpam::class;
	}
}