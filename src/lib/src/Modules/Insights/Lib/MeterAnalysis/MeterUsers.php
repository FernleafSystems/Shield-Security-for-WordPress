<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterUsers extends MeterBase {

	const SLUG = 'users';

	protected function getWorkingMods() :array {
		return [
			$this->getCon()->getModule_LoginGuard(),
			$this->getCon()->getModule_UserManagement()
		];
	}

	protected function title() :string {
		return __( 'Customer And Visitor Protection', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'How well customers, users, and visitors are protected', 'wp-simple-firewall' );
	}

	protected function description() :array {
		return [
			__( "It is important to not only block malicious request, but also protect your existing users and customers.", 'wp-simple-firewall' ),
			__( "This section assesses how well you're protecting existing users, administrator accounts, and passwords.", 'wp-simple-firewall' ),
			__( "Another, often overlooked, component of security is how you communicate to your visitors that you're employing strong security practices and that you take their data and privacy seriously.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponentSlugs() :array {
		$components = [
			'ssl_certificate',
			'admin_user',
			'secadmin_admins',
			'2fa',
			'sessions_idle',
			'users_inactive',
			'user_email_validation',
			'pass_policies',
			'pass_pwned',
			'pass_str',
			'headers',
		];
		if ( !$this->getCon()->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() ) {
			$components[] = 'plugin_badge';
		}
		return $components;
	}
}