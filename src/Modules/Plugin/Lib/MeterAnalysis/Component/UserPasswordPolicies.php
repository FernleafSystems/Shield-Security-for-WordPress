<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class UserPasswordPolicies extends UserPasswordPoliciesBase {

	public const SLUG = 'user_pass_policies';

	public function title() :string {
		return __( 'Password Policies', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Password policies are enabled to help promote good password hygiene.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Password polices aren't enabled which may lead to poor password hygiene.", 'wp-simple-firewall' );
	}
}