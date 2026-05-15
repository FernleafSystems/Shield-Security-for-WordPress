<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as ReqLogsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class RequestLoggerCompletenessIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'enable_logger',
			'enable_live_log',
			'live_log_started_at',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		RuntimeTestState::resetRequestLoggerState();
	}

	public function tear_down() {
		RuntimeTestState::resetRequestLoggerState();
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		$this->restoreSelectedOptions( $this->optionSnapshot );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_dependent_request_log_records_real_request_fields_and_meta() :void {
		$userId = $this->loginAsAdministrator();
		$this->requireController()->opts
			->optSet( 'enable_logger', 'Y' )
			->optSet( 'enable_live_log', 'N' )
			->optSet( 'live_log_started_at', 0 );
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD'  => 'POST',
				'REQUEST_URI'     => '/shi-279-traffic?selected=1&mode=complete',
				'HTTP_USER_AGENT' => 'shi-279-agent',
				'REMOTE_ADDR'     => '198.51.100.88',
			],
			[
				'selected' => '1',
				'mode'     => 'complete',
			],
			[
				'payload' => 'present',
			],
			[
				'path' => '/shi-279-traffic',
				'ip'   => '198.51.100.88',
			]
		);
		$expectedCode = (int)http_response_code();

		$record = $this->withTrafficLoggingEnabled(
			fn() => $this->requireController()->comps->requests_log->createDependentLog()
		);

		$this->assertNotNull( $record );
		$raw = $record->getRawData();
		$meta = $this->decodedMeta( (string)( $raw[ 'meta' ] ?? '' ) );

		$this->assertSame( ReqLogsHandler::TYPE_HTTP, $record->type );
		$this->assertSame( 'POST', $record->verb );
		$this->assertSame( '/shi-279-traffic', $record->path );
		$this->assertSame( $expectedCode, $record->code );
		$this->assertSame( $userId, $record->uid );
		$this->assertFalse( $record->offense );
		$this->assertNotSame( '', $record->req_id );
		$this->assertGreaterThan( 0, (int)$record->ip_ref );
		$this->assertSame( 0, (int)( $raw[ 'transient' ] ?? 1 ) );
		$this->assertSame( 'selected=1&mode=complete', $meta[ 'query' ] ?? null );
		$this->assertSame( 'shi-279-agent', $meta[ 'ua' ] ?? null );
		$this->assertSame( 1, (int)( $meta[ 'has_params' ] ?? 0 ) );
		$this->assertArrayHasKey( 'ts', $meta );
	}

	private function withTrafficLoggingEnabled( callable $callback ) {
		add_filter( 'shield/is_log_traffic', '__return_true', \PHP_INT_MAX );

		try {
			return $callback();
		}
		finally {
			remove_filter( 'shield/is_log_traffic', '__return_true', \PHP_INT_MAX );
		}
	}

	private function decodedMeta( string $encoded ) :array {
		$decoded = \json_decode( (string)\base64_decode( $encoded ), true );
		return \is_array( $decoded ) ? $decoded : [];
	}
}
