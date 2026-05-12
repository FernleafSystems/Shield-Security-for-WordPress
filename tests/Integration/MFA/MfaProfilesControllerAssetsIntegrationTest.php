<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionRoutingController,
	Actions\AjaxRender,
	Actions\Render\Components\UserMfa\ConfigForm
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\{
	MfaProfilesController,
	Provider\Provider2faInterface
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class MfaProfilesControllerAssetsIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'mfa_user_setup_pages',
		] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_logged_in_frontend_page_without_shortcode_does_not_enqueue_userprofile_or_load_mfa_handler() :void {
		$this->loginAsAdministrator();
		$this->requireController()->db_con->reset();

		$scenario = $this->runFrontendAssetScenario( 'Plain front-end content.' );

		$this->assertNotContains( 'userprofile', $scenario[ 'assets' ] );
		$this->assertArrayNotHasKey( 'userprofile', $scenario[ 'components' ] );
		$this->assertMfaHandlerNotLoaded();
	}

	public function test_logged_in_frontend_page_with_shortcode_enqueues_placeholder_and_lightweight_localisation() :void {
		$this->enablePremiumCapabilities();
		$this->loginAsAdministrator();
		$this->requireController()->db_con->reset();

		$scenario = $this->runFrontendAssetScenario( 'Before [SHIELD_USER_PROFILE_MFA] after.' );

		$this->assertContains( 'userprofile', $scenario[ 'assets' ] );
		$this->assertHtmlContainsMarker(
			'ShieldMfaUserProfileForm',
			\do_shortcode( '[SHIELD_USER_PROFILE_MFA]' ),
			'MFA shortcode'
		);
		$this->assertInitialLocalisationIsLightweight( $this->userProfileBootstrapData( $scenario[ 'components' ] ) );
		$this->assertMfaHandlerNotLoaded();
	}

	public function test_frontend_enqueue_filter_can_explicitly_opt_in_without_eager_provider_payload() :void {
		$this->loginAsAdministrator();
		$this->requireController()->db_con->reset();

		$forceEnqueue = static fn( bool $enqueue = false ) => true;
		\add_filter( 'shield/mfa_profile/enqueue_frontend_assets', $forceEnqueue );

		try {
			$scenario = $this->runFrontendAssetScenario( 'Plain front-end content.' );
		}
		finally {
			\remove_filter( 'shield/mfa_profile/enqueue_frontend_assets', $forceEnqueue );
		}

		$this->assertContains( 'userprofile', $scenario[ 'assets' ] );
		$this->assertInitialLocalisationIsLightweight( $this->userProfileBootstrapData( $scenario[ 'components' ] ) );
		$this->assertMfaHandlerNotLoaded();
	}

	public function test_initial_localisation_does_not_build_provider_javascript_payload() :void {
		$this->enablePremiumCapabilities();
		$this->loginAsAdministrator();
		$this->requireController()->db_con->reset();
		AsyncMfaProfileTestProvider::$javascriptVarsCalls = 0;

		$providerFilter = static fn( array $providers ) :array => [ AsyncMfaProfileTestProvider::class ];
		\add_filter( 'shield/2fa_providers', $providerFilter );

		try {
			$scenario = $this->runFrontendAssetScenario( 'Before [SHIELD_USER_PROFILE_MFA] after.' );
			$this->assertInitialLocalisationIsLightweight( $this->userProfileBootstrapData( $scenario[ 'components' ] ) );
		}
		finally {
			\remove_filter( 'shield/2fa_providers', $providerFilter );
		}

		$this->assertSame( 0, AsyncMfaProfileTestProvider::$javascriptVarsCalls );
		$this->assertMfaHandlerNotLoaded();
	}

	public function test_admin_profile_config_enqueues_only_profile_hooks() :void {
		$profile = $this->runAdminAssetScenario( [ 'profile' ], 'profile.php' );
		$userEdit = $this->runAdminAssetScenario( [ 'profile' ], 'user-edit.php' );
		$dedicated = $this->runAdminAssetScenario( [ 'profile' ], 'users_page_shield-login-security' );

		$this->assertContains( 'userprofile', $profile[ 'assets' ] );
		$this->assertInitialLocalisationIsLightweight( $this->userProfileBootstrapData( $profile[ 'components' ] ) );
		$this->assertContains( 'userprofile', $userEdit[ 'assets' ] );
		$this->assertInitialLocalisationIsLightweight( $this->userProfileBootstrapData( $userEdit[ 'components' ] ) );
		$this->assertNotContains( 'userprofile', $dedicated[ 'assets' ] );
		$this->assertArrayNotHasKey( 'userprofile', $dedicated[ 'components' ] );
	}

	public function test_admin_dedicated_config_enqueues_only_dedicated_hook() :void {
		$dedicated = $this->runAdminAssetScenario( [ 'dedicated' ], 'users_page_shield-login-security' );
		$profile = $this->runAdminAssetScenario( [ 'dedicated' ], 'profile.php' );
		$userEdit = $this->runAdminAssetScenario( [ 'dedicated' ], 'user-edit.php' );

		$this->assertContains( 'userprofile', $dedicated[ 'assets' ] );
		$this->assertInitialLocalisationIsLightweight( $this->userProfileBootstrapData( $dedicated[ 'components' ] ) );
		$this->assertNotContains( 'userprofile', $profile[ 'assets' ] );
		$this->assertArrayNotHasKey( 'userprofile', $profile[ 'components' ] );
		$this->assertNotContains( 'userprofile', $userEdit[ 'assets' ] );
		$this->assertArrayNotHasKey( 'userprofile', $userEdit[ 'components' ] );
	}

	public function test_ajax_render_profile_produces_provider_html_and_javascript_payload() :void {
		$userID = $this->loginAsAdministrator();
		AsyncMfaProfileTestProvider::$javascriptVarsCalls = 0;
		$providerFilter = static fn( array $providers ) :array => [ AsyncMfaProfileTestProvider::class ];
		\add_filter( 'shield/2fa_providers', $providerFilter );

		try {
			$request = ActionData::BuildAjaxRender( ConfigForm::class );
			$this->applyCurrentShieldAjaxRequest( $request, false );
			$payload = $this->requireController()->action_router->action(
				AjaxRender::SLUG,
				$request,
				ActionRoutingController::ACTION_AJAX
			)->payload();
		}
		finally {
			\remove_filter( 'shield/2fa_providers', $providerFilter );
		}

		$this->assertNotSame( '', \trim( (string)( $payload[ 'html' ] ?? '' ) ) );
		$this->assertArrayHasKey( 'render_data', $payload );
		$renderData = $payload[ 'render_data' ];
		$this->assertArrayHasKey( 'content', $renderData );
		$this->assertArrayHasKey( 'providers', $renderData[ 'content' ] );
		$this->assertArrayHasKey( AsyncMfaProfileTestProvider::ProviderSlug(), $renderData[ 'content' ][ 'providers' ] );
		$this->assertNotSame(
			'',
			\trim( (string)$renderData[ 'content' ][ 'providers' ][ AsyncMfaProfileTestProvider::ProviderSlug() ] )
		);
		$this->assertArrayHasKey( 'vars', $renderData );
		$this->assertArrayHasKey( 'providers', $renderData[ 'vars' ] );
		$this->assertArrayHasKey( AsyncMfaProfileTestProvider::ProviderSlug(), $renderData[ 'vars' ][ 'providers' ] );
		$providerVars = $renderData[ 'vars' ][ 'providers' ][ AsyncMfaProfileTestProvider::ProviderSlug() ];
		$this->assertSame( 1, AsyncMfaProfileTestProvider::$javascriptVarsCalls );
		$this->assertArrayHasKey( 'vars', $providerVars );
		$this->assertArrayHasKey( 'user_id', $providerVars[ 'vars' ] );
		$this->assertSame( $userID, $providerVars[ 'vars' ][ 'user_id' ] );
		$this->assertArrayHasKey( 'flags', $providerVars );
		$this->assertArrayHasKey( 'is_available', $providerVars[ 'flags' ] );
		$this->assertSame( true, $providerVars[ 'flags' ][ 'is_available' ] );
	}

	private function runFrontendAssetScenario( string $postContent ) :array {
		$postID = self::factory()->post->create( [
			'post_content' => $postContent,
		] );
		$this->go_to( \get_permalink( $postID ) );

		return $this->withIsolatedHooks( [
			'wp',
			'shield/custom_enqueue_assets',
			'shield/custom_localisations/components',
		], function () {
			( new MfaProfilesController() )->execute();
			\do_action( 'wp' );
			$assets = \apply_filters( 'shield/custom_enqueue_assets', [] );
			return [
				'assets'     => $assets,
				'components' => \apply_filters( 'shield/custom_localisations/components', [] ),
			];
		} );
	}

	private function runAdminAssetScenario( array $setupPages, string $hook ) :array {
		$this->loginAsAdministrator();
		$this->requireController()->opts->optSet( 'mfa_user_setup_pages', $setupPages )->store();

		return $this->withAdminScreen( function () use ( $hook ) {
			return $this->withIsolatedHooks( [
				'admin_menu',
				'show_user_profile',
				'edit_user_profile',
				'shield/custom_enqueue_assets',
				'shield/custom_localisations/components',
			], function () use ( $hook ) {
				( new MfaProfilesController() )->execute();
				$assets = \apply_filters( 'shield/custom_enqueue_assets', [], $hook );
				return [
					'assets'     => $assets,
					'components' => \apply_filters( 'shield/custom_localisations/components', [] ),
				];
			} );
		} );
	}

	private function assertInitialLocalisationIsLightweight( array $data ) :void {
		$this->assertArrayHasKey( 'ajax', $data );
		$this->assertArrayHasKey( 'render_profile', $data[ 'ajax' ] );
		$this->assertArrayHasKey( 'mfa_remove_all', $data[ 'ajax' ] );
		$this->assertArrayHasKey( 'vars', $data );
		$this->assertArrayNotHasKey( 'providers', $data[ 'vars' ] );
	}

	private function userProfileBootstrapData( array $components ) :array {
		$this->assertArrayHasKey( 'userprofile', $components );
		$this->assertArrayHasKey( 'data', $components[ 'userprofile' ] );
		$data = $components[ 'userprofile' ][ 'data' ];
		return \is_callable( $data ) ? $data() : $data;
	}

	private function assertMfaHandlerNotLoaded() :void {
		$handlers = $this->requireController()->db_con->getHandlers();
		$this->assertNull( $handlers[ 'mfa' ][ 'handler' ] );
	}

	private function withAdminScreen( callable $callback ) {
		global $current_screen;

		$screenSnapshot = $current_screen ?? null;
		\set_current_screen( 'dashboard' );

		try {
			return $callback();
		}
		finally {
			$current_screen = $screenSnapshot;
		}
	}

	private function withIsolatedHooks( array $hookNames, callable $callback ) {
		global $wp_filter;

		$snapshot = [];
		foreach ( $hookNames as $hookName ) {
			$snapshot[ $hookName ] = $wp_filter[ $hookName ] ?? null;
			unset( $wp_filter[ $hookName ] );
		}

		try {
			return $callback();
		}
		finally {
			foreach ( $hookNames as $hookName ) {
				if ( $snapshot[ $hookName ] === null ) {
					unset( $wp_filter[ $hookName ] );
				}
				else {
					$wp_filter[ $hookName ] = $snapshot[ $hookName ];
				}
			}
		}
	}
}

class AsyncMfaProfileTestProvider implements Provider2faInterface {

	public static int $javascriptVarsCalls = 0;

	private \WP_User $user;

	public function __construct( \WP_User $user ) {
		$this->user = $user;
	}

	public static function ProviderEnabled() :bool {
		return true;
	}

	public static function ProviderSlug() :string {
		return 'asyncprobe';
	}

	public static function ProviderName() :string {
		return 'Async Probe';
	}

	public function getUser() :\WP_User {
		return $this->user;
	}

	public function getProviderName() :string {
		return self::ProviderName();
	}

	public function isProviderStandalone() :bool {
		return true;
	}

	public function validateLoginIntent( string $hashedLoginNonce ) :bool {
		return true;
	}

	public function isProfileActive() :bool {
		return false;
	}

	public function isProviderAvailableToUser() :bool {
		return true;
	}

	public function isProviderEnabled() :bool {
		return true;
	}

	public function isEnforced() :bool {
		return false;
	}

	public function removeFromProfile() :void {
	}

	public function renderUserProfileConfigFormField() :string {
		return '<div data-mfa-provider="'.self::ProviderSlug().'"></div>';
	}

	public function renderLoginIntentFormField( string $format ) :string {
		return '';
	}

	public function setUser( \WP_User $user ) {
		$this->user = $user;
		return $this;
	}

	public function getJavascriptVars() :array {
		self::$javascriptVarsCalls++;
		return [
			'flags' => [
				'is_available' => true,
			],
			'vars'  => [
				'user_id' => $this->user->ID,
			],
		];
	}
}
