<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\SpamHandlers;

class ContactForm7 extends Base {

	const SLUG = 'contactform7';

	protected function run() {
		add_filter( 'wpcf7_spam', function ( $wasSpam, $submission ) {
			return $wasSpam || $this->isSpamBot();
		}, 1000, 2 );
	}

	protected function isPluginInstalled() :bool {
		return defined( 'WPCF7_TEXT_DOMAIN' ) && WPCF7_TEXT_DOMAIN === 'contact-form-7';
	}
}