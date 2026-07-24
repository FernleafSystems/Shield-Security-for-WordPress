<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use Dolondro\GoogleAuthenticator\GoogleAuthenticator as OtpGenerator;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionRoutingController,
	Actions\FullPageDisplay\FullPageDisplayDynamic,
	Actions\MfaLoginVerifyStep
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\LoginRequestValues;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\GoogleAuth;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	ServicesState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\Controller as AdminNoticesController;
use FernleafSystems\Wordpress\Services\Core\{
	Response,
	Users
};

class MfaRequestBoundaryIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];
	private array $optionsSnapshot = [];

	private MfaBoundaryResponseCapture $responseCapture;
	private MfaBoundaryUsersSpy $usersSpy;

	public function set_up() :void {
		parent::set_up();
		$this->requireDb( 'mfa' );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [ 'enable_google_authenticator' ] );
		RuntimeTestState::restoreOptions( [ 'enable_google_authenticator' => 'Y' ], true );
		$this->responseCapture = new MfaBoundaryResponseCapture();
		$this->usersSpy = new MfaBoundaryUsersSpy();
		ServicesState::mergeItems( [
			'service_response' => $this->responseCapture,
			'service_wpusers'  => $this->usersSpy,
		] );
		\wp_set_current_user( 0 );
	}

	public function tear_down() :void {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_mfa_verify_wp_loaded_hook_rejects_malformed_identity_without_throwing() :void {
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], [
			'wp_user_id' => [ '42' ],
			'login_nonce' => [ 'nonce' ],
			'cancel' => [ '1' ],
			'cancel_href' => [ 'https://evil.example/' ],
			'skip_mfa' => [ 'Y' ],
			'interim-login' => [ '1' ],
			'redirect_to' => [ 'https://evil.example/' ],
			'rememberme' => [ 'forever' ],
		] );

		$this->requireController()->action_router->action( MfaLoginVerifyStep::class );
		\do_action( 'wp_loaded' );

		$this->assertSame( [ 'shield_msg' => 'no_user_login_intent' ], $this->responseCapture->loginParams );
		$this->assertSame( '', $this->responseCapture->redirectUrl );
		$this->assertSame( 'redirectToLogin', $this->responseCapture->method );
		$this->assertSame( 0, $this->usersSpy->lookupCount );
		$this->assertSame( 0, \get_current_user_id() );
	}

	public function test_malformed_nonce_is_rejected_before_user_lookup() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$this->seedLoginIntent( $user, 'valid-cancel-nonce' );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], [
			'wp_user_id'  => (string)$user->ID,
			'login_nonce' => [ 'valid-cancel-nonce' ],
			'cancel'      => '1',
		] );

		$this->usersSpy->lookupCount = 0;
		$this->runMfaVerifyStep();

		$this->assertSame( 0, $this->usersSpy->lookupCount );
		$this->assertNotEmpty( $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertSame( 'redirectToLogin', $this->responseCapture->method );
		$this->assertSame( [ 'shield_msg' => 'no_user_login_intent' ], $this->responseCapture->loginParams );
	}

	public function test_unknown_positive_user_id_performs_one_lookup_and_uses_generic_failure() :void {
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], [
			'wp_user_id'  => (string)\PHP_INT_MAX,
			'login_nonce' => 'valid-shape',
		] );

		$this->runMfaVerifyStep();

		$this->assertSame( 1, $this->usersSpy->lookupCount );
		$this->assertSame( 'redirectToLogin', $this->responseCapture->method );
		$this->assertSame( [ 'shield_msg' => 'no_user_login_intent' ], $this->responseCapture->loginParams );
	}

	public function test_invalid_nonce_cannot_cancel_or_clear_login_intent() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$this->seedLoginIntent( $user, 'valid-cancel-nonce' );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], [
			'wp_user_id' => (string)$user->ID,
			'login_nonce' => 'invalid-cancel-nonce',
			'cancel' => '1',
			'cancel_href' => '/safe-cancel',
		] );

		$this->usersSpy->lookupCount = 0;
		$this->runMfaVerifyStep();

		$this->assertSame( 1, $this->usersSpy->lookupCount );
		$this->assertNotEmpty( $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertSame( [ 'shield_msg' => 'no_user_login_intent' ], $this->responseCapture->loginParams );
		$this->assertSame( '', $this->responseCapture->redirectUrl );
	}

	public function test_valid_cancel_with_unsafe_destination_clears_intent_and_uses_login_fallback() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$this->seedLoginIntent( $user, 'valid-cancel-nonce' );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], [
			'wp_user_id' => (string)$user->ID,
			'login_nonce' => 'valid-cancel-nonce',
			'cancel' => '1',
			'cancel_href' => 'https://evil.example/cancel',
		] );

		$this->runMfaVerifyStep();

		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertSame( [], $this->responseCapture->loginParams );
		$this->assertSame( '', $this->responseCapture->redirectUrl );
		$this->assertSame( 'redirectToLogin', $this->responseCapture->method );
	}

	public function test_valid_cancel_with_safe_relative_destination_clears_intent_and_redirects() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$this->seedLoginIntent( $user, 'valid-cancel-nonce' );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], [
			'wp_user_id'  => (string)$user->ID,
			'login_nonce' => 'valid-cancel-nonce',
			'cancel'      => '1',
			'cancel_href' => '/safe-cancel',
		] );

		$this->runMfaVerifyStep();

		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertSame( 'redirect', $this->responseCapture->method );
		$this->assertSame( '/safe-cancel', $this->responseCapture->redirectUrl );
	}

	public function test_failed_verification_retry_emits_canonical_render_data() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$secret = 'JBSWY3DPEHPK3PXP';
		TestDataFactory::insertMfaRecord( $user->ID, GoogleAuth::ProviderSlug(), [], [
			'unique_id' => $secret,
			'label'     => 'Retry GA',
		] );
		RuntimeTestState::resetMfaProviderCache();
		$this->seedLoginIntent( $user, 'retry-nonce' );
		$provider = new GoogleAuth( $user );
		$validCode = ( new OtpGenerator() )->calculateCode( $secret );
		$invalidCode = ( $validCode[ 0 ] === '0' ? '1' : '0' ).\substr( $validCode, 1 );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], [
			'wp_user_id' => (string)$user->ID,
			'login_nonce' => 'retry-nonce',
			$provider->getLoginIntentFormParameter() => $invalidCode,
			'rememberme' => [ 'forever' ],
			'interim-login' => [ '1' ],
			'redirect_to' => [ '/invalid-shape' ],
			'cancel_href' => [ '/invalid-shape' ],
		] );

		$con = $this->requireController();
		$originalRouter = $con->action_router;
		$renderCalls = [];
		$con->action_router = new MfaBoundaryActionCapture( $originalRouter, $renderCalls );
		try {
			$this->runMfaVerifyStep();
		}
		finally {
			$con->action_router = $originalRouter;
		}

		$this->assertCount( 1, $renderCalls );
		$renderData = $renderCalls[ 0 ][ 'render_data' ] ?? [];
		$this->assertCanonicalRenderData( $renderData );
		$this->assertSame( $user->ID, $renderData[ 'user_id' ] );
		$this->assertTrue( $renderData[ 'include_body' ] );
		$this->assertSame( 'retry-nonce', $renderData[ 'plain_login_nonce' ] );
		$this->assertSame( '', $renderData[ 'interim_login' ] );
		$this->assertSame( '/wp-login.php', $renderData[ 'redirect_to' ] );
		$this->assertSame( '', $renderData[ 'rememberme' ] );
		$this->assertSame( '', $renderData[ 'cancel_href' ] );
		$this->assertNotSame( '', $renderData[ 'msg_error' ] );
		$this->assertSame( '', $renderData[ 'interim_message' ] );
	}

	public function test_valid_mfa_rejects_malformed_tokens_and_revalidates_final_redirect() :void {
		$this->captureShieldEvents();
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$secret = 'JBSWY3DPEHPK3PXP';
		TestDataFactory::insertMfaRecord( $user->ID, GoogleAuth::ProviderSlug(), [], [
			'unique_id' => $secret,
			'label' => 'Boundary GA',
		] );
		RuntimeTestState::resetMfaProviderCache();
		$this->seedLoginIntent( $user, 'valid-login-nonce' );
		$provider = new GoogleAuth( $user );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], [
			'wp_user_id' => (string)$user->ID,
			'login_nonce' => 'valid-login-nonce',
			$provider->getLoginIntentFormParameter() => ( new OtpGenerator() )->calculateCode( $secret ),
			'rememberme' => [ 'forever' ],
			'skip_mfa' => [ 'Y' ],
			'interim-login' => [ '1' ],
			'redirect_to' => '/requested-destination',
		] );
		$maliciousRedirect = static fn() :string => 'https://evil.example/filtered';
		$remembered = null;
		$authCookieObserver = static function ( int $length, int $userID, bool $remember ) use ( &$remembered ) :int {
			unset( $userID );
			$remembered = $remember;
			return $length;
		};
		\add_filter( 'login_redirect', $maliciousRedirect, 100 );
		\add_filter( 'auth_cookie_expiration', $authCookieObserver, 10, 3 );

		try {
			$this->runMfaVerifyStep();
		}
		finally {
			\remove_filter( 'login_redirect', $maliciousRedirect, 100 );
			\remove_filter( 'auth_cookie_expiration', $authCookieObserver, 10 );
		}

		global $interim_login;
		$this->assertFalse( $interim_login );
		$this->assertFalse( $remembered );
		$this->assertSame( '/wp-login.php', $this->responseCapture->redirectUrl );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertEmpty( $this->requireController()->user_metas->for( $user )->hash_loginmfa );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( '2fa_success' ) );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( '2fa_verify_success' ) );
	}

	public function test_valid_mfa_exact_tokens_enable_persistence_skip_and_interim_mode() :void {
		$this->captureShieldEvents();
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$secret = 'JBSWY3DPEHPK3PXP';
		TestDataFactory::insertMfaRecord( $user->ID, GoogleAuth::ProviderSlug(), [], [
			'unique_id' => $secret,
			'label'     => 'Exact token GA',
		] );
		RuntimeTestState::resetMfaProviderCache();
		$this->seedLoginIntent( $user, 'exact-token-nonce' );
		$provider = new GoogleAuth( $user );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], [
			'wp_user_id' => (string)$user->ID,
			'login_nonce' => 'exact-token-nonce',
			$provider->getLoginIntentFormParameter() => ( new OtpGenerator() )->calculateCode( $secret ),
			'rememberme' => 'forever',
			'skip_mfa' => 'Y',
			'interim-login' => '1',
			'redirect_to' => '/safe-success',
		] );

		$remembered = null;
		$authCookieObserver = static function ( int $length, int $userID, bool $remember ) use ( &$remembered ) :int {
			unset( $userID );
			$remembered = $remember;
			return $length;
		};
		$con = $this->requireController();
		$originalRouter = $con->action_router;
		$renderCalls = [];
		$con->action_router = new MfaBoundaryActionCapture( $originalRouter, $renderCalls );
		\add_filter( 'auth_cookie_expiration', $authCookieObserver, 10, 3 );
		try {
			$this->runMfaVerifyStep();
		}
		finally {
			\remove_filter( 'auth_cookie_expiration', $authCookieObserver, 10 );
			$con->action_router = $originalRouter;
		}

		global $interim_login;
		$this->assertTrue( $remembered );
		$this->assertTrue( $interim_login );
		$this->assertCount( 1, $renderCalls );
		$renderData = $renderCalls[ 0 ][ 'render_data' ] ?? [];
		$this->assertCanonicalRenderData( $renderData );
		$this->assertSame( $user->ID, $renderData[ 'user_id' ] );
		$this->assertFalse( $renderData[ 'include_body' ] );
		$this->assertSame( 'exact-token-nonce', $renderData[ 'plain_login_nonce' ] );
		$this->assertSame( '1', $renderData[ 'interim_login' ] );
		$this->assertSame( 'forever', $renderData[ 'rememberme' ] );
		$this->assertSame( '/safe-success', $renderData[ 'redirect_to' ] );
		$this->assertSame( '', $renderData[ 'cancel_href' ] );
		$this->assertSame( '', $renderData[ 'msg_error' ] );
		$this->assertNotSame( '', $renderData[ 'interim_message' ] );
		$this->assertSame( '/safe-success', $this->responseCapture->redirectUrl );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertNotEmpty( $this->requireController()->user_metas->for( $user )->hash_loginmfa );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( '2fa_success' ) );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( '2fa_verify_success' ) );
	}

	public function test_real_login_message_filter_normalizes_mixed_values() :void {
		( new AdminNoticesController() )->execute();

		$this->assertSame( '', \apply_filters( 'login_message', [ 'invalid' ] ) );
		$this->assertSame( '', \apply_filters( 'login_message', new \stdClass() ) );
		$this->assertSame( '12', \apply_filters( 'login_message', 12 ) );
		$this->assertSame( 'stringable', \apply_filters( 'login_message', new MfaBoundaryStringable() ) );
	}

	public function test_real_redirect_validation_preserves_wordpress_allowed_destinations() :void {
		$fallback = '/fallback';
		$this->assertSame( '/relative', LoginRequestValues::safeRedirect( '/relative', $fallback ) );
		$this->assertSame( \home_url( '/same-site' ), LoginRequestValues::safeRedirect( \home_url( '/same-site' ), $fallback ) );
		$this->assertSame( $fallback, LoginRequestValues::safeRedirect( 'https://evil.example/path', $fallback ) );

		$allowHost = static function ( array $hosts ) :array {
			$hosts[] = 'allowed.example';
			return $hosts;
		};
		\add_filter( 'allowed_redirect_hosts', $allowHost );
		try {
			$this->assertSame(
				'https://allowed.example/path',
				LoginRequestValues::safeRedirect( 'https://allowed.example/path', $fallback )
			);
		}
		finally {
			\remove_filter( 'allowed_redirect_hosts', $allowHost );
		}
	}

	private function runMfaVerifyStep() :void {
		$this->requireController()->action_router->action( MfaLoginVerifyStep::class );
		\do_action( 'wp_loaded' );
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

	private function seedLoginIntent( \WP_User $user, string $plainNonce ) :void {
		$hash = \wp_hash_password( $plainNonce.$user->ID );
		$this->requireController()->user_metas->for( $user )->login_intents = [
			$hash => [
				'hash' => $hash,
				'start' => \time(),
				'attempts' => 0,
			],
		];
	}
}

class MfaBoundaryResponseCapture extends Response {

	public array $loginParams = [];

	public string $redirectUrl = '';
	public string $method = '';
	public int $callCount = 0;

	public function redirect( $url, $queryParams = [], $safe = true, $bProtectAgainstInfiniteLoops = true ) {
		unset( $safe, $bProtectAgainstInfiniteLoops );
		$this->method = 'redirect';
		$this->callCount++;
		$this->redirectUrl = \is_string( $url ) ? $url : '';
		$this->loginParams = \is_array( $queryParams ) ? $queryParams : [];
	}

	public function redirectToLogin( $aQueryParams = [] ) {
		$this->method = 'redirectToLogin';
		$this->callCount++;
		$this->loginParams = \is_array( $aQueryParams ) ? $aQueryParams : [];
	}
}

class MfaBoundaryUsersSpy extends Users {

	public int $lookupCount = 0;

	public function getUserById( $userId ) {
		$this->lookupCount++;
		return parent::getUserById( $userId );
	}
}

class MfaBoundaryActionCapture {

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

class MfaBoundaryStringable {
	public function __toString() :string {
		return 'stringable';
	}
}
