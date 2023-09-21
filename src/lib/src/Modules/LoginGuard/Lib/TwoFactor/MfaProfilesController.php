<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class MfaProfilesController {

	use ExecOnce;
	use ModConsumer;

	private $rendered = false;

	private $isFrontend = false;

	protected function run() {
		// shortcode for placing user authentication handling anywhere
		if ( self::con()->isPremiumActive() ) {
			add_shortcode( 'SHIELD_USER_PROFILE_MFA', function ( $attributes ) {
				return $this->renderUserProfileMFA( \is_array( $attributes ) ? $attributes : [] );
			} );
		}

		if ( Services::WpUsers()->isUserLoggedIn() ) {
			add_action( 'wp', function () {
				$this->enqueueAssets( true );
			} );

			if ( is_admin() && !Services::WpGeneral()->isAjax() ) {
				$this->enqueueAssets( false );

				if ( \in_array( 'dedicated', $this->opts()->getOpt( 'mfa_user_setup_pages' ) ) ) {
					$this->provideUserLoginSecurityPage();
				}

				if ( \in_array( 'profile', $this->opts()->getOpt( 'mfa_user_setup_pages' ) ) ) {
					$this->provideUserProfileSections();
				}
			}
		}
	}

	private function provideUserLoginSecurityPage() {
		add_action( 'admin_menu', function () {
			add_users_page(
				sprintf( '%s - %s', __( 'My Login Security', 'wp-simple-firewall' ), self::con()->getHumanName() ),
				__( 'Login Security', 'wp-simple-firewall' ),
				'read',
				'shield-login-security',
				function () {
					echo self::con()->action_router->render( Actions\Render\Components\UserMfa\ConfigPage::SLUG );
				},
				4
			);
		} );
	}

	private function provideUserProfileSections() {
		// Standard WordPress User Editing their OWN profile.
		add_action( 'show_user_profile', function () {
			echo $this->renderUserProfileMFA();
		}, 7, 0 );

		// WordPress Admin Editing OTHER user profile.
		add_action( 'edit_user_profile', function ( $user ) {
			if ( $user instanceof \WP_User ) {
				$this->rendered = true;
				echo self::con()->action_router->render( Actions\Render\Components\UserMfa\ConfigEdit::SLUG, [
					'user_id' => $user->ID
				] );
			}
		} );
	}

	private function enqueueAssets( bool $isFrontend ) {
		$this->isFrontend = $isFrontend;
		add_filter( 'shield/custom_enqueues', function ( array $enqueues, $hook = '' ) {

			$isPageWithProfileDisplay = \preg_match( '#^(profile\.php|user-edit\.php|[a-z_\-]+shield-login-security)$#', (string)$hook );
			if ( $this->isFrontend || $isPageWithProfileDisplay ) {
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
					$user = Services::WpUsers()->getCurrentWpUser();
					$providers = $user instanceof \WP_User ?
						$this->mod()->getMfaController()->getProvidersAvailableToUser( $user ) : [];
					$localz[] = [
						'shield/userprofile',
						'shield_vars_userprofile',
						[
							'ajax'    => [
								'mfa_remove_all' => ActionData::Build( Actions\MfaRemoveAll::class ),
							],
							'vars'    => [
								'providers' => \array_map( function ( $provider ) {
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

	public function renderUserProfileMFA( array $attributes = [] ) :string {
		$this->rendered = true;
		return self::con()->action_router->render( Actions\Render\Components\UserMfa\ConfigForm::SLUG,
			\array_merge(
				[
					'title'    => __( 'Multi-Factor Authentication', 'wp-simple-firewall' ),
					'subtitle' => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ),
						self::con()->getHumanName() )
				],
				$attributes
			)
		);
	}
}