<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionRoutingController,
	Actions\CaptureNotBot,
	Actions\CaptureNotBotAltcha
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\AltCha\AltChaV2Pbkdf2;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\SilentCaptchaComplexity;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
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
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [ 'silentcaptcha_complexity' ] );
		$this->requireController()->opts->optSet( 'silentcaptcha_complexity', 'low' );
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
	}

	public function test_none_complexity_prevents_direct_challenge_generation() :void {
		$this->requireController()->opts->optSet( 'silentcaptcha_complexity', SilentCaptchaComplexity::NONE );

		$this->expectException( \Exception::class );
		$this->requireController()->comps->altcha->generateChallenge();
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
		return $this->requireController()->action_router->action(
			CaptureNotBotAltcha::SLUG,
			$data,
			ActionRoutingController::ACTION_AJAX
		);
	}

	private function runNotBotAction( array $data, string $ip ) :\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\RoutedResponse {
		$this->applyAjaxRequestContext( $ip, $data );
		return $this->requireController()->action_router->action(
			CaptureNotBot::SLUG,
			$data,
			ActionRoutingController::ACTION_AJAX
		);
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
