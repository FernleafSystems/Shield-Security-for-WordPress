<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertAdminLogin;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\AdminLoginAlertContextBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;

class AlertHandlerAdminLogin extends AlertHandlerBase {

	use WpLoginCapture;

	public function alertAction() :string {
		return EmailInstantAlertAdminLogin::class;
	}

	public function alertTitle() :string {
		return __( 'Admin Login Detected', 'wp-simple-firewall' );
	}

	public function alertDataKeys() :array {
		return [
			'admin_login',
		];
	}

	protected function run() {
		$this->setupLoginCaptureHooks();
	}

	protected function captureLogin( \WP_User $user ) {
		$context = ( new AdminLoginAlertContextBuilder() )->build( $user );
		if ( $context !== null ) {
			self::con()->comps->instant_alerts->updateAlertDataFor( $this, [
				'admin_login' => $context,
			] );
		}
	}

	public function isImmediateAlert() :bool {
		return true;
	}
}
