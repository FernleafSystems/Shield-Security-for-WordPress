<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class UserProfile {

	use MfaControllerConsumer;

	public function run() {
		if ( is_admin() ) {
			add_action( 'show_user_profile', [ $this, 'addOptionsToUserProfile' ] );
			add_action( 'personal_options_update', [ $this, 'handleUserProfileSubmit' ] );
			if ( $this->getMfaCon()->getCon()->isPluginAdmin() ) {
				add_action( 'edit_user_profile', [ $this, 'addOptionsToUserEditProfile' ] );
				add_action( 'edit_user_profile_update', [ $this, 'handleEditOtherUserProfileSubmit' ] );
			}
		}
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param \WP_User $oUser
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
	 * This MUST only ever be hooked into when the User is looking at their OWN profile,
	 * so we can use "current user" functions.  Otherwise we need to be careful of mixing up users.
	 * @param int $nSavingUserId
	 */
	public function handleUserProfileSubmit( $nSavingUserId ) {
		$oUser = Services::WpUsers()->getUserById( $nSavingUserId );
		foreach ( $this->getMfaCon()->getProvidersForUser( $oUser ) as $oProvider ) {
			$oProvider->handleUserProfileSubmit( $oUser );
		}
	}

	/**
	 * ONLY TO BE HOOKED TO USER PROFILE EDIT
	 * @param \WP_User $oUser
	 */
	public function addOptionsToUserEditProfile( $oUser ) {
		$this->addOptionsToUserProfile( $oUser );
	}

	/**
	 * The only thing we can do is REMOVE Google Authenticator from an account that is not our own
	 * But, only admins can do this.  If Security Admin feature is enabled, then only they can do it.
	 * @param int $nSavingUserId
	 */
	public function handleEditOtherUserProfileSubmit( $nSavingUserId ) {
		$oUser = Services::WpUsers()->getUserById( $nSavingUserId );
		foreach ( $this->getMfaCon()->getProvidersForUser( $oUser ) as $oProvider ) {
			$oProvider->handleEditOtherUserProfileSubmit( $oUser );
		}
	}
}