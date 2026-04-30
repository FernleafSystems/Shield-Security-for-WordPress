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

	private bool $localisationRegistered = false;

	protected function run() {
		// shortcode for placing user authentication handling anywhere
		if ( self::con()->isPremiumActive() ) {
			add_shortcode( 'SHIELD_USER_PROFILE_MFA', fn() => $this->renderUserProfileMFA() );
		}

		if ( Services::WpUsers()->isUserLoggedIn() ) {
			if ( !is_admin() ) {
				add_action( 'wp', fn() => $this->enqueueFrontendAssetsIfRequired() );
			}

			if ( is_admin() && !Services::WpGeneral()->isAjax() ) {
				$setupPages = self::con()->opts->optGet( 'mfa_user_setup_pages' );
				$setupPages = \is_array( $setupPages ) ? $setupPages : [];
				$this->enqueueAssets( false, $setupPages );

				if ( \in_array( 'dedicated', $setupPages, true ) ) {
					$this->provideUserLoginSecurityPage();
				}

				if ( \in_array( 'profile', $setupPages, true ) ) {
					$this->provideUserProfileSections();
				}
			}
		}
	}

	private function provideUserLoginSecurityPage() {
		// @phpstan-ignore return.void
		add_action( 'admin_menu', fn() => add_users_page(
			sprintf( '%s - %s', __( 'My Login Security', 'wp-simple-firewall' ), self::con()->labels->Name ),
			__( 'Login Security', 'wp-simple-firewall' ),
			'read',
			'shield-login-security',
			function () {
				echo self::con()->action_router->render( Actions\Render\Components\UserMfa\ConfigPage::class );
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
				echo self::con()->action_router->render( Actions\Render\Components\UserMfa\ConfigEdit::class, [
					'user_id' => $user->ID
				] );
			}
		} );
	}

	private function enqueueFrontendAssetsIfRequired() :void {
		if ( $this->shouldEnqueueFrontendAssets() ) {
			$this->enqueueAssets( true );
		}
	}

	private function shouldEnqueueFrontendAssets() :bool {
		$shouldEnqueue = (bool)apply_filters( 'shield/mfa_profile/enqueue_frontend_assets', false );

		if ( !$shouldEnqueue && \function_exists( 'is_singular' ) && is_singular() ) {
			global $post;
			$shouldEnqueue = $post instanceof \WP_Post
							 && \function_exists( 'has_shortcode' )
							 && has_shortcode( (string)$post->post_content, 'SHIELD_USER_PROFILE_MFA' );
		}

		return $shouldEnqueue;
	}

	private function enqueueAssets( bool $isFrontend, array $setupPages = [] ) {

		add_filter( 'shield/custom_enqueue_assets', function ( array $assets, $hook = '' ) use ( $isFrontend, $setupPages ) {

			if ( $isFrontend || $this->isAdminHookWithConfiguredMfaUi( (string)$hook, $setupPages ) ) {
				$assets[] = 'userprofile';

				$this->registerUserProfileLocalisation();
			}

			return \array_unique( $assets );
		}, 10, $isFrontend ? 1 : 2 );
	}

	private function isAdminHookWithConfiguredMfaUi( string $hook, array $setupPages ) :bool {
		return ( \in_array( 'profile', $setupPages, true ) && \in_array( $hook, [ 'profile.php', 'user-edit.php' ], true ) )
			   || ( \in_array( 'dedicated', $setupPages, true ) && \preg_match( '#shield-login-security$#', $hook ) );
	}

	private function registerUserProfileLocalisation() :void {
		if ( $this->localisationRegistered ) {
			return;
		}
		$this->localisationRegistered = true;

		add_filter( 'shield/custom_localisations/components', function ( array $components ) {
			$components[ 'userprofile' ] = [
				'key'     => 'userprofile',
				'handles' => [
					'userprofile',
				],
				'data'    => function () {
					return [
						'ajax'    => [
							'mfa_remove_all' => ActionData::Build( Actions\MfaRemoveAll::class ),
							'render_profile' => ActionData::BuildAjaxRender( Actions\Render\Components\UserMfa\ConfigForm::class ),
						],
						'vars'    => [],
						'strings' => [
							'are_you_sure'         => __( 'Are you sure?', 'wp-simple-firewall' ),
							'cancel'               => __( 'Cancel', 'wp-simple-firewall' ),
							'confirm'              => __( 'Confirm', 'wp-simple-firewall' ),
							'continue'             => __( 'Continue', 'wp-simple-firewall' ),
							'dialog_alert_title'   => __( 'Notice', 'wp-simple-firewall' ),
							'dialog_confirm_title' => __( 'Confirm Action', 'wp-simple-firewall' ),
							'dialog_prompt_title'  => __( 'Information Required', 'wp-simple-firewall' ),
							'request_failed'       => __( 'Request Failed', 'wp-simple-firewall' ),
						],
					];
				},
			];
			return $components;
		} );
	}

	public function renderUserProfileMFA() :string {
		return '<div id="ShieldMfaUserProfileForm" class="shield_user_mfa_container"><p>Loading ...</p></div>';
	}
}
