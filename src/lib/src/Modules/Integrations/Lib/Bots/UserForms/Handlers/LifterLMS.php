<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

/**
 * Lost Password is mimicked after WordPress so no separate integration necessary
 */
class LifterLMS extends Base {

	protected function login() {
		add_filter( 'llms_after_user_login_data_validation', [ $this, 'checkLogin_LLMS' ], 100 );
	}

	protected function register() {
		add_filter( 'lifterlms_user_registration_data', [ $this, 'checkRegister_LLMS' ], 100 );
	}

	/**
	 * @param bool|\WP_Error $valid
	 * @return bool|\WP_Error
	 */
	public function checkLogin_LLMS( $valid ) {
		if ( !is_wp_error( $valid ) && $this->setAuditAction( 'login' )->isBotBlockRequired() ) {
			$valid = new \WP_Error( 'shield-fail-login', $this->getErrorMessage() );
		}
		return $valid;
	}

	/**
	 * @param bool|\WP_Error $valid
	 * @return bool|\WP_Error
	 */
	public function checkRegister_LLMS( $valid ) {
		if ( !is_wp_error( $valid ) && $this->setAuditAction( 'register' )->isBotBlockRequired() ) {
			$valid = new \WP_Error( 'shield-fail-register', $this->getErrorMessage() );
		}
		return $valid;
	}

	public static function IsProviderInstalled() :bool {
		return defined( 'LLMS_PLUGIN_FILE' ) && @class_exists( 'LifterLMS' )
			   && defined( 'LLMS_VERSION' ) && version_compare( LLMS_VERSION, '4.20', '>' );
	}
}