<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

class EasyDigitalDownloads extends BaseFormProvider {

	protected function login() {
		add_action( 'edd_login_fields_after', [ $this, 'printFormInsert' ], 10 );
	}

	protected function register() {
		add_action( 'edd_register_form_fields_before_submit', [ $this, 'printFormInsert' ], 10 );
		add_action( 'edd_process_register_form', [ $this, 'checkRegister' ], 10 );
	}

	public function checkRegister() {
		try {
			$this->setActionToAudit( 'edd-register' )
				 ->checkProviders();
		}
		catch ( \Exception $e ) {
			if ( function_exists( 'edd_set_error' ) ) {
				edd_set_error( $this->getCon()->prefix( uniqid() ), $e->getMessage() );
			}
		}
	}
}