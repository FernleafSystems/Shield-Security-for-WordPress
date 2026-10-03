<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionRoutingController,
	Actions\CaptureNotBot,
	Actions\CaptureNotBotAltcha
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\AltCha\AltChaV2Pbkdf2;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Signals\BotEventListener;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Signals\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Signals\NotBotHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\SilentCaptchaComplexity;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Services;

class AltChaV2ActionIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];
	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [ 'silentcaptcha_complexity', 'silentcaptcha_cookie_free' ] );
		$this->requireController()->opts->optSet( 'silentcaptcha_complexity', 'low' );
		$this->requireDb( 'bot_signals' );
		$this->requireController()->this_req->is_trusted_request = false;
		// The integration bootstrap does not install this request-time listener.
		( new BotEventListener() )->execute();
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_valid_v2_payload_fires_altcha_signal() :void {
		$this->captureShieldEvents();
		$routed = $this->runAltchaAction( $this->buildValidV2ActionData() );

		$this->assertTrue( (bool)( $routed->payload()[ 'success' ] ?? false ) );
		$this->assertContains( 'bottrack_altcha', $this->capturedBottrackEvents() );
		$this->assertSame( [ 'mode' => 'cookie', 'required' => [], 'exchange_valid' => true ], $routed->payload()[ 'notbot_state' ] );
	}

	public function test_v1_payload_cannot_fire_altcha_signal() :void {
		$this->captureShieldEvents();
		$routed = $this->runAltchaAction( ActionData::Build( CaptureNotBotAltcha::class, true, [
			'algorithm' => 'SHA-256',
			'challenge' => \hash( 'sha256', 'salt10' ),
			'maxnumber' => 100,
			'number'    => 10,
			'salt'      => 'salt',
			'signature' => 'legacy-signature',
			'expires'   => 2000000000,
		] ) );

		$this->assertTrue( (bool)( $routed->payload()[ 'success' ] ?? false ) );
		$this->assertNotContains( 'bottrack_altcha', $this->capturedBottrackEvents() );
		$this->assertContains( 'bottrack_notbot', $this->capturedBottrackEvents() );
	}

	public function test_tampered_v2_payload_cannot_fire_altcha_signal() :void {
		$data = $this->buildValidV2ActionData();
		$challenge = \json_decode( (string)$data[ 'altcha_challenge' ], true );
		$challenge[ 'parameters' ][ 'cost' ] = (int)$challenge[ 'parameters' ][ 'cost' ] + 1;
		$data[ 'altcha_challenge' ] = \json_encode( $challenge, \JSON_THROW_ON_ERROR );

		$this->captureShieldEvents();
		$routed = $this->runAltchaAction( $data );

		$this->assertTrue( (bool)( $routed->payload()[ 'success' ] ?? false ) );
		$this->assertNotContains( 'bottrack_altcha', $this->capturedBottrackEvents() );
	}

	public function test_expired_signed_v2_payload_cannot_fire_altcha_signal() :void {
		$this->captureShieldEvents();
		$routed = $this->runAltchaAction( $this->buildSignedV2ActionData( Services::Request()->ts() - 1 ) );

		$this->assertTrue( (bool)( $routed->payload()[ 'success' ] ?? false ) );
		$this->assertNotContains( 'bottrack_altcha', $this->capturedBottrackEvents() );
		$this->assertContains( 'bottrack_notbot', $this->capturedBottrackEvents() );
	}

	public function test_invalid_v2_solution_cannot_fire_altcha_signal() :void {
		$data = $this->buildSignedV2ActionData( Services::Request()->ts() + 300 );
		$solution = \json_decode( (string)$data[ 'altcha_solution' ], true );
		$solution[ 'derivedKey' ] = \str_repeat( '0', 64 );
		$data[ 'altcha_solution' ] = \json_encode( $solution, \JSON_THROW_ON_ERROR );

		$this->captureShieldEvents();
		$routed = $this->runAltchaAction( $data );

		$this->assertTrue( (bool)( $routed->payload()[ 'success' ] ?? false ) );
		$this->assertNotContains( 'bottrack_altcha', $this->capturedBottrackEvents() );
		$this->assertContains( 'bottrack_notbot', $this->capturedBottrackEvents() );
	}

	public function test_malformed_v2_payload_cannot_fire_altcha_signal() :void {
		$data = ActionData::Build( CaptureNotBotAltcha::class, true, [
			'altcha_version'   => '2',
			'altcha_challenge' => '{',
			'altcha_solution'  => '{',
		] );

		$this->captureShieldEvents();
		$routed = $this->runAltchaAction( $data );

		$this->assertTrue( (bool)( $routed->payload()[ 'success' ] ?? false ) );
		$this->assertNotContains( 'bottrack_altcha', $this->capturedBottrackEvents() );
		$this->assertContains( 'bottrack_notbot', $this->capturedBottrackEvents() );
	}

	public function test_none_complexity_suppresses_altcha_data_on_notbot_capture() :void {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );
		$ip = '198.51.100.221';
		TestDataFactory::insertBotSignal( $ip, [
			'notbot_at' => 0,
			'altcha_at' => 0,
		] );
		$this->requireController()->opts->optSet( 'silentcaptcha_complexity', SilentCaptchaComplexity::NONE );

		$routed = $this->runNotBotAction( ActionData::Build( CaptureNotBot::class, true ), $ip );

		$this->assertTrue( (bool)( $routed->payload()[ 'success' ] ?? false ) );
		$this->assertSame( [], $routed->payload()[ 'altcha_data' ] ?? null );
		$this->assertSame( [ 'mode' => 'cookie', 'required' => [], 'exchange_valid' => true ], $routed->payload()[ 'notbot_state' ] );
	}

	public function test_basic_response_state_is_built_after_signal_processing() :void {
		$ip = '93.184.216.231';
		$this->applyAjaxRequestContext( $ip );
		$this->seedCurrentIpBotSignal( [ 'notbot_at' => 0, 'altcha_at' => 0 ] );
		$routed = $this->runNotBotAction( ActionData::Build( CaptureNotBot::class, true ), $ip );
		$this->assertSame( [ 'mode' => 'cookie', 'required' => [ 'altcha' ], 'exchange_valid' => true ], $routed->payload()[ 'notbot_state' ] );
		$this->assertSame( '2', $routed->payload()[ 'altcha_data' ][ 'altcha_version' ] );
	}

	/** @dataProvider invalidExchangeProvider */
	public function test_invalid_exchange_never_becomes_valid_from_existing_signals( bool $fresh, bool $expired ) :void {
		$this->applyAjaxRequestContext( '93.184.216.232' );
		$this->seedCurrentIpBotSignal( [
			'notbot_at' => 0,
			'altcha_at' => $fresh ? Services::Request()->ts() : 0,
		] );
		$data = $this->buildSignedV2ActionData( Services::Request()->ts() + ( $expired ? -1 : 300 ) );
		if ( !$expired ) {
			$data[ 'altcha_solution' ] = '{}';
		}
		$routed = $this->runAltchaAction( $data );
		$this->assertTrue( $routed->payload()[ 'success' ] );
		$this->assertSame( [ 'mode' => 'cookie', 'required' => $fresh ? [] : [ 'altcha' ], 'exchange_valid' => false ], $routed->payload()[ 'notbot_state' ] );
	}

	public function invalidExchangeProvider() :array {
		return [ [ false, false ], [ true, false ], [ false, true ], [ true, true ] ];
	}

	public function test_none_complexity_prevents_direct_challenge_generation() :void {
		$this->requireController()->opts->optSet( 'silentcaptcha_complexity', SilentCaptchaComplexity::NONE );

		$this->expectException( \Exception::class );
		$this->requireController()->comps->altcha->generateChallenge();
	}

	/** @dataProvider failedWriteProvider */
	public function test_failed_signal_write_preserves_confirmed_state_and_recovers( bool $cookieFree, bool $altcha ) :void {
		$ip = '93.184.216.233';
		$con = $this->requireController();
		$con->opts->optSet( 'silentcaptcha_cookie_free', $cookieFree ? 'Y' : 'N' );
		$this->applyAjaxRequestContext( $ip );
		$now = Services::Request()->ts();
		$id = TestDataFactory::insertBotSignal( $ip, [ 'notbot_at' => 0, 'altcha_at' => $altcha ? 0 : $now ] );
		$owner = ( new BotSignalsRecord() )->setIP( $ip );
		$confirmed = $owner->retrieve();
		$before = $confirmed->getRawData();
		$data = $altcha ? $this->buildSignedV2ActionData( $now + 300 ) : ActionData::Build( CaptureNotBot::class, true );
		$slug = $altcha ? CaptureNotBotAltcha::SLUG : CaptureNotBot::SLUG;
		$this->captureShieldEvents();

		$payload = [];
		$this->withQueryFailure( $con->db_con->bot_signals->getTable(), 'UPDATE', function () use ( $slug, $data, &$payload ) {
			$payload = $this->routeCaptureAction( $slug, $data )->payload();
		} );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( [
			'mode' => $cookieFree ? 'cookie_free' : 'cookie',
			'required' => $altcha ? [ 'notbot', 'altcha' ] : [ 'notbot' ],
			'exchange_valid' => true,
		], $payload[ 'notbot_state' ] );
		$this->assertContains( $altcha ? 'bottrack_altcha' : 'bottrack_notbot', $this->capturedBottrackEvents() );
		$this->assertSame( $confirmed, $con->this_req->botsignal_record );
		$this->assertSame( $before, $confirmed->getRawData() );
		$saved = $con->db_con->bot_signals->getQuerySelector()->byId( $id );
		$this->assertSame( 0, $saved->notbot_at );
		$this->assertSame( $altcha ? 0 : $now, $saved->altcha_at );

		$this->assertSame( [], $this->routeCaptureAction( $slug, $data )->payload()[ 'notbot_state' ][ 'required' ] );
		$written = $con->this_req->botsignal_record;
		$this->assertGreaterThanOrEqual( $now, $written->notbot_at );
		$this->applyAjaxRequestContext( $ip );
		$freshRequest = $owner->retrieve();
		$this->assertSame( $id, $freshRequest->id );
		$this->assertSame( $written->notbot_at, $freshRequest->notbot_at );
		$this->assertSame( $written->altcha_at, $freshRequest->altcha_at );
	}

	public function failedWriteProvider() :array {
		return [ 'cookie basic' => [ false, false ], 'cookie ALTCHA' => [ false, true ],
			'free basic' => [ true, false ], 'free ALTCHA' => [ true, true ] ];
	}

	public function test_first_visitor_capture_updates_one_identified_record() :void {
		$ip = '93.184.216.234';
		$this->applyAjaxRequestContext( $ip );
		$con = $this->requireController();
		$ipRecord = TestDataFactory::createIpRecord( $ip );
		$this->assertSame( [], $con->db_con->bot_signals->getQuerySelector()->filterByIP( $ipRecord->id )->queryWithResult() );
		$before = \time();
		$basic = $this->routeCaptureAction( CaptureNotBot::SLUG, ActionData::Build( CaptureNotBot::class, true ) )->payload();
		$after = \time();
		$this->assertTrue( $basic[ 'success' ] );
		$first = $con->this_req->botsignal_record;
		$this->assertSame( $ip, $first->ip );
		$this->assertGreaterThan( 0, $first->id );
		$this->assertFalse( $first->modified );
		$this->assertGreaterThanOrEqual( $before, $first->created_at );
		$this->assertLessThanOrEqual( $after, $first->created_at );
		$this->assertGreaterThanOrEqual( $before, $first->updated_at );
		$this->assertLessThanOrEqual( $after, $first->updated_at );
		// The signed builder does not seed a second signal row.
		$altcha = $this->routeCaptureAction( CaptureNotBotAltcha::SLUG, $this->buildSignedV2ActionData( Services::Request()->ts() + 300 ) )->payload();
		$this->assertSame( [], $altcha[ 'notbot_state' ][ 'required' ] );
		$rows = $con->db_con->bot_signals->getQuerySelector()->filterByIP( $first->ip_ref )->queryWithResult();
		$this->assertCount( 1, $rows );
		$saved = \reset( $rows );
		$this->assertSame( $first->id, $saved->id );
		$this->assertSame( $first->created_at, $saved->created_at );
		$this->assertSame( $con->this_req->botsignal_record->updated_at, $saved->updated_at );
		$this->applyAjaxRequestContext( $ip );
		$fresh = ( new BotSignalsRecord() )->setIP( $ip )->retrieve();
		$this->assertSame( $saved->id, $fresh->id );
		$this->assertGreaterThan( 0, $fresh->notbot_at );
		$this->assertGreaterThan( 0, $fresh->altcha_at );
	}

	/** @dataProvider unavailableRecordProvider */
	public function test_unavailable_record_cannot_exempt_required_checks( bool $cookieFree, bool $ipFailure, string $operation ) :void {
		$ip = '93.184.216.235';
		$con = $this->requireController();
		$con->opts->optSet( 'silentcaptcha_cookie_free', $cookieFree ? 'Y' : 'N' );
		$this->applyAjaxRequestContext( $ip );
		if ( $operation === 'SELECT' ) {
			TestDataFactory::createIpRecord( $ip );
		}
		$this->resetIpCaches();
		$this->withQueryFailure( $con->db_con->{$ipFailure ? 'ips' : 'bot_signals'}->getTable(), $operation, function () {
			$payload = $this->routeCaptureAction( CaptureNotBot::SLUG, ActionData::Build( CaptureNotBot::class, true ) )->payload();
			$this->assertSame( [ 'notbot', 'altcha' ], $payload[ 'notbot_state' ][ 'required' ] );
			$this->assertEmpty( $this->requireController()->this_req->botsignal_record );
			$this->requireController()->opts->optSet( 'silentcaptcha_complexity', SilentCaptchaComplexity::NONE );
			$this->assertSame( [], ( new NotBotHandler() )->getRequiredSignals() );
			$this->requireController()->opts->optSet( 'silentcaptcha_complexity', SilentCaptchaComplexity::LOW );
			// Substitute only the external identity boundary; required-state logic and SQL remain real.
			$services = ServicesState::snapshot();
			$detector = $this->createMock( \FernleafSystems\Wordpress\Services\Utilities\Net\VisitorIpDetection::class );
			$detector->method( 'getIPIdentity' )->willReturn( 'googlebot' );
			$ipService = $this->createPartialMock( \FernleafSystems\Wordpress\Services\Utilities\IpUtils::class, [ 'getIpDetector' ] );
			$ipService->method( 'getIpDetector' )->willReturn( $detector );
			try {
				ServicesState::mergeItems( [ 'service_ip' => $ipService ] );
				$this->assertSame( [], ( new NotBotHandler() )->getRequiredSignals() );
			}
			finally {
				ServicesState::restore( $services );
			}
		} );
		$con->opts->optSet( 'silentcaptcha_complexity', SilentCaptchaComplexity::LOW );
		$this->assertSame( [ 'altcha' ], $this->routeCaptureAction( CaptureNotBot::SLUG, ActionData::Build( CaptureNotBot::class, true ) )->payload()[ 'notbot_state' ][ 'required' ] );
	}

	public function unavailableRecordProvider() :array {
		return [ 'cookie signal insert' => [ false, false, 'INSERT' ], 'free signal insert' => [ true, false, 'INSERT' ],
			'cookie IP insert' => [ false, true, 'INSERT' ], 'free IP insert' => [ true, true, 'INSERT' ],
			'cookie IP retrieval' => [ false, true, 'SELECT' ], 'free IP retrieval' => [ true, true, 'SELECT' ] ];
	}

	public function test_repeated_timestamp_is_a_noop_and_other_ip_does_not_replace_cache() :void {
		$ip = '93.184.216.236';
		$this->applyAjaxRequestContext( $ip );
		$owner = ( new BotSignalsRecord() )->setIP( $ip );
		$confirmed = $owner->updateSignalField( 'notbot_at' );
		$this->assertFalse( $confirmed->modified );
		$this->withQueryFailure( $this->requireController()->db_con->bot_signals->getTable(), 'UPDATE', function () use ( $owner, $confirmed ) {
			$this->assertSame( $confirmed->notbot_at, $owner->updateSignalField( 'notbot_at', $confirmed->notbot_at )->notbot_at );
		}, false );
		$current = $this->requireController()->this_req->botsignal_record;
		$other = ( new BotSignalsRecord() )->setIP( '93.184.216.237' )->updateSignalField( 'altcha_at' );
		$this->assertSame( $current, $this->requireController()->this_req->botsignal_record );
		$this->assertNotSame( $current->id, $other->id );
		$this->assertSame( 0, $current->altcha_at );
		$this->assertGreaterThan( 0, $this->requireController()->db_con->bot_signals->getQuerySelector()->byId( $other->id )->altcha_at );
	}

	private function withQueryFailure( string $table, string $operation, callable $exercise, bool $mustReach = true ) :void {
		$hits = 0;
		$filter = static function ( string $sql ) use ( $table, $operation, &$hits ) :string {
			if ( \preg_match( '/^\s*'.$operation.'\b/i', $sql ) && \strpos( $sql, '`'.$table.'`' ) !== false ) {
				$hits++;
				return 'SELECT * FROM `shield_missing_signal_failure_table`';
			}
			return $sql;
		};
		$wpdb = Services::WpDb()->loadWpdb();
		$previous = $wpdb->suppress_errors( true );
		\add_filter( 'query', $filter, 1000 );
		try {
			$exercise();
			$this->assertSame( $mustReach, $hits > 0, 'The targeted SQL operation must match the intended fault/no-op path.' );
		}
		finally {
			\remove_filter( 'query', $filter, 1000 );
			$wpdb->suppress_errors( $previous );
		}
	}

	private function routeCaptureAction( string $slug, array $data ) :\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\RoutedResponse {
		return $this->requireController()->action_router->action( $slug, $data, ActionRoutingController::ACTION_AJAX );
	}

	public function test_low_complexity_with_recent_page_signal_uses_low_profile_and_v2_contract() :void {
		$this->applyAjaxRequestContext( '198.51.100.222' );
		$this->seedCurrentIpBotSignal( [
			'frontpage_at' => Services::Request()->ts(),
		] );

		$data = $this->requireController()->comps->altcha->generateChallenge();
		$challenge = $this->decodeChallengeData( $data );

		$this->assertSame( AltChaV2Pbkdf2::VERSION, $data[ 'altcha_version' ] ?? '' );
		$this->assertIsString( $data[ 'altcha_challenge' ] ?? null );
		$this->assertSame( 1000, $challenge[ 'parameters' ][ 'cost' ] ?? null );
		foreach ( [ 'algorithm', 'challenge', 'maxnumber', 'number', 'salt', 'signature', 'expires' ] as $legacyKey ) {
			$this->assertArrayNotHasKey( $legacyKey, $data );
		}
	}

	public function test_medium_complexity_without_recent_page_signal_escalates_to_high_profile() :void {
		$this->requireController()->opts->optSet( 'silentcaptcha_complexity', SilentCaptchaComplexity::MEDIUM );
		$this->applyAjaxRequestContext( '198.51.100.223' );
		$this->seedCurrentIpBotSignal( [
			'frontpage_at' => Services::Request()->ts() - HOUR_IN_SECONDS - 1,
			'loginpage_at' => 0,
		] );

		$this->assertGeneratedChallengeCost( 5000 );
	}

	public function test_medium_complexity_with_recent_frontpage_signal_stays_medium_profile() :void {
		$this->requireController()->opts->optSet( 'silentcaptcha_complexity', SilentCaptchaComplexity::MEDIUM );
		$this->applyAjaxRequestContext( '198.51.100.224' );
		$this->seedCurrentIpBotSignal( [
			'frontpage_at' => Services::Request()->ts(),
			'loginpage_at' => 0,
		] );

		$this->assertGeneratedChallengeCost( 2500 );
	}

	public function test_medium_complexity_with_recent_loginpage_signal_stays_medium_profile() :void {
		$this->requireController()->opts->optSet( 'silentcaptcha_complexity', SilentCaptchaComplexity::MEDIUM );
		$this->applyAjaxRequestContext( '198.51.100.225' );
		$this->seedCurrentIpBotSignal( [
			'frontpage_at' => 0,
			'loginpage_at' => Services::Request()->ts(),
		] );

		$this->assertGeneratedChallengeCost( 2500 );
	}

	private function runAltchaAction( array $data ) :\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\RoutedResponse {
		$ip = (string)( self::con()->this_req->ip ?? '' );
		$this->applyAjaxRequestContext( $ip === '' ? '198.51.100.25' : $ip, $data );
		return $this->routeCaptureAction( CaptureNotBotAltcha::SLUG, $data );
	}

	private function runNotBotAction( array $data, string $ip ) :\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\RoutedResponse {
		$this->applyAjaxRequestContext( $ip, $data );
		return $this->routeCaptureAction( CaptureNotBot::SLUG, $data );
	}

	private function buildValidV2ActionData() :array {
		$this->applyAjaxRequestContext( '198.51.100.230' );
		$this->seedCurrentIpBotSignal( [
			'frontpage_at' => Services::Request()->ts(),
		] );

		$challengeData = $this->requireController()->comps->altcha->generateChallenge();
		$protocol = new AltChaV2Pbkdf2();
		$challenge = $protocol->decodeChallenge( (string)$challengeData[ 'altcha_challenge' ] );
		$solution = $this->solveChallenge( $protocol, $challenge );

		return ActionData::Build( CaptureNotBotAltcha::class, true, \array_merge( $challengeData, [
			'altcha_solution' => \json_encode( $solution, \JSON_THROW_ON_ERROR ),
		] ) );
	}

	private function buildSignedV2ActionData( int $expiresAt, int $counter = 7 ) :array {
		$protocol = new AltChaV2Pbkdf2();
		$hmacKey = wp_salt( 'shield-altcha' );
		$challenge = $protocol->buildChallenge(
			$hmacKey,
			$protocol->keySignatureSecret( $hmacKey ),
			2,
			$counter,
			$expiresAt,
			'000102030405060708090a0b0c0d0e0f',
			'101112131415161718191a1b1c1d1e1f'
		);
		$solution = [
			'counter'    => $counter,
			'derivedKey' => $protocol->deriveKeyHex( $challenge[ 'parameters' ], $counter ),
		];

		return ActionData::Build( CaptureNotBotAltcha::class, true, [
			'altcha_version'   => AltChaV2Pbkdf2::VERSION,
			'altcha_challenge' => $protocol->encodeChallenge( $challenge ),
			'altcha_solution'  => \json_encode( $solution, \JSON_THROW_ON_ERROR ),
		] );
	}

	/**
	 * @param array<string,mixed> $challenge
	 * @return array{counter:int,derivedKey:string}
	 */
	private function solveChallenge( AltChaV2Pbkdf2 $protocol, array $challenge ) :array {
		$parameters = $challenge[ 'parameters' ];
		$keyPrefix = \strtolower( (string)$parameters[ 'keyPrefix' ] );
		for ( $counter = 0; $counter <= 1000; $counter++ ) {
			$derivedKey = $protocol->deriveKeyHex( $parameters, $counter );
			if ( \strpos( $derivedKey, $keyPrefix ) === 0 ) {
				return [
					'counter'    => $counter,
					'derivedKey' => $derivedKey,
				];
			}
		}
		$this->fail( 'Unable to solve generated ALTCHA v2 challenge within low complexity bounds.' );
	}

	private function applyAjaxRequestContext( string $ip, array $post = [] ) :void {
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'     => $ip,
				'REQUEST_METHOD'  => 'POST',
				'REQUEST_URI'     => '/wp-admin/admin-ajax.php',
			],
			[],
			$post,
			[
				'ip'                => $ip,
				'ip_is_public'      => true,
				'is_security_admin' => false,
				'path'              => '/wp-admin/admin-ajax.php',
				'wp_is_ajax'        => true,
			]
		);
	}

	private function seedCurrentIpBotSignal( array $signals ) :void {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );

		TestDataFactory::insertBotSignal( (string)self::con()->this_req->ip, $signals );
	}

	private function assertGeneratedChallengeCost( int $expectedCost ) :void {
		$this->assertSame(
			$expectedCost,
			$this->decodeChallengeData( $this->requireController()->comps->altcha->generateChallenge() )[ 'parameters' ][ 'cost' ] ?? null
		);
	}

	private function decodeChallengeData( array $challengeData ) :array {
		$protocol = new AltChaV2Pbkdf2();
		return $protocol->decodeChallenge( (string)$challengeData[ 'altcha_challenge' ] );
	}

	/**
	 * @return list<string>
	 */
	private function capturedBottrackEvents() :array {
		$events = [];
		foreach ( $this->getCapturedEventsByKey( 'bottrack_multiple' ) as $event ) {
			$events = \array_merge( $events, $event[ 'meta' ][ 'data' ][ 'events' ] ?? [] );
		}
		return \array_values( \array_unique( $events ) );
	}
}
