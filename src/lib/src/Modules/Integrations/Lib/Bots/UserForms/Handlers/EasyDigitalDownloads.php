<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

class EasyDigitalDownloads extends Base {

	protected function register() {
		add_action( 'edd_process_register_form', [ $this, 'checkRegister_EDD' ] );
	}

	public function checkRegister_EDD() {
		if ( $this->setAuditAction( 'register' )->checkIsBot() ) {
			edd_set_error( $this->getCon()->prefix( rand() ), $this->getErrorMessage() );
		}
	}

	public function getProviderName() :string {
		return 'Easy Digital Downloads';
	}

	public static function IsProviderInstalled() :bool {
		return function_exists( 'edd_set_error' ) && @class_exists( 'Easy_Digital_Downloads' );
	}
}