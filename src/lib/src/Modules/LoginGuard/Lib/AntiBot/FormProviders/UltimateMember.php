<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

/**
 * https://wordpress.org/plugins/ultimate-member/
 */
class UltimateMember extends BaseFormProvider {

	protected function login() {
		add_action( 'um_after_login_fields', [ $this, 'printFormInsert' ], 100 );
		add_action( 'um_submit_form_login', [ $this, 'checkLogin' ], 100 );
	}

	protected function register() {
		add_action( 'um_after_register_fields', [ $this, 'printFormInsert' ], 100 );
		add_action( 'um_submit_form_register', [ $this, 'checkRegister' ], 5, 0 );
	}

	protected function lostpassword() {
		add_action( 'um_after_password_reset_fields', [ $this, 'printFormInsert' ], 100 );
		add_action( 'um_submit_form_password_reset', [ $this, 'checkLostPassword' ], 5, 0 );
	}

	public function checkLogin() {
		try {
			$this->setActionToAudit( 'ultimatemember-login' )
				 ->checkProviders();
		}
		catch ( \Exception $e ) {
			\UM()->form()->add_error( 'shield-fail-login', $e->getMessage() );
		}
	}

	public function checkLostPassword() {
		try {
			$this->setActionToAudit( 'ultimatemember-lostpassword' )
				 ->checkProviders();
		}
		catch ( \Exception $e ) {
			\UM()->form()->add_error( 'shield-fail-lostpassword', $e->getMessage() );
		}
	}

	public function checkRegister() {
		try {
			$this->setActionToAudit( 'ultimatemember-register' )
				 ->checkProviders();
		}
		catch ( \Exception $e ) {
			\UM()->form()->add_error( 'shield-fail-register', $e->getMessage() );
		}
	}
}