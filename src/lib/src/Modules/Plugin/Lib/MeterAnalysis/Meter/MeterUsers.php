<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class MeterUsers extends MeterBase {

	public const SLUG = 'users';

	public function title() :string {
		return __( 'Customer And Visitor Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How well customers, users, and visitors are protected', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "It is important to not only block malicious request, but also protect your existing users and customers.", 'wp-simple-firewall' ),
			__( "This section assesses how well you're protecting existing users, administrator accounts, and passwords.", 'wp-simple-firewall' ),
			__( "Another, often overlooked, component of security is how you communicate to your visitors that you're employing strong security practices and that you take their data and privacy seriously.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponents() :array {
		$components = [
			Component\SystemSslCertificate::class,
			Component\UserAdminExists::class,
			Component\SecurityAdminAdmins::class,
			Component\Login2fa::class,
			Component\UserSuspendInactive::class,
			Component\UserEmailValidation::class,
			Component\UserPasswordPolicies::class,
			Component\UserPasswordPwned::class,
			Component\UserPasswordStrength::class,
			Component\HttpHeaders::class,
		];
		if ( !self::con()->comps->whitelabel->isEnabled() ) {
			$components[] = Component\PluginBadge::class;
		}
		return $components;
	}
}