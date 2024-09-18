<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

/**
 * https://wordpress.org/plugins/simple-membership/
 *
 * Filters operate such that if a non-empty value is returned, captcha validation is considered to have
 * failed.
 */
class SimpleMembership extends Base {

	protected function login() {
		add_filter( 'swpm_validate_login_form_submission', [ $this, 'checkLogin' ], 100 );
	}

	protected function register() {
		add_filter( 'swpm_validate_registration_form_submission', [ $this, 'checkRegister' ], 100 );
	}

	protected function lostpassword() {
		add_filter( 'swpm_validate_pass_reset_form_submission', [ $this, 'checkLostPassword' ], 100 );
	}

	public function checkLogin( $msg ) :string {
		return $this->check( (string)$msg, 'login' );
	}

	public function checkRegister( $msg ) :string {
		return $this->check( (string)$msg, 'register' );
	}

	public function checkLostPassword( $msg ) :string {
		return $this->check( (string)$msg, 'lostpassword' );
	}

	private function check( string $msg, string $type ) :string {
		return ( empty( $msg ) && $this->setAuditAction( $type )->isBotBlockRequired() ) ?
			'Shield silentCAPTCHA Check Failed' : '';
	}
}