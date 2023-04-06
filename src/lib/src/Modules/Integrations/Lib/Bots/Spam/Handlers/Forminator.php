<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class Forminator extends Base {

	protected function run() {
		add_filter( 'forminator_spam_protection', function ( $wasSpam ) {
			return $wasSpam || $this->isBotBlockRequired();
		}, 1000 );
	}
}