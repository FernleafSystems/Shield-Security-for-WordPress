<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Spam\Handlers;

class ContactForm7 extends Base {

	protected function run() {
		add_filter( 'wpcf7_spam', function ( $wasSpam, $submission ) {
			return $wasSpam || $this->isSpam();
		}, 1000, 2 );
	}

	protected function getProviderName() :string {
		return 'Contact Form 7';
	}

	protected function isPluginInstalled() :bool {
		return defined( 'WPCF7_TEXT_DOMAIN' ) && WPCF7_TEXT_DOMAIN === 'contact-form-7';
	}
}