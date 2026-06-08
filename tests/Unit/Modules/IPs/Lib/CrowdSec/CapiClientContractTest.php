<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\IPs\Lib\CrowdSec;

use CrowdSec\CapiClient\Client\CapiHandler\CapiHandlerInterface;
use CrowdSec\CapiClient\ClientException;
use CrowdSec\CapiClient\Constants;
use CrowdSec\CapiClient\Storage\StorageInterface;
use CrowdSec\CapiClient\Watcher;
use CrowdSec\Common\Client\HttpMessage\Request;
use CrowdSec\Common\Client\HttpMessage\Response;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Capi\RequestHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Capi\Storage;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Signals\PushSignalsToCS;
use PHPUnit\Framework\TestCase;

class CapiClientContractTest extends TestCase {

	public function test_shield_push_limit_matches_capi_client_batch_size() :void {
		$this->assertSame( 50, PushSignalsToCS::LIMIT );
	}

	public function test_shield_adapters_implement_capi_client_interfaces() :void {
		$this->assertTrue( \is_subclass_of( Storage::class, StorageInterface::class ) );
		$this->assertTrue( \is_subclass_of( RequestHandler::class, CapiHandlerInterface::class ) );
	}

	public function test_push_signals_reindexes_batches_and_returns_final_response() :void {
		$handler = new CapiContractRequestHandler( [
			[ 200, '{"batch":1}' ],
			[ 200, '{"batch":2}' ],
		] );
		$signals = [];
		for ( $i = 1; $i <= 51; $i++ ) {
			$signals[ 100 + $i ] = [ 'signal_id' => $i ];
		}

		$response = $this->watcher( $handler )->pushSignals( $signals );

		$this->assertSame( [ 'batch' => 2 ], $response );
		$this->assertCount( 2, $handler->requests );

		$first = $handler->requests[ 0 ];
		$this->assertSame( 'POST', $first->getMethod() );
		$this->assertSame( 'https://api.dev.crowdsec.net/v3/signals', $first->getUri() );
		$this->assertSame( 'Bearer test-token', $first->getHeaders()[ 'Authorization' ] );
		$this->assertSame( \range( 0, 49 ), \array_keys( $first->getParams() ) );
		$this->assertSame( 1, $first->getParams()[ 0 ][ 'signal_id' ] );
		$this->assertSame( 50, $first->getParams()[ 49 ][ 'signal_id' ] );

		$second = $handler->requests[ 1 ];
		$this->assertSame( 'POST', $second->getMethod() );
		$this->assertSame( 'https://api.dev.crowdsec.net/v3/signals', $second->getUri() );
		$this->assertSame( [ 0 ], \array_keys( $second->getParams() ) );
		$this->assertSame( 51, $second->getParams()[ 0 ][ 'signal_id' ] );
	}

	public function test_push_signals_throws_client_exception_when_later_batch_fails() :void {
		$handler = new CapiContractRequestHandler( [
			[ 200, '{"batch":1}' ],
			[ 400, '{"message":"bad request"}' ],
		] );
		$signals = [];
		for ( $i = 1; $i <= 51; $i++ ) {
			$signals[] = [ 'signal_id' => $i ];
		}

		try {
			$this->watcher( $handler )->pushSignals( $signals );
			$this->fail( 'Expected CrowdSec CAPI client exception.' );
		}
		catch ( ClientException $e ) {
			$this->assertCount( 2, $handler->requests );
		}
	}

	public function test_build_signal_produces_shield_required_payload_contract() :void {
		$handler = new CapiContractRequestHandler();

		$signal = $this->watcher( $handler )->buildSignal(
			[
				'scenario'         => 'shield/offense',
				'scenario_version' => '0.1',
				'message'          => 'Shield reporting scenario offense',
				'created_at'       => new \DateTimeImmutable( '2026-01-02T03:04:05.123456Z' ),
				'start_at'         => new \DateTimeImmutable( '2026-01-02T03:04:05.123456Z' ),
				'stop_at'          => new \DateTimeImmutable( '2026-01-02T03:04:05.123456Z' ),
				'context'          => [
					[ 'key' => 'method', 'value' => 'POST' ],
					[ 'key' => 'status', 'value' => '403' ],
				],
			],
			[
				'scope' => Constants::SCOPE_IP,
				'value' => '198.51.100.10',
			]
		);

		$this->assertSame( 'shield/offense', $signal[ 'scenario' ] );
		$this->assertSame( '0.1', $signal[ 'scenario_version' ] );
		$this->assertSame( 'manual', $signal[ 'scenario_trust' ] );
		$this->assertSame( 'Shield reporting scenario offense', $signal[ 'message' ] );
		$this->assertSame( 'test-machine-id', $signal[ 'machine_id' ] );
		$this->assertSame( '2026-01-02T03:04:05.123456Z', $signal[ 'created_at' ] );
		$this->assertSame( '2026-01-02T03:04:05.123456Z', $signal[ 'start_at' ] );
		$this->assertSame( '2026-01-02T03:04:05.123456Z', $signal[ 'stop_at' ] );
		$this->assertSame( [
			[ 'key' => 'method', 'value' => 'POST' ],
			[ 'key' => 'status', 'value' => '403' ],
		], $signal[ 'context' ] );
		$this->assertSame( [
			'scope' => Constants::SCOPE_IP,
			'value' => '198.51.100.10',
		], $signal[ 'source' ] );
		$this->assertCount( 1, $signal[ 'decisions' ] );
		$this->assertSame( $signal[ 'uuid' ], $signal[ 'decisions' ][ 0 ][ 'uuid' ] );
		$this->assertSame( '24h0m0s', $signal[ 'decisions' ][ 0 ][ 'duration' ] );
		$this->assertSame( 'shield/offense', $signal[ 'decisions' ][ 0 ][ 'scenario' ] );
		$this->assertSame( Constants::ORIGIN, $signal[ 'decisions' ][ 0 ][ 'origin' ] );
		$this->assertSame( Constants::SCOPE_IP, $signal[ 'decisions' ][ 0 ][ 'scope' ] );
		$this->assertSame( '198.51.100.10', $signal[ 'decisions' ][ 0 ][ 'value' ] );
		$this->assertSame( Constants::REMEDIATION_BAN, $signal[ 'decisions' ][ 0 ][ 'type' ] );
		$this->assertCount( 0, $handler->requests );
	}

