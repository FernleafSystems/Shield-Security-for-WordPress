<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class SuperForms extends Base {

	protected function run() {
		add_action( 'super_before_sending_email_hook', function ( $formSubmissionData ) {
			if ( $this->isBotBlockRequired() ) {
				\SUPER_Common::output_message( true, esc_html( $this->getCommonSpamMessage() ) );
			}
		}, 1000 );
	}

	protected static function ProviderMeetsRequirements() :bool {
		return isset( \SUPER_Forms::$version ) && \version_compare( \SUPER_Forms::$version, '4.9', '>=' );
	}
}