<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class ElementorPro extends Base {

	protected function run() {
		add_action( 'elementor_pro/forms/validation', function ( $form, $ajax_handler ) {
			/** @var \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler */
			if ( empty( $ajax_handler->errors ) && $this->isBotBlockRequired() ) {
				$msg = $this->getCommonSpamMessage();
				$ajax_handler->add_error( 'shield-antibot', $msg );
				$ajax_handler->add_error_message( $msg );
			}
		}, 1000, 2 );
	}
}