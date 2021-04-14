<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class Forminator extends Base {

	protected function run() {
		add_filter( 'forminator_spam_protection', function ( $wasSpam ) {
			return $wasSpam || $this->isSpam();
		}, 1000 );
	}

	protected function getProviderName() :string {
		return 'Forminator';
	}

	public static function IsProviderInstalled() :bool {
		return defined( 'FORMINATOR_VERSION' ) && @class_exists( '\Forminator' );
	}
}