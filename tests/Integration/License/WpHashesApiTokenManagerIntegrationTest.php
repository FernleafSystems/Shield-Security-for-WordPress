<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\License;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\OptsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\TracksOptionWrites;
use FernleafSystems\Wordpress\Services\Services;

class WpHashesApiTokenManagerIntegrationTest extends ShieldIntegrationTestCase {

	use TracksOptionWrites;

	private const TOKEN_ROUTE = '/v2/wphashes/token/';

	private array $optionsSnapshot = [];

	/** @var callable|null */
	private $httpStub = null;

	public function set_up() {
		parent::set_up();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [ 'wphashes_api_token' ] );
	}

	public function tear_down() {
		if ( \is_callable( $this->httpStub ) ) {
			\remove_filter( 'pre_http_request', $this->httpStub, 10 );
			$this->httpStub = null;
		}
		$this->stopTrackingOptionWrites();
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_attempt_window_is_persisted_before_token_request() :void {
		$this->enablePremiumCapabilities( [] );
		$this->seedToken( [
			'token'             => '',
			'expires_at'        => 0,
			'attempt_at'        => 0,
			'next_attempt_from' => 0,
			'valid_license'     => false,
		] );

		$newToken = \str_repeat( 'a', 40 );
		$requestCount = 0;
		$storedDuringRequest = [];
		$before = Services::Request()->ts();

		$this->stubWpHashesTokenRequest(
			function () use ( &$requestCount, &$storedDuringRequest, $newToken ) :array {
				$requestCount++;
				$storedDuringRequest = $this->rawStoredToken();

				return $this->httpResponse( [
					'token'         => $newToken,
					'expires_at'    => Services::Request()->ts() + \DAY_IN_SECONDS,
					'valid_license' => true,
				] );
			}
		);

		$this->assertSame( $newToken, $this->requireController()->comps->api_token->getToken() );
		$after = Services::Request()->ts();
		$finalToken = $this->rawStoredToken();

		$this->assertSame( 1, $requestCount );
		$this->assertAttemptWindow( $storedDuringRequest, $before, $after );
		$this->assertSame( $newToken, $finalToken[ 'token' ] ?? '' );
		$this->assertTrue( $finalToken[ 'valid_license' ] ?? false );
	}

	public function test_failed_token_request_is_throttled_after_runtime_reload() :void {
		$this->enablePremiumCapabilities( [] );
		$this->seedToken( [
			'token'             => '',
			'expires_at'        => 0,
			'attempt_at'        => 0,
			'next_attempt_from' => 0,
			'valid_license'     => false,
		] );

		$requestCount = 0;
		$before = Services::Request()->ts();

		$this->stubWpHashesTokenRequest(
			function () use ( &$requestCount ) :array {
				$requestCount++;
				return $this->httpResponse( [ 'error_code' => 1 ] );
			}
		);

		$this->assertSame( '', $this->requireController()->comps->api_token->getToken() );
		$after = Services::Request()->ts();
		$storedAfterFailure = $this->rawStoredToken();
		$this->assertSame( 1, $requestCount );
		$this->assertAttemptWindow( $storedAfterFailure, $before, $after );

		RuntimeTestState::resetOptionsRuntimeCache();

		$this->assertSame( '', $this->requireController()->comps->api_token->getToken() );
		$this->assertSame( 1, $requestCount );
	}

	public function test_fresh_token_does_not_request_or_write() :void {
		$this->enablePremiumCapabilities( [] );
		$token = \str_repeat( 'b', 40 );
		$this->seedToken( [
			'token'             => $token,
			'expires_at'        => Services::Request()->ts() + \DAY_IN_SECONDS,
			'attempt_at'        => Services::Request()->ts() - \HOUR_IN_SECONDS,
			'next_attempt_from' => Services::Request()->ts() - 1,
			'valid_license'     => true,
		] );

		$requestCount = 0;
		$this->stubWpHashesTokenRequest(
			function () use ( &$requestCount ) :array {
				$requestCount++;
				return $this->httpResponse( [ 'token' => \str_repeat( 'c', 40 ) ] );
			}
		);
		$this->startTrackingOptionWrites( [ $this->optsAllOptionName() ] );

		$this->assertSame( $token, $this->requireController()->comps->api_token->getToken() );

		$this->assertSame( 0, $requestCount );
		$this->assertOptionWasNotWritten( $this->optsAllOptionName() );
	}

	/**
	 * @param callable():array $responseBuilder
	 */
	private function stubWpHashesTokenRequest( callable $responseBuilder ) :void {
		if ( \is_callable( $this->httpStub ) ) {
			\remove_filter( 'pre_http_request', $this->httpStub, 10 );
		}

		$this->httpStub = static function ( $pre, array $args, string $url ) use ( $responseBuilder ) {
			unset( $args );
			return \str_contains( $url, self::TOKEN_ROUTE ) ? $responseBuilder() : $pre;
		};

		\add_filter( 'pre_http_request', $this->httpStub, 10, 3 );
	}

	private function seedToken( array $token ) :void {
		$this->requireController()
			 ->opts
			 ->optSet( 'wphashes_api_token', $token )
			 ->store();
		RuntimeTestState::resetOptionsRuntimeCache();
	}

	private function rawStoredToken() :array {
		$stored = \get_option( $this->optsAllOptionName(), [] );
		$this->assertIsArray( $stored );
		$this->assertIsArray( $stored[ 'values' ][ OptsHandler::TYPE_FREE ] ?? null );
		$this->assertIsArray( $stored[ 'values' ][ OptsHandler::TYPE_FREE ][ 'wphashes_api_token' ] ?? null );

		return $stored[ 'values' ][ OptsHandler::TYPE_FREE ][ 'wphashes_api_token' ];
	}

	private function optsAllOptionName() :string {
		return $this->requireController()->prefix( 'opts_all', '_' );
	}

	private function httpResponse( array $body ) :array {
		return [
			'headers'  => [],
			'body'     => (string)\wp_json_encode( $body ),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	private function assertAttemptWindow( array $token, int $before, int $after ) :void {
		$attemptAt = (int)( $token[ 'attempt_at' ] ?? 0 );
		$nextAttemptFrom = (int)( $token[ 'next_attempt_from' ] ?? 0 );

		$this->assertGreaterThanOrEqual( $before, $attemptAt );
		$this->assertLessThanOrEqual( $after, $attemptAt );
		$this->assertSame( $attemptAt + \HOUR_IN_SECONDS, $nextAttemptFrom );
	}
}
