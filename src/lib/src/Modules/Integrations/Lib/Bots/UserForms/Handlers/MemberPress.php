<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

/**
 * Errors are passed about using an array of strings.
 */
class MemberPress extends Base {

	protected function login() {
		add_filter( 'mepr-validate-login', [ $this, 'checkLogin_MP' ], 100 );
	}

	protected function register() {
		add_filter( 'mepr-validate-signup', [ $this, 'checkRegister_MP' ], 10, 2 );
	}

	protected function lostpassword() {
		add_filter( 'mepr-validate-forgot-password', [ $this, 'checkLostPassword_MP' ], 100 );
	}

	/**
	 * @param array $errors
	 * @return array
	 */
	public function checkLogin_MP( $errors ) {
		if ( empty( $errors ) && $this->setAuditAction( 'login' )->checkIsBot() ) {
			$errors = [
				$this->getErrorMessage()
			];
		}
		return $errors;
	}

	/**
	 * @param array $errors
	 * @return array
	 */
	public function checkLostPassword_MP( $errors ) {
		if ( empty( $errors ) && $this->setAuditAction( 'lostpassword' )->checkIsBot() ) {
			$errors = [
				$this->getErrorMessage()
			];
		}
		return $errors;
	}

	/**
	 * @param string[] $errors
	 * @return string[]
	 */
	public function checkRegister_MP( $errors ) {
		if ( empty( $errors ) && $this->setAuditAction( 'register' )->checkIsBot() ) {
			$errors = [
				$this->getErrorMessage()
			];
		}
		return $errors;
	}

	public static function IsProviderInstalled() :bool {
		return function_exists( 'mepr_autoloader' ) || @class_exists( '\MeprAccountCtrl' );
	}
}