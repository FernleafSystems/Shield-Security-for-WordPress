<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

/**
 * https://wordpress.org/plugins/ultimate-member/
 *
 * Class UltimateMember
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers
 */
class UltimateMember extends Base {

	protected function login() {
		add_action( 'um_submit_form_login', [ $this, 'checkLogin_UM' ], 100 );
	}

	protected function register() {
		add_action( 'um_submit_form_register', [ $this, 'checkRegister_UM' ], 5, 0 );
	}

	protected function lostpassword() {
		add_action( 'um_submit_form_password_reset', [ $this, 'checkLostPassword_UM' ], 5, 0 );
	}

	public function checkLogin_UM() {
		if ( $this->setAuditAction( 'login' )->checkIsBot() ) {
			\UM()->form()->add_error( 'shield-fail-login', $this->getErrorMessage() );
		}
	}

	public function checkLostPassword_UM() {
		if ( $this->setAuditAction( 'lostpassword' )->checkIsBot() ) {
			\UM()->form()->add_error( 'shield-fail-lostpassword', $this->getErrorMessage() );
		}
	}

	public function checkRegister_UM() {
		if ( $this->setAuditAction( 'register' )->checkIsBot() ) {
			\UM()->form()->add_error( 'shield-fail-register', $this->getErrorMessage() );
		}
	}

	protected function getProviderName() :string {
		return 'Ultimate Member';
	}

	public static function IsProviderInstalled() :bool {
		return function_exists( '\UM' ) && @class_exists( '\UM' ) && method_exists( '\UM', 'form' );
	}
}