	public function test_get_stream_decisions_uses_expected_capi_contract() :void {
		$handler = new CapiContractRequestHandler( [
			[ 200, '{"new":[],"deleted":[]}' ],
		] );

		$response = $this->watcher( $handler )->getStreamDecisions();

		$this->assertSame( [ 'new' => [], 'deleted' => [] ], $response );
		$this->assertCount( 1, $handler->requests );
		$request = $handler->requests[ 0 ];
		$this->assertSame( 'GET', $request->getMethod() );
		$this->assertSame( 'https://api.dev.crowdsec.net/v3/decisions/stream', $request->getUri() );
		$this->assertSame( [], $request->getParams() );
		$this->assertSame( 'Bearer test-token', $request->getHeaders()[ 'Authorization' ] );
	}

	public function test_enroll_uses_expected_capi_contract() :void {
		$handler = new CapiContractRequestHandler( [
			[ 200, '{"ok":true}' ],
		] );

		$response = $this->watcher( $handler )->enroll( 'shield-site', true, 'enroll-key', [ 'shield', 'wp' ] );

		$this->assertSame( [ 'ok' => true ], $response );
		$this->assertCount( 1, $handler->requests );
		$request = $handler->requests[ 0 ];
		$this->assertSame( 'POST', $request->getMethod() );
		$this->assertSame( 'https://api.dev.crowdsec.net/v3/watchers/enroll', $request->getUri() );
		$this->assertSame( [
			'name'           => 'shield-site',
			'overwrite'      => true,
			'attachment_key' => 'enroll-key',
			'tags'           => [ 'shield', 'wp' ],
		], $request->getParams() );
		$this->assertSame( 'Bearer test-token', $request->getHeaders()[ 'Authorization' ] );
	}

	private function watcher( CapiContractRequestHandler $handler ) :Watcher {
		return new Watcher(
			[
				'env'               => Constants::ENV_DEV,
				'machine_id_prefix' => 'test',
				'scenarios'         => [ 'shield/offense' ],
			],
			new CapiContractStorage(),
			$handler
		);
	}
}

class CapiContractStorage implements StorageInterface {

	public function retrieveMachineId() :?string {
		return 'test-machine-id';
	}

	public function retrievePassword() :?string {
		return 'test-password';
	}

	public function retrieveScenarios() :?array {
		return [ 'shield/offense' ];
	}

	public function retrieveToken() :?string {
		return 'test-token';
	}

	public function storeMachineId( string $machineId ) :bool {
		return true;
	}

	public function storePassword( string $password ) :bool {
		return true;
	}

	public function storeScenarios( array $scenarios ) :bool {
		return true;
	}

	public function storeToken( string $token ) :bool {
		return true;
	}
}

class CapiContractRequestHandler implements CapiHandlerInterface {

	/**
	 * @var Request[]
	 */
	public array $requests = [];

	/**
	 * @var array<int, array{0:int,1:string}>
	 */
	private array $responses;

	/**
	 * @param array<int, array{0:int,1:string}> $responses
	 */
	public function __construct( ?array $responses = null ) {
		$this->responses = $responses === null ? [] : \array_values( $responses );
	}

	public function getListDecisions( string $url, array $headers = [] ) :string {
		return '';
	}

	public function handle( Request $request ) :Response {
		$this->requests[] = $request;
		if ( $this->responses === [] ) {
			throw new \LogicException( 'No fake CAPI response configured for request.' );
		}
		$response = \array_shift( $this->responses );

		return new Response( $response[ 1 ], $response[ 0 ] );
	}
}
