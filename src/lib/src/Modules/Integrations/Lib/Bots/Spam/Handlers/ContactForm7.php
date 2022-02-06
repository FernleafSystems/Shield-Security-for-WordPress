<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class ContactForm7 extends Base {

	protected function run() {
		add_filter( 'wpcf7_spam', function ( $isSpam, $submission ) {

			if ( !$isSpam && $this->isSpam() ) {
				$isSpam = true;
				add_filter( 'wpcf7_display_message', function ( $msg, $status ) {
					if ( $status === 'spam' ) {
						$msg = $this->getCommonSpamMessage();
					}
					return $msg;
				}, 100, 2 );
			}

			return $isSpam;
		}, 1000, 2 );
	}

	public static function IsProviderInstalled() :bool {
		return defined( 'WPCF7_TEXT_DOMAIN' ) && WPCF7_TEXT_DOMAIN === 'contact-form-7';
	}
}