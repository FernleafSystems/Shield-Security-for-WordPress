<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Signals\NotBotHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\SilentCaptchaComplexity;
use FernleafSystems\Wordpress\Plugin\Shield\Profiles\Levels;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Core\Response;

class NotBotProductionGateIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private const PUBLIC_TEST_IP = '93.184.216.44';

	private const HOOKS = [
		'shield/custom_enqueue_assets',
		'shield/custom_localisations/components',
		'shield/can_run_antibot',
		'shield/notbot_js_insert',
		'shield/antibot_score_minimum',
		'shield/silent_captcha_bot_threshold',
		'shield/notbot_cookie_life',
	];

	private const OPTION_KEYS = [
		'antibot_minimum',
		'silentcaptcha_complexity',
		'enable_antibot_comments',
		'bot_protection_locations',
		'form_spam_providers',
		'user_form_providers',
		'enable_auto_integrations',
		'antibot_high_reputation_minimum',
	];

	private array $requestSnapshot = [];

	private array $optionsSnapshot = [];

	private array $cookiesSnapshot = [];

	private int $currentUserId = 0;

	private NotBotCookieCaptureResponse $responseCapture;

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( self::OPTION_KEYS );
		$this->cookiesSnapshot = $_COOKIE;
		$this->currentUserId = \get_current_user_id();
		$this->requireDb( 'ips' );
		$this->requireDb( 'bot_signals' );
		$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam', 'thirdparty_scan_users' ] );

		$this->responseCapture = new NotBotCookieCaptureResponse();
		ServicesState::mergeItems( [ 'service_response' => $this->responseCapture ] );
		\wp_set_current_user( 0 );
		$this->applyPublicRequestContext();
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		$_COOKIE = $this->cookiesSnapshot;
		\wp_set_current_user( $this->currentUserId );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	/**
	 * @dataProvider thresholdProvider
	 *
	 * @param mixed $input
	 */
	public function test_threshold_normalisation_controls_cookie_and_frontend_payloads(
		$input,
		int $expectedThreshold,
		bool $expectedToRun
	) :void {
		$this->setOptions( [ 'antibot_minimum' => $input ] );

		$this->assertSame( $expectedThreshold, $this->requireController()->opts->optGet( 'antibot_minimum' ) );
		$this->assertOutcome( $this->runHandler(), $expectedToRun, $expectedToRun );
	}

	public function thresholdProvider() :array {
		return [
			'below range becomes zero' => [ -1, 0, false ],
			'string zero is preserved' => [ '0', 0, false ],
			'integer zero is preserved' => [ 0, 0, false ],
			'minimum enabled score'     => [ 1, 1, true ],
			'default score'             => [ 45, 45, true ],
			'maximum enabled score'     => [ 99, 99, true ],
			'above range becomes max'   => [ 100, 99, true ],
		];
	}

	/**
	 * @dataProvider complexityProvider
	 */
	public function test_complexity_is_orthogonal_to_score_gate(
		string $complexity,
		int $score,
		bool $expectedToRun
	) :void {
		$this->setOptions( [
			'antibot_minimum'        => $score,
			'silentcaptcha_complexity' => $complexity,
		] );

		$this->assertOutcome( $this->runHandler(), $expectedToRun, $expectedToRun );
	}

	public function complexityProvider() :array {
		$cases = [];
		foreach ( SilentCaptchaComplexity::VALID as $complexity ) {
			$cases[ $complexity.' with score zero' ] = [ $complexity, 0, false ];
			$cases[ $complexity.' with enabled score' ] = [ $complexity, 45, true ];
		}
		return $cases;
	}

	/**
	 * @dataProvider consumerProvider
	 */
	public function test_consumer_settings_do_not_replace_the_explicit_score_gate(
		int $score,
		array $overrides,
		bool $expectedToRun
	) :void {
		$this->setOptions( \array_merge( $this->allConsumersOff(), $overrides, [
			'antibot_minimum' => $score,
		] ) );

		$this->assertOutcome( $this->runHandler(), $expectedToRun, $expectedToRun );
	}

	public function consumerProvider() :array {
		return [
			'all consumers off leaves positive score enabled' => [ 45, [], true ],
			'comment consumer' => [ 45, [ 'enable_antibot_comments' => 'Y' ], true ],
			'login consumer' => [ 45, [ 'bot_protection_locations' => [ 'login' ] ], true ],
			'registration consumer' => [ 45, [ 'bot_protection_locations' => [ 'register' ] ], true ],
			'password consumer' => [ 45, [ 'bot_protection_locations' => [ 'password' ] ], true ],
			'contact form consumer' => [ 45, [ 'form_spam_providers' => [ 'contactform7' ] ], true ],
			'WordPress user form consumer' => [ 45, [ 'user_form_providers' => [ 'wordpress' ] ], true ],
			'third-party checkout consumer' => [ 45, [ 'user_form_providers' => [ 'woocommerce' ] ], true ],
			'auto-integration consumer' => [ 45, [ 'enable_auto_integrations' => 'Y' ], true ],
			'high-reputation bypass' => [ 45, [ 'antibot_high_reputation_minimum' => 1000 ], true ],
			'all consumers on cannot bypass score zero' => [ 0, [
				'enable_antibot_comments'         => 'Y',
				'bot_protection_locations'         => [ 'login', 'register', 'password' ],
				'form_spam_providers'              => [ 'contactform7' ],
				'user_form_providers'              => [ 'wordpress', 'woocommerce' ],
				'enable_auto_integrations'         => 'Y',
				'antibot_high_reputation_minimum' => 1000,
			], false ],
		];
	}

	/**
	 * @dataProvider filterProvider
	 */
	public function test_filters_keep_existing_precedence_and_side_effect_scope(
		int $storedScore,
		array $filters,
		bool $expectedCookie,
		bool $expectedJavascript
	) :void {
		$this->setOptions( [ 'antibot_minimum' => $storedScore ] );

		$this->assertOutcome( $this->runHandler( $filters ), $expectedCookie, $expectedJavascript );
	}

	public function filterProvider() :array {
		return [
			'legacy threshold filter disables' => [ 45, [ 'shield/antibot_score_minimum' => 0 ], false, false ],
			'current threshold filter disables' => [ 45, [ 'shield/silent_captcha_bot_threshold' => 0 ], false, false ],
			'legacy threshold filter enables stored zero' => [ 0, [ 'shield/antibot_score_minimum' => 45 ], true, true ],
			'current threshold filter enables stored zero' => [ 0, [ 'shield/silent_captcha_bot_threshold' => 45 ], true, true ],
			'current threshold wins by enabling' => [ 45, [
				'shield/antibot_score_minimum'          => 0,
				'shield/silent_captcha_bot_threshold' => 45,
			], true, true ],
			'current threshold wins by disabling' => [ 0, [
				'shield/antibot_score_minimum'          => 45,
				'shield/silent_captcha_bot_threshold' => 0,
			], false, false ],
			'final can-run filter disables' => [ 45, [ 'shield/can_run_antibot' => false ], false, false ],
			'final can-run filter enables stored zero' => [ 0, [ 'shield/can_run_antibot' => true ], true, true ],
			'JavaScript filter leaves cookie intact' => [ 45, [ 'shield/notbot_js_insert' => false ], true, false ],
			'JavaScript filter cannot bypass score zero' => [ 0, [ 'shield/notbot_js_insert' => true ], false, false ],
		];
	}

	public function test_force_notbot_query_cannot_bypass_score_zero() :void {
		$this->setOptions( [ 'antibot_minimum' => 0 ] );

		$this->assertOutcome( $this->runHandler( [], [ 'force_notbot' => '1' ] ), false, false );
	}

	public function test_default_and_reset_keep_silentcaptcha_enabled() :void {
		$opts = $this->requireController()->opts;
		$this->assertSame( 45, $opts->optDefault( 'antibot_minimum' ) );

		$opts->optSet( 'antibot_minimum', 0 );
		$this->assertOutcome( $this->runHandler(), false, false );

		$opts->optReset( 'antibot_minimum' );
		$this->assertSame( 45, $opts->optGet( 'antibot_minimum' ) );
		$this->assertOutcome( $this->runHandler(), true, true );
	}

	/**
	 * @dataProvider securityProfileProvider
	 */
	public function test_security_profiles_keep_silentcaptcha_enabled( string $level, int $expectedScore ) :void {
		$profile = $this->requireController()->comps->security_profiles->buildForLevel( $level );
		$score = null;
		foreach ( $profile[ 'silentcaptcha' ][ 'opts' ] ?? [] as $option ) {
			if ( ( $option[ 'item_key' ] ?? '' ) === 'antibot_minimum' ) {
				$score = $option[ 'value' ] ?? null;
				break;
			}
		}

		$this->assertSame( $expectedScore, $score );
		$this->setOptions( [ 'antibot_minimum' => $score ] );
		$this->assertOutcome( $this->runHandler(), true, true );
	}

	public function securityProfileProvider() :array {
		return [
			'light'  => [ Levels::LIGHT, 25 ],
			'medium' => [ Levels::MEDIUM, 45 ],
			'strong' => [ Levels::STRONG, 65 ],
		];
	}

	private function allConsumersOff() :array {
		return [
			'enable_antibot_comments'         => 'N',
			'bot_protection_locations'         => [],
			'form_spam_providers'              => [],
			'user_form_providers'              => [],
			'enable_auto_integrations'         => 'N',
			'antibot_high_reputation_minimum' => 0,
		];
	}

	private function setOptions( array $values ) :void {
		$opts = $this->requireController()->opts;
		foreach ( $values as $key => $value ) {
			$opts->optSet( (string)$key, $value );
		}
	}

	private function runHandler( array $filters = [], array $query = [] ) :array {
		$this->applyPublicRequestContext( $query );
		$this->responseCapture->reset();
		unset( $_COOKIE[ $this->cookieName() ] );

		return $this->withIsolatedHooks( self::HOOKS, function () use ( $filters ) :array {
			foreach ( $filters as $hook => $value ) {
				\add_filter( (string)$hook, static fn() => $value, \PHP_INT_MAX );
			}

			( new NotBotHandler() )->execute();
			$assets = \apply_filters( 'shield/custom_enqueue_assets', [] );

			return [
				'cookies'    => $this->responseCapture->cookies(),
				'cookie_set' => \array_key_exists( $this->cookieName(), $_COOKIE ),
				'assets'     => $assets,
				'components' => \apply_filters( 'shield/custom_localisations/components', [] ),
			];
		} );
	}

	private function assertOutcome( array $outcome, bool $expectedCookie, bool $expectedJavascript ) :void {
		if ( $expectedCookie ) {
			$this->assertCount( 1, $outcome[ 'cookies' ] );
			$this->assertSame( $this->cookieName(), $outcome[ 'cookies' ][ 0 ][ 'key' ] );
			$this->assertTrue( $outcome[ 'cookie_set' ] );
		}
		else {
			$this->assertSame( [], $outcome[ 'cookies' ] );
			$this->assertFalse( $outcome[ 'cookie_set' ] );
		}

		if ( $expectedJavascript ) {
			$this->assertContains( 'silentcaptcha', $outcome[ 'assets' ] );
			$this->assertArrayHasKey( 'silentcaptcha', $outcome[ 'components' ] );
			$this->assertSame( 'silentcaptcha', $outcome[ 'components' ][ 'silentcaptcha' ][ 'key' ] ?? null );
		}
		else {
			$this->assertNotContains( 'silentcaptcha', $outcome[ 'assets' ] );
			$this->assertArrayNotHasKey( 'silentcaptcha', $outcome[ 'components' ] );
		}
	}

	private function cookieName() :string {
		return $this->requireController()->prefix( NotBotHandler::COOKIE_SLUG );
	}

	private function applyPublicRequestContext( array $query = [] ) :void {
		if ( \defined( 'LOGGED_IN_COOKIE' ) ) {
			unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		}
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => self::PUBLIC_TEST_IP,
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/',
			],
			$query,
			[],
			[
				'ip'                => self::PUBLIC_TEST_IP,
				'ip_is_public'      => true,
				'is_security_admin' => false,
				'path'              => '/',
				'wp_is_ajax'        => false,
			]
		);
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

class NotBotCookieCaptureResponse extends Response {

	private array $cookies = [];

	public function cookieSet( $key, $value, $duration = 3600, $path = null, $domain = null, $ssl = null ) {
		$this->cookies[] = [
			'key'      => (string)$key,
			'value'    => (string)$value,
			'duration' => (int)$duration,
		];
		$_COOKIE[ (string)$key ] = (string)$value;
		return true;
	}

	public function cookies() :array {
		return $this->cookies;
	}

	public function reset() :void {
		$this->cookies = [];
	}
}
