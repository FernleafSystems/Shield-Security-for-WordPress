<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as ReqLogsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogger;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class McpRequestLoggingIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'req_logs' );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();

		$this->requireController()->opts
			 ->optSet( 'enable_logger', 'Y' )
			 ->optSet( 'enable_live_log', 'N' )
			 ->optSet( 'live_log_started_at', 0 );
	}

	public function tear_down() {
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_shield_mcp_rest_route_is_logged_as_mcp_type() :void {
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/?rest_route=/wp/v2/mcp-servers/shield-security/mcp',
			],
			[
				'rest_route' => '/wp/v2/mcp-servers/shield-security/mcp',
			],
			[],
			[
				'wp_is_permalinks_enabled' => false,
			]
		);

		$this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->createDependentLog() );

		$this->assertSame( ReqLogsHandler::TYPE_MCP, $this->latestLoggedRequestType() );
	}

	public function test_non_mcp_rest_route_remains_generic_rest_type() :void {
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/?rest_route=/wp/v2/users/me',
			],
			[
				'rest_route' => '/wp/v2/users/me',
			],
			[],
			[
				'wp_is_permalinks_enabled' => false,
			]
		);

		$this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->createDependentLog() );

		$this->assertSame( ReqLogsHandler::TYPE_REST, $this->latestLoggedRequestType() );
	}

	private function latestLoggedRequestType() :string {
		global $wpdb;

		return (string)$wpdb->get_var(
			sprintf(
				'SELECT `type` FROM `%s` ORDER BY `id` DESC LIMIT 1',
				$this->requireController()->db_con->req_logs->getTable()
			)
		);
	}

	private function withTrafficLoggingEnabled( callable $callback ) {
		\add_filter( 'shield/is_log_traffic', '__return_true', \PHP_INT_MAX );

		try {
			return $callback();
		}
		finally {
			\remove_filter( 'shield/is_log_traffic', '__return_true', \PHP_INT_MAX );
		}
	}
}
