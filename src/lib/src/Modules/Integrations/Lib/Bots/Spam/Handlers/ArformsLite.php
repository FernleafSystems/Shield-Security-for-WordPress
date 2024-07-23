<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class ArformsLite extends Base {

	protected function run() {
		add_filter( 'arflite_is_to_validate_spam_filter', function ( $validate ) {
			if ( $validate && $this->isBotBlockRequired() ) {
				$validate = false;
			}
			return $validate;
		}, 1000 );
	}

	protected static function ProviderMeetsRequirements() :bool {
		global $arfliteversion;
		return !empty( $arfliteversion ) && \is_string( $arfliteversion ) && \version_compare( $arfliteversion, '1.6.8', '>=' );
	}
}