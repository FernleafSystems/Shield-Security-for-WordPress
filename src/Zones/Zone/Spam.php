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
		$con = self::con();
		return [
			__( 'SPAM is perpetrated by 2 types of visitors: automated bots and humans.', 'wp-simple-firewall' ),
			sprintf( __( 'With %s technology, stopping bots is quite easy to do now.', 'wp-simple-firewall' ), $con->labels->getBrandName( 'silentcaptcha' ) ),
			\implode( ' ', [
				__( 'Human SPAM still causes most frustration as it often bypasses SPAM defenses.', 'wp-simple-firewall' ),
				sprintf( __( '%s uses a regularly-updated dictionary of commonly-used SPAM content to block this type of SPAM content.', 'wp-simple-firewall' ), $con->labels->Name ),
				__( "This approach helps retain your data and privacy as it doesn't send comments offsite to be scanned.", 'wp-simple-firewall' ),
			] ),
			__( 'Contact Form SPAM can only be blocked with a custom-built integration for each form provider - if your form is not supported, please reach out to our support team.', 'wp-simple-firewall' ),
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