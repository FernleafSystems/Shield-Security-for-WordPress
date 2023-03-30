<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

use FernleafSystems\Wordpress\Services\Services;

class CalderaForms extends Base {

	protected function run() {
		add_action( 'caldera_forms_submit_start', function ( $form, $process_id ) {
			// response mimics Caldera's "honeypot" code but without all the faffing about.
			if ( $this->isBotBlockRequired() ) {
				\Caldera_Forms::form_redirect( 'shield_antibot', Services::Request()->getPath(), $form, $process_id );
			}
		}, 1000, 2 );
	}

	protected static function ProviderMeetsRequirements() :bool {
		return \defined( '\CFCORE_VER' ) && \version_compare( \CFCORE_VER, '1.9.6', '>=' );
	}
}