<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class MfaProfilesController {

	use ExecOnce;
	use PluginControllerConsumer;

	private bool $rendered = false;

	private bool $isFrontend = false;

	protected function run() {
		// shortcode for placing user authentication handling anywhere
		if ( self::con()->isPremiumActive() ) {
			add_shortcode( 'SHIELD_USER_PROFILE_MFA', fn() => $this->renderUserProfileMFA() );
		}

		if ( Services::WpUsers()->isUserLoggedIn() ) {
			add_action( 'wp', fn() => $this->enqueueAssets( true ) );

			if ( is_admin() && !Services::WpGeneral()->isAjax() ) {
				$this->enqueueAssets( false );

				if ( \in_array( 'dedicated', self::con()->opts->optGet( 'mfa_user_setup_pages' ) ) ) {
					$this->provideUserLoginSecurityPage();
				}

				if ( \in_array( 'profile', self::con()->opts->optGet( 'mfa_user_setup_pages' ) ) ) {
					$this->provideUserProfileSections();
				}
			}
		}
	}

	private function provideUserLoginSecurityPage() {
		add_action( 'admin_menu', fn() => add_users_page(
			sprintf( '%s - %s', __( 'My Login Security', 'wp-simple-firewall' ), self::con()->labels->Name ),
			__( 'Login Security', 'wp-simple-firewall' ),
			'read',
			'shield-login-security',
			function () {
				echo self::con()->action_router->render( Actions\Render\Components\UserMfa\ConfigPage::SLUG );
			},
			4
		) );
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

		add_filter( 'shield/custom_enqueue_assets', function ( array $assets, $hook = '' ) {

			$isPageWithProfileDisplay = \preg_match( '#^(profile\.php|user-edit\.php|[a-z_\-]+shield-login-security)$#', (string)$hook );
			if ( $this->isFrontend || $isPageWithProfileDisplay ) {
				$assets[] = 'userprofile';

				add_filter( 'shield/custom_dequeues', fn( $assets ) => \array_merge( $assets, $this->rendered ? [] : [ 'userprofile' ] ) );

				add_filter( 'shield/custom_localisations/components', function ( array $components ) {
					$components[ 'userprofile' ] = [
						'key'     => 'userprofile',
						'handles' => [
							'userprofile',
						],
						'data'    => function () {
							$user = Services::WpUsers()->getCurrentWpUser();
							$providers = $user instanceof \WP_User ? self::con()->comps->mfa->getProvidersAvailableToUser( $user ) : [];
							return [
								'ajax'    => [
									'mfa_remove_all' => ActionData::Build( Actions\MfaRemoveAll::class ),
									'render_profile' => ActionData::BuildAjaxRender( Actions\Render\Components\UserMfa\ConfigForm::class ),
								],
								'vars'    => [
									'providers' => \array_map( function ( $provider ) {
										return $provider->getJavascriptVars();
									}, $providers )
								],
								'strings' => [
									'are_you_sure' => __( 'Are you sure?', 'wp-simple-firewall' )
								],
							];
						},
					];
					return $components;
				} );
			}

			return $assets;
		}, 10, $this->isFrontend ? 1 : 2 );
	}

	public function renderUserProfileMFA() :string {
		$this->rendered = true;
		return '<div id="ShieldMfaUserProfileForm" class="shield_user_mfa_container"><p>Loading ...</p></div>';
	}
}