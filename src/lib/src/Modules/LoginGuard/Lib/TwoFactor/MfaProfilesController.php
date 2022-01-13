<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Services\Services;

class MfaProfilesController {

	use MfaControllerConsumer;
	use ExecOnce;

	private $rendered = false;

	private $isFrontend = false;

	protected function run() {
		$this->addHooks();
		if ( Services::WpUsers()->isUserLoggedIn() ) {
			add_action( 'wp', function () {
				$this->enqueueAssets( true );
			} );
			add_action( 'admin_init', function () {
				$this->enqueueAssets( false );
			} );
		}
	}

	private function enqueueAssets( bool $isFrontend ) {
		$this->isFrontend = $isFrontend;
		add_filter( 'shield/custom_enqueues', function ( array $enqueues, $hook = '' ) {

			if ( $this->isFrontend || in_array( $hook, [ 'profile.php', 'user-edit.php' ] ) ) {
				$enqueues[ Enqueue::JS ][] = 'shield/userprofile';
				$enqueues[ Enqueue::CSS ][] = 'shield/dialog';
				$enqueues[ Enqueue::CSS ][] = 'shield/userprofile';

				add_filter( 'shield/custom_dequeues', function ( $assets ) {
					if ( !$this->rendered ) {
						$assets[ Enqueue::JS ][] = 'shield/userprofile';
						$assets[ Enqueue::CSS ][] = 'shield/dialog';
						$assets[ Enqueue::CSS ][] = 'shield/userprofile';
					}
					return $assets;
				} );

				add_filter( 'shield/custom_localisations', function ( array $localz ) {
					$mfaCon = $this->getMfaCon();
					$user = Services::WpUsers()->getCurrentWpUser();
					$providers = $user instanceof \WP_User ? $mfaCon->getProvidersForUser( $user ) : [];
					$localz[] = [
						'shield/userprofile',
						'shield_vars_userprofile',
						[
							'ajax'    => [
								'mfa_remove_all' => $mfaCon->getMod()->getAjaxActionData( 'mfa_remove_all' )
							],
							'vars'    => [
								'providers' => array_map( function ( $provider ) {
									return $provider->getJavascriptVars();
								}, $providers )
							],
							'strings' => [
								'are_you_sure' => __( 'Are you sure?', 'wp-simple-firewall' )
							],
						]
					];
					return $localz;
				} );
			}

			return $enqueues;
		}, 10, $this->isFrontend ? 1 : 2 );
	}

	private function loadUserProfileMFA( array $attributes = [] ) :string {
		$this->rendered = true;
		return ( new Profiles\RenderCustomForms() )
			->setMfaController( $this->getMfaCon() )
			->setWpUser( Services::WpUsers()->getCurrentWpUser() )
			->render( $attributes );
	}

	private function addHooks() {

		// shortcode for placing user authentication handling anywhere
		if ( $this->getMfaCon()->getCon()->isPremiumActive() ) {
			add_shortcode( 'SHIELD_USER_PROFILE_MFA', function ( $attributes ) {
				return $this->loadUserProfileMFA( is_array( $attributes ) ? $attributes : [] );
			} );
		}

		// Standard WordPress User Profile Editing
		add_action( 'show_user_profile', function () {
			$this->addOptionsToUserProfile();
		}, 7, 0 );
		add_action( 'edit_user_profile', function ( $user ) {
			if ( $user instanceof \WP_User ) {
				$this->addOptionsToUserEditProfile( $user );
			}
		} );
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 */
	public function addOptionsToUserProfile() {
		echo $this->loadUserProfileMFA( [
			'title'=>__( 'Multi-Factor Authentication', 'wp-simple-firewall' ),
			'subtitle' => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ),
				$this->getMfaCon()->getCon()->getHumanName() )
		] );
	}

	/**
	 * ONLY TO BE HOOKED TO USER PROFILE EDIT
	 */
	public function addOptionsToUserEditProfile( \WP_User $user ) {
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
		$this->rendered = true;

		echo $mfaCon->getMod()->renderTemplate( '/admin/user/profile/mfa/remove_for_other_user.twig', [
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
				'remove_warning'   => __( "Certain providers may not be removed if they're enforced." ),
				'no_providers'     => __( 'There are no MFA providers active on this user account.' ),
				'only_secadmin'    => sprintf( __( 'Only %s Security Admins may modify the MFA settings of another admin account.' ),
					$pluginName ),
				'authenticate'     => sprintf( __( 'You may authenticate with the %s Security Admin system and return here.' ),
					$pluginName ),
			],
		] );
	}
}