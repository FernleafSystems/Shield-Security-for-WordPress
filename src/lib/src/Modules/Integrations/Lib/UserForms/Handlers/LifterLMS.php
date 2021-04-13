<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\UserForms\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders\AntiBot;

/**
 * Lost Password is mimicked after WordPress so no separate integration necessary (
 * Class LifterLMS
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\UserForms\Handlers
 */
class LifterLMS extends Base {

	protected function login() {
		add_filter( 'llms_after_user_login_data_validation', [ $this, 'checkLogin' ], 100 );
	}

	protected function register() {
		add_filter( 'lifterlms_user_registration_data', [ $this, 'checkRegister' ], 100 );
	}

	/**
	 * @param bool|\WP_Error $valid
	 * @return bool|\WP_Error
	 */
	public function checkLogin( $valid ) {
		if ( !is_wp_error( $valid ) ) {
			try {
				$this->setActionToAudit( $this->getProviderSlug().'-login' )
					 ->checkProviders();
			}
			catch ( \Exception $e ) {
				$valid = new \WP_Error( 'shield-fail-login', $e->getMessage() );
			}
		}
		return $valid;
	}

	/**
	 * @param bool|\WP_Error $valid
	 * @return bool|\WP_Error
	 */
	public function checkRegister( $valid ) {
		if ( !is_wp_error( $valid ) ) {
			try {
				$this->setActionToAudit( $this->getProviderSlug().'-register' )
					 ->checkProviders();
			}
			catch ( \Exception $e ) {
				$valid = new \WP_Error( 'shield-fail-register', $e->getMessage() );
			}
		}
		return $valid;
	}

	protected function getProviderName() :string {
		return 'LifterLMS';
	}

	protected function isProviderAvailable() :bool {
		return defined( 'LLMS_PLUGIN_FILE' ) && @class_exists( 'LifterLMS' );
	}
}