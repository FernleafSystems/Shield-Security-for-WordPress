<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxBatchRequests,
	Actions\PluginBadgeClose,
	Actions\Render\Components\Traffic\TrafficLiveLogs,
	Actions\Render\Components\Widgets\DashboardLiveMonitorTicker
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogger;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\Request as ServicesRequest;
use FernleafSystems\Wordpress\Services\Services;

class RequestLoggerSuppressionIntegrationTest extends ShieldIntegrationTestCase {

	private array $servicesSnapshot = [];

	private array $serverSnapshot = [];

	private array $querySnapshot = [];

	private array $postSnapshot = [];

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );

		$this->servicesSnapshot = $this->servicesStateSnapshot();
		$this->serverSnapshot = $_SERVER;
		$this->querySnapshot = $_GET;
		$this->postSnapshot = $_POST;

		$this->requireController()->opts
			 ->optSet( 'enable_logger', 'Y' )
			 ->optSet( 'enable_live_log', 'N' )
			 ->optSet( 'live_log_started_at', 0 );
	}

	public function tear_down() {
		$_SERVER = $this->serverSnapshot;
		$_GET = $this->querySnapshot;
		$_POST = $this->postSnapshot;
		$this->restoreServicesState( $this->servicesSnapshot );

		parent::tear_down();
	}

	public function test_rest_route_normalisation_supports_permalink_and_query_forms() :void {
		$this->applyRequestState(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/wp-json/wp/v2/users/me?context=edit&_locale=user',
			],
			[],
			[],
			[
				'wp_is_permalinks_enabled' => true,
			]
		);
		$this->assertSame( 'wp/v2/users/me', $this->requireController()->this_req->getRestRoute() );

		$this->applyRequestState(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/?rest_route=/wp/v2/users/me',
			],
			[
				'rest_route' => '/wp/v2/users/me',
				'context'    => 'edit',
				'_locale'    => 'user',
			],
			[],
			[
				'wp_is_permalinks_enabled' => false,
			]
		);
		$this->assertSame( 'wp/v2/users/me', $this->requireController()->this_req->getRestRoute() );
	}

	public function test_logged_in_users_me_rest_requests_are_suppressed() :void {
		$this->loginAsAdministrator();
		$this->applyRequestState(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/wp-json/wp/v2/users/me?context=edit&_locale=user',
			],
			[],
			[],
			[
				'wp_is_permalinks_enabled' => true,
			]
		);

		$this->assertFalse( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_logged_out_users_me_rest_requests_remain_loggable() :void {
		\wp_set_current_user( 0 );
		$this->applyRequestState(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/?rest_route=/wp/v2/users/me',
			],
			[
				'rest_route' => '/wp/v2/users/me',
			]
		);

		$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
	}

	public function test_live_monitor_batch_requests_are_suppressed_only_when_all_nested_requests_match() :void {
		$this->loginAsSecurityAdmin();

		$allSuppressible = ActionData::Build( AjaxBatchRequests::class, true, [
			'requests' => [
				[
					'id'      => 'ticker',
					'request' => ActionData::BuildAjaxRender( DashboardLiveMonitorTicker::class, [ 'limit' => 12 ] ),
				],
				[
					'id'      => 'traffic',
					'request' => ActionData::BuildAjaxRender( TrafficLiveLogs::class, [ 'limit' => 12 ] ),
				],
			],
		] );

		$this->applyShieldAjaxRequest( $allSuppressible, true );
		$beforeSuppressed = $this->rowCount( 'req_logs' );
		$this->withTrafficLoggingEnabled( function () {
			$this->runShutdownLogger( new RequestLogger() );
		} );
		$this->assertSame( $beforeSuppressed, $this->rowCount( 'req_logs' ) );

		$mixedBatch = ActionData::Build( AjaxBatchRequests::class, true, [
			'requests' => [
				[
					'id'      => 'ticker',
					'request' => ActionData::BuildAjaxRender( DashboardLiveMonitorTicker::class, [ 'limit' => 12 ] ),
				],
				[
					'id'      => 'other',
					'request' => ActionData::Build( PluginBadgeClose::class ),
				],
			],
		] );

		$this->applyShieldAjaxRequest( $mixedBatch, true );
		$beforeMixed = $this->rowCount( 'req_logs' );
		$this->withTrafficLoggingEnabled( function () {
			$this->runShutdownLogger( new RequestLogger() );
		} );
		$this->assertSame( $beforeMixed + 1, $this->rowCount( 'req_logs' ) );
	}

	public function test_programmatic_filter_can_disable_suppressions() :void {
		$this->loginAsSecurityAdmin();
		$this->applyShieldAjaxRequest(
			ActionData::BuildAjaxRender( DashboardLiveMonitorTicker::class, [ 'limit' => 12 ] ),
			true
		);

		$callback = fn( bool $enabled ) => false;
		add_filter( RequestLogger::FILTER_BUILTIN_SUPPRESSIONS_ENABLED, $callback, 10, 1 );

		try {
			$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
		}
		finally {
			remove_filter( RequestLogger::FILTER_BUILTIN_SUPPRESSIONS_ENABLED, $callback, 10 );
		}
	}

	public function test_live_logging_bypasses_suppressions() :void {
		$this->loginAsSecurityAdmin();
		$this->applyShieldAjaxRequest(
			ActionData::BuildAjaxRender( DashboardLiveMonitorTicker::class, [ 'limit' => 12 ] ),
			true
		);

		$this->requireController()->opts
			 ->optSet( 'enable_live_log', 'Y' )
			 ->optSet( 'live_log_started_at', \time() );

		try {
			$this->assertTrue( $this->withTrafficLoggingEnabled( fn() => ( new RequestLogger() )->isLogged() ) );
		}
		finally {
			$this->requireController()->opts
				 ->optSet( 'enable_live_log', 'N' )
				 ->optSet( 'live_log_started_at', 0 );
		}
	}

	public function test_dependent_log_creation_still_writes_suppressible_requests() :void {
		$this->loginAsSecurityAdmin();
		$this->applyShieldAjaxRequest(
			ActionData::BuildAjaxRender( DashboardLiveMonitorTicker::class, [ 'limit' => 12 ] ),
			true
		);

		$before = $this->rowCount( 'req_logs' );
		$this->withTrafficLoggingEnabled( function () {
			( new RequestLogger() )->createDependentLog();
		} );

		$this->assertSame( $before + 1, $this->rowCount( 'req_logs' ) );
	}

	private function applyShieldAjaxRequest( array $post, bool $isSecurityAdmin ) :void {
		$this->applyRequestState(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-admin/admin-ajax.php',
			],
			[],
			$post,
			[
				'path'              => '/wp-admin/admin-ajax.php',
				'wp_is_ajax'        => true,
				'is_security_admin' => $isSecurityAdmin,
			]
		);
	}

	private function applyRequestState(
		array $server,
		array $query = [],
		array $post = [],
		array $requestOverrides = []
	) :void {
		$host = (string)\wp_parse_url( \home_url(), \PHP_URL_HOST );
		$_SERVER = \array_merge( $this->serverSnapshot, [
			'HTTP_HOST'      => empty( $host ) ? 'example.org' : $host,
			'HTTP_USER_AGENT'=> 'phpunit',
			'REMOTE_ADDR'    => '198.51.100.25',
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/',
		], $server );
		$_GET = $query;
		$_POST = $post;

		$request = new ServicesRequest();
		$this->installServiceRequest( $request );

		$this->requireController()->this_req = new ThisRequest( \array_merge( [
			'request'                  => $request,
			'path'                     => empty( $request->getPath() ) ? '/' : $request->getPath(),
			'wp_is_ajax'               => false,
			'wp_is_permalinks_enabled' => true,
			'rest_api_root'            => \rest_url(),
			'is_security_admin'        => false,
		], $requestOverrides ) );
	}

	private function installServiceRequest( ServicesRequest $request ) :void {
		$itemsProperty = $this->servicesProperty( 'items' );
		$servicesProperty = $this->servicesProperty( 'services' );

		$items = $itemsProperty->getValue() ?? [];
		if ( !\is_array( $items ) ) {
			$items = [];
		}
		$items[ 'service_request' ] = $request;

		$itemsProperty->setValue( null, $items );
		$servicesProperty->setValue( null, null );
	}

	private function servicesStateSnapshot() :array {
		return [
			'items'    => $this->servicesProperty( 'items' )->getValue(),
			'services' => $this->servicesProperty( 'services' )->getValue(),
		];
	}

	private function restoreServicesState( array $snapshot ) :void {
		$this->servicesProperty( 'items' )->setValue( null, $snapshot[ 'items' ] ?? null );
		$this->servicesProperty( 'services' )->setValue( null, $snapshot[ 'services' ] ?? null );
	}

	private function servicesProperty( string $propertyName ) :\ReflectionProperty {
		$reflection = new \ReflectionClass( Services::class );
		$property = $reflection->getProperty( $propertyName );
		$property->setAccessible( true );
		return $property;
	}

	private function rowCount( string $dbKey ) :int {
		global $wpdb;
		return (int)$wpdb->get_var(
			sprintf(
				'SELECT COUNT(*) FROM `%s`',
				$this->requireController()->db_con->{$dbKey}->getTable()
			)
		);
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

	private function runShutdownLogger( RequestLogger $logger ) :void {
		$hook = $this->requireController()->prefix( 'plugin_shutdown' );
		remove_all_actions( $hook );

		$logger->resetExecution();
		$logger->execute();

		do_action( $hook );
	}
}
