<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Spam\Handlers;

class ElementorPro extends Base {

	protected function run() {
		add_action( 'elementor_pro/forms/validation', function ( $form, $ajax_handler ) {
			/** @var \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler */
			if ( empty( $ajax_handler->errors ) && $this->isSpam() ) {
				$msg = sprintf( __( "This appears to be spam - failed %s AntiBot protection checks.", 'wp-simple-firewall' ),
					$this->getCon()->getHumanName() );
				$ajax_handler->add_error( 'shield-antibot', $msg );
				$ajax_handler->add_error_message( $msg );
			}
		}, 1000, 2 );
	}

	protected function getProviderName() :string {
		return 'Elementor Pro';
	}

	protected function isProviderAvailable() :bool {
		return defined( 'ELEMENTOR_PRO_VERSION' ) && @function_exists( 'elementor_pro_load_plugin' );
	}
}