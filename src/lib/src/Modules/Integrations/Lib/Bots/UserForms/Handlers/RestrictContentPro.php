<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

class RestrictContentPro extends Base {

	protected function login() {
		\add_action( 'rcp_login_form_errors', [ $this, 'checkLogin' ] );
	}

	protected function lostpassword() {
		\add_action( 'rcp_retrieve_password_form_errors', [ $this, 'checkLostPassword' ] );
	}

	public function checkLogin() {
		if ( $this->setAuditAction( 'login' )->isBotBlockRequired() ) {
			\rcp_errors()->add( 'shield-fail-login', $this->getErrorMessage(), 'login' );
		}
	}

	public function checkLostPassword() {
		if ( $this->setAuditAction( 'lostpassword' )->isBotBlockRequired() ) {
			\rcp_errors()->add( 'shield-fail-lostpassword', $this->getErrorMessage(), 'lostpassword' );
		}
	}

	protected static function ProviderMeetsRequirements() :bool {
		return \function_exists( '\rcp_errors' );
	}
}