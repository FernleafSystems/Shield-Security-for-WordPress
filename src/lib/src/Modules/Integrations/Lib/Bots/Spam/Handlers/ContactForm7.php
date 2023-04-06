<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class ContactForm7 extends Base {

	protected function run() {
		add_filter( 'wpcf7_spam', function ( $isSpam, $submission ) {

			if ( !$isSpam && $this->isBotBlockRequired() ) {
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
}