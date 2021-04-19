<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class UserProfile {

	use MfaControllerConsumer;

	public function run() {
		if ( is_admin() ) {
			add_action( 'show_user_profile', [ $this, 'addOptionsToUserProfile' ] );
			add_action( 'edit_user_profile', [ $this, 'addOptionsToUserEditProfile' ] );
		}
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param \WP_User $user
	 */
	public function addOptionsToUserProfile( $oUser ) {
		$oMC = $this->getMfaCon();
		$oWpUsers = Services::WpUsers();
		$providers = $oMC->getProvidersForUser( $oUser );
		if ( count( $providers ) > 0 ) {
			$rows = [];
			foreach ( $providers as $provider ) {
				$rows[ $provider::SLUG ] = $provider->renderUserProfileOptions( $oUser );
			}

			echo $oMC->getMod()
					 ->renderTemplate(
						 '/admin/user/profile/mfa/mfa_container.twig',
						 [
							 'is_my_user_profile'    => ( $oUser->ID == $oWpUsers->getCurrentWpUserId() ),
							 'i_am_valid_admin'      => $oMC->getCon()->isPluginAdmin(),
							 'user_to_edit_is_admin' => $oWpUsers->isUserAdmin( $oUser ),
							 'strings'               => [
								 'title'       => __( 'Multi-Factor Authentication', 'wp-simple-firewall' ),
								 'provided_by' => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), $oMC->getCon()
																											 ->getHumanName() )
							 ],
							 'mfa_rows'              => $rows,
						 ],
						 true
					 );
		}
	}


	/**
	 * ONLY TO BE HOOKED TO USER PROFILE EDIT
	 * @param \WP_User $user
	 */
	public function addOptionsToUserEditProfile( $user ) {
		$mfaCon = $this->getMfaCon();
		$con = $mfaCon->getCon();
		$WPU = Services::WpUsers();
		$pluginName = $con->getHumanName();

		$providers = array_map(
			function ( $provider ) {
				return $provider->getProviderName();
			},
			$mfaCon->getProvidersForUser( $user, true )
		);

		echo $mfaCon->getMod()
					->renderTemplate(
						'/admin/user/profile/mfa/remove_for_other_user.twig',
						[
							'flags'   => [
								'has_factors'      => count( $providers ) > 0,
								'is_admin_profile' => $WPU->isUserAdmin( $user ),
								'can_remove'       => $con->isPluginAdmin() || !$WPU->isUserAdmin( $user ),
							],
							'vars'    => [
								'user_id'          => $user->ID,
								'mfa_factor_names' => $providers,
							],
							'strings' => [
								'title'            => __( 'Multi-Factor Authentication', 'wp-simple-firewall' ),
								'provided_by'      => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), $pluginName ),
								'currently_active' => __( 'Currently active MFA Providers on this profile are' ),
								'remove_all'       => __( 'Remove All MFA Providers' ),
								'remove_all_from'  => __( 'Remove All MFA Providers From This User Profile' ),
								'no_providers'     => __( 'There are no MFA providers active on this user account.' ),
								'only_secadmin'    => sprintf( __( 'Only %s Security Admins may modify the MFA settings of another admin account.' ),
									$pluginName ),
								'authenticate'     => sprintf( __( 'You may authenticate with the %s Security Admin system and return here.' ),
									$pluginName ),
							],
						],
						true
					);
	}
}