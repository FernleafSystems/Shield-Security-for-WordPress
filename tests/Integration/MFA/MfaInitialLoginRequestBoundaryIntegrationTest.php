<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionRoutingController,
	Actions\FullPageDisplay\FullPageDisplayDynamic
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\{
	LoginRequestCapture,
	Provider\GoogleAuth
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class MfaInitialLoginRequestBoundaryIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $optionsSnapshot = [];
	private array $requestSnapshot = [];

	public function set_up() :void {
		parent::set_up();
		$this->requireDb( 'mfa' );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'enable_google_authenticator',
			'mfa_skip',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		RuntimeTestState::restoreOptions( [
			'enable_google_authenticator' => 'Y',
			'mfa_skip'                    => 0,
		], true );
	}

	public function tear_down() :void {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		RuntimeTestState::resetMfaProviderCache();
		parent::tear_down();
	}

	public function test_real_wp_login_normalizes_hostile_initial_render_values() :void {
		$user = $this->createMfaUser();

		$calls = $this->captureInitialLogin( $user, [
			'interim-login' => [ '1' ],
			'redirect_to'   => [ '/unsafe-shape' ],
			'rememberme'    => [ 'forever' ],
		] );

		$this->assertCount( 1, $calls );
		$renderData = $calls[ 0 ][ 'render_data' ] ?? [];
		$this->assertSame( $user->ID, $renderData[ 'user_id' ] ?? null );
		$this->assertIsString( $renderData[ 'plain_login_nonce' ] ?? null );
		$this->assertNotSame( '', $renderData[ 'plain_login_nonce' ] );
		$this->assertSame( '', $renderData[ 'interim_login' ] ?? null );
		$this->assertSame( '/wp-login.php', $renderData[ 'redirect_to' ] ?? null );
		$this->assertSame( '', $renderData[ 'rememberme' ] ?? null );
		$this->assertSame( '', $renderData[ 'cancel_href' ] ?? null );
		$this->assertSame( '', $renderData[ 'msg_error' ] ?? null );
		$this->assertSame( '', $renderData[ 'interim_message' ] ?? null );
		$this->assertTrue( $renderData[ 'include_body' ] ?? null );
		$this->assertCanonicalRenderData( $renderData );
		$this->assertSame( 0, \get_current_user_id() );
		$this->assertNotEmpty( $this->requireController()->user_metas->for( $user )->login_intents );
	}

	public function test_real_wp_login_preserves_exact_initial_render_tokens() :void {
		$user = $this->createMfaUser();

		$calls = $this->captureInitialLogin( $user, [
			'interim-login' => '1',
			'redirect_to'   => '/safe-target',
			'rememberme'    => 'forever',
		] );

		$this->assertCount( 1, $calls );
		$renderData = $calls[ 0 ][ 'render_data' ] ?? [];
		$this->assertSame( '1', $renderData[ 'interim_login' ] ?? null );
		$this->assertSame( '/safe-target', $renderData[ 'redirect_to' ] ?? null );
		$this->assertSame( 'forever', $renderData[ 'rememberme' ] ?? null );
		$this->assertCanonicalRenderData( $renderData );
	}

	/**
	 * @dataProvider finalSkipFilterProvider
	 */
	public function test_final_skip_filter_accepts_literal_boolean_only( $filterResult, bool $expectSkip ) :void {
		$user = $this->createMfaUser();
		$filter = static fn() => $filterResult;
		\add_filter( 'shield/2fa_skip', $filter, \PHP_INT_MAX );
		try {
			$calls = $this->captureInitialLogin( $user, [] );
		}
		finally {
			\remove_filter( 'shield/2fa_skip', $filter, \PHP_INT_MAX );
		}

		$this->assertCount( $expectSkip ? 0 : 1, $calls );
		$this->assertSame(
			$expectSkip,
			$this->requireController()->user_metas->for( $user )->login_intents === []
		);
	}

	public static function finalSkipFilterProvider() :array {
		return [
			'literal true'  => [ true, true ],
			'literal false' => [ false, false ],
			'truthy string' => [ '1', false ],
			'truthy int'    => [ 1, false ],
			'truthy array'  => [ [ true ], false ],
			'object'        => [ new \stdClass(), false ],
			'null'          => [ null, false ],
		];
	}

	private function createMfaUser() :\WP_User {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		TestDataFactory::insertMfaRecord( $user->ID, GoogleAuth::ProviderSlug(), [], [
			'unique_id' => 'JBSWY3DPEHPK3PXP',
			'label'     => 'Initial boundary GA',
		] );
		RuntimeTestState::resetMfaProviderCache();
		return $user;
	}

	private function assertCanonicalRenderData( array $renderData ) :void {
		$this->assertSame( [
			'user_id',
			'include_body',
			'plain_login_nonce',
			'interim_login',
			'redirect_to',
			'rememberme',
			'cancel_href',
			'msg_error',
			'interim_message',
		], \array_keys( $renderData ) );
		$this->assertIsInt( $renderData[ 'user_id' ] );
		$this->assertIsBool( $renderData[ 'include_body' ] );
		foreach ( \array_slice( $renderData, 2 ) as $value ) {
			$this->assertIsString( $value );
		}
	}

	private function captureInitialLogin( \WP_User $user, array $post ) :array {
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], $post );
		\wp_set_current_user( $user->ID );

		$con = $this->requireController();
		$originalRouter = $con->action_router;
		$calls = [];
		$con->action_router = new MfaInitialRenderCapture( $originalRouter, $calls );
		$hooks = $this->snapshotHooks( [ 'wp_login', 'set_logged_in_cookie' ] );
		$this->restoreHooks( [
			'wp_login'            => null,
			'set_logged_in_cookie' => null,
		] );
		try {
			( new LoginRequestCapture() )->execute();
			\do_action( 'wp_login', $user->user_login, $user );
		}
		finally {
			$con->action_router = $originalRouter;
			$this->restoreHooks( $hooks );
		}
		return $calls;
	}

	private function snapshotHooks( array $names ) :array {
		global $wp_filter;
		$snapshot = [];
		foreach ( $names as $name ) {
			$snapshot[ $name ] = $wp_filter[ $name ] ?? null;
		}
		return $snapshot;
	}

	private function restoreHooks( array $snapshot ) :void {
		global $wp_filter;
		foreach ( $snapshot as $name => $hook ) {
			if ( $hook === null ) {
				unset( $wp_filter[ $name ] );
			}
			else {
				$wp_filter[ $name ] = $hook;
			}
		}
	}
}

class MfaInitialRenderCapture {

	private object $inner;
	private array $calls;

	public function __construct( object $inner, array &$calls ) {
		$this->inner = $inner;
		$this->calls = &$calls;
	}

	public function action( string $classOrSlug, array $data = [], int $type = ActionRoutingController::ACTION_SHIELD ) {
		if ( $classOrSlug === FullPageDisplayDynamic::class ) {
			$this->calls[] = $data;
			return null;
		}
		return $this->inner->action( $classOrSlug, $data, $type );
	}

	public function render( string $action, array $actionData = [] ) :string {
		return $this->inner->render( $action, $actionData );
	}
}
