<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\UserMfa;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Services\Services;

class ConfigEdit extends UserMfaBase {

	/** Secadmin is handled within the UI */
	use SecurityAdminNotRequired;

	public const SLUG = 'user_mfa_config_edit';
	public const TEMPLATE = '/admin/user/profile/mfa/remove_for_other_user.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$user = Services::WpUsers()->getUserById( $this->action_data[ 'user_id' ] );

		$providers = \array_map(
			function ( $provider ) {
				return $provider->getProviderName();
			},
			self::con()->comps->mfa->getProvidersActiveForUser( $user )
		);

		$isAdmin = Services::WpUsers()->isUserAdmin( $user );
		return [
			'flags'   => [
				'has_factors'      => \count( $providers ) > 0,
				'is_admin_profile' => $isAdmin,
				'can_remove'       => $con->isPluginAdmin() || !$isAdmin,
			],
			'strings' => [
				'title'            => __( 'Multi-Factor Authentication', 'wp-simple-firewall' ),
				'provided_by'      => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), $con->labels->Name ),
				'currently_active' => __( 'Currently active MFA Providers on this profile are' ),
				'remove_all'       => __( 'Remove All MFA Providers' ),
				'remove_all_from'  => __( 'Remove All MFA Providers From This User Profile' ),
				'remove_warning'   => __( "Certain providers may not be removed if they're enforced." ),
				'no_providers'     => __( 'There are no MFA providers active on this user account.' ),
				'only_secadmin'    => sprintf( __( 'Only %s Security Admins may modify the MFA settings of another admin account.' ), $con->labels->Name ),
				'authenticate'     => sprintf( __( 'You may authenticate with the %s Security Admin system and return here.' ), $con->labels->Name ),
			],
			'vars'    => [
				'user_id'          => $user->ID,
				'mfa_factor_names' => $providers,
			],
		];
	}
}