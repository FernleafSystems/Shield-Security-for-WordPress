<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class HappyForms extends Base {

	protected function run() {
		add_filter( 'happyforms_validate_submission',
			function ( $is_valid, $request = null, $form = null ) {
				if ( $is_valid ) {
					$is_valid = !$this->isBotBlockRequired();
				}
				return $is_valid;
			},
			1000, 3
		);
	}

	protected static function ProviderMeetsRequirements() :bool {
		return \function_exists( '\happyforms_get_version' )
			   && \version_compare( (string)happyforms_get_version(), '1.15', '>=' );
	}
}