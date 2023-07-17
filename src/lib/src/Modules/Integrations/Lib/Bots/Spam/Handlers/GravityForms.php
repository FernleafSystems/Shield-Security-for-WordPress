<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class GravityForms extends Base {

	protected function run() {
		add_filter( 'gform_entry_is_spam', function ( $wasSpam ) {
			return $wasSpam || $this->isBotBlockRequired();
		}, 1000 );
	}

	protected static function ProviderMeetsRequirements() :bool {
		return isset( \GFForms::$version ) && \version_compare( \GFForms::$version, '2.4.17', '>=' );
	}
}