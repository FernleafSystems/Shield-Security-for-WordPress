<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class Groundhogg extends Base {

	protected function run() {
		add_filter( 'groundhogg/form/submission_handler/is_spam', function ( $wasSpam ) {
			return $wasSpam || $this->isSpam();
		}, 1000 );
	}

	public function getProviderName() :string {
		return 'Groundhogg';
	}

	public static function IsProviderInstalled() :bool {
		return defined( 'GROUNDHOGG_TEXT_DOMAIN' ) && defined( 'GROUNDHOGG_VERSION' )
			   && version_compare( GROUNDHOGG_VERSION, '2.4.5.5', '>=' );
	}
}