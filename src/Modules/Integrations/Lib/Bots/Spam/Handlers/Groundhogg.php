<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class Groundhogg extends Base {

	protected function run() {
		add_filter( 'groundhogg/form/submission_handler/is_spam', function ( $wasSpam ) {
			return $wasSpam || $this->isBotBlockRequired();
		}, 1000 );
	}

	protected static function ProviderMeetsRequirements() :bool {
		return \defined( '\GROUNDHOGG_VERSION' ) && \version_compare( \GROUNDHOGG_VERSION, '2.4.5.5', '>=' );
	}
}