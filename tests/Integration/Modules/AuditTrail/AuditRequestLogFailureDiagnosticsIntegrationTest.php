<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\LogHandlers {
	function error_log( string $message ) :bool {
		\FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\AuditTrail\Support\RequestLogDiagnosticSpy::record( 'traffic', $message );
		return true;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers {
	function error_log( string $message ) :bool {
		\FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\AuditTrail\Support\RequestLogDiagnosticSpy::record( 'audit', $message );
		return true;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\AuditTrail\Support {
	class RequestLogDiagnosticSpy {

		private static array $messages = [
			'audit'   => [],
			'traffic' => [],
		];

		public static function record( string $source, string $message ) :void {
			self::$messages[ $source ][] = $message;
		}

		public static function reset() :void {
			self::$messages = [
				'audit'   => [],
				'traffic' => [],
			];
		}

		public static function messages( string $source ) :array {
			return self::$messages[ $source ] ?? [];
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\AuditTrail {
	use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
	use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops as ReqLogsDB;
	use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as ReqLogsHandler;
	use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\RequestRecords;
	use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditLogger;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
		RuntimeTestState,
		ServicesState
	};
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\AuditTrail\Support\RequestLogDiagnosticSpy;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
	use FernleafSystems\Wordpress\Services\Services;
	use FernleafSystems\Wordpress\Services\Core\General;
	use FernleafSystems\Wordpress\Services\Utilities\{
		IpUtils,
		ServiceProviders
	};
	use FernleafSystems\Wordpress\Services\Utilities\Net\RequestIpDetect;

	class AuditRequestLogFailureDiagnosticsIntegrationTest extends ShieldIntegrationTestCase {

		use CurrentRequestFixture;

		private array $requestSnapshot = [];

		public function set_up() {
			parent::set_up();

			\remove_filter( 'shield/is_log_traffic', '__return_true', \PHP_INT_MAX );

			$this->requireDb( 'ips' );
			$this->requireDb( 'req_logs' );
			$this->requireDb( 'activity_logs' );
			$this->requireDb( 'activity_logs_meta' );

			$this->requestSnapshot = $this->snapshotCurrentRequestState();
			$this->requireController()->opts
				->optSet( 'enable_logger', 'Y' )
				->optSet( 'enable_live_log', 'N' )
				->optSet( 'enable_limiter', 'N' )
				->optSet( 'live_log_started_at', 0 );

			RequestLogDiagnosticSpy::reset();
			RuntimeTestState::resetRequestLoggerState();
		}

		public function tear_down() {
			RequestLogDiagnosticSpy::reset();
			RuntimeTestState::resetRequestLoggerState();
			\remove_filter( 'shield/is_log_traffic', '__return_true', \PHP_INT_MAX );
			$this->restoreCurrentRequestState( $this->requestSnapshot );
			parent::tear_down();
		}

		public function test_request_record_insert_failure_logs_precise_traffic_line_without_activity_row() :void {
			$corrupted = 0;
			$filter = $this->reqLogInsertFailureFilter( $corrupted );
			$restoreDbErrors = $this->suppressWpDbErrors();
			add_filter( 'query', $filter, 999 );

			try {
				$this->applySnapshotDiscoveryRequest();
				$this->writeAuditEvent();
			}
			finally {
				remove_filter( 'query', $filter, 999 );
				$restoreDbErrors();
			}

			$this->assertGreaterThan( 0, $corrupted );
			$line = $this->singleTrafficLine();
			$this->assertSame( 'request_record_insert', $this->fieldValue( $line, 'stage' ) );
			$this->assertNotSame( '', $this->fieldValue( $line, 'db_error' ) );
			$this->assertNotSame( '', $this->fieldValue( $line, 'req_id' ) );
			$this->assertNotSame( '', $this->fieldValue( $line, 'type' ) );
			$this->assertSame( '198.51.100.91', $this->fieldValue( $line, 'ip' ) );
			$this->assertSame( '/wp-admin/admin-ajax.php', $this->fieldValue( $line, 'path' ) );
			$this->assertSame( [], RequestLogDiagnosticSpy::messages( 'audit' ) );
			$this->assertSame( 0, $this->rowCount( 'activity_logs' ) );
			$this->assertSame( 0, $this->rowCount( 'req_logs' ) );
			$this->assertDiagnosticLineIsPrivate( $line );
		}

		public function test_activity_log_meta_insert_failure_logs_audit_line_and_keeps_request_logging_enabled() :void {
			$corrupted = 0;
			$filter = $this->activityLogMetaInsertFailureFilter( $corrupted );
			$restoreDbErrors = $this->suppressWpDbErrors();
			add_filter( 'query', $filter, 999 );

			try {
				$this->applySnapshotDiscoveryRequest();
				$this->writeAuditEvent( [
					'meta_failure_key' => 'meta-failure-value',
				] );
			}
			finally {
				remove_filter( 'query', $filter, 999 );
				$restoreDbErrors();
			}

			$this->assertGreaterThan( 0, $corrupted );
			$line = $this->singleAuditLine();
			$this->assertSame( 'Failed to insert activity log metadata', $this->fieldValue( $line, 'message' ) );
			$this->assertNotSame( '', $this->fieldValue( $line, 'db_error' ) );
			$this->assertSame( [], RequestLogDiagnosticSpy::messages( 'traffic' ) );
			$this->assertSame( 1, $this->rowCount( 'activity_logs' ) );
			$this->assertSame( 0, $this->rowCount( 'activity_logs_meta' ) );
			$this->assertSame( \PHP_INT_MAX, \has_filter( 'shield/is_log_traffic', '__return_true' ) );
			$this->assertDiagnosticLineIsPrivate( $line );
		}

		public function test_request_record_failure_state_is_cleared_after_successful_direct_create() :void {
			$ipRecord = ( new IPRecords() )->loadIP( '198.51.100.92' );
			$requestRecords = new RequestRecords();
			$corrupted = 0;
			$filter = $this->reqLogInsertFailureFilter( $corrupted );
			$restoreDbErrors = $this->suppressWpDbErrors();
			add_filter( 'query', $filter, 999 );

			try {
				$this->assertNull( $requestRecords->createReq( 'failed-diagnostic-create', $ipRecord->id ) );
			}
			finally {
				remove_filter( 'query', $filter, 999 );
				$restoreDbErrors();
			}

			$this->assertSame( 'request_record_insert', $requestRecords->getLastFailure()[ 'stage' ] ?? '' );

			$created = $requestRecords->createReq( 'successful-diagnostic-create', $ipRecord->id );
			$this->assertInstanceOf( ReqLogsDB\Record::class, $created );
			$this->assertSame( [], $requestRecords->getLastFailure() );
		}

		public function test_request_record_update_failure_logs_precise_line_and_preserves_fallback_linking() :void {
			$corrupted = 0;
			$filter = $this->reqLogUpdateFailureFilter( $corrupted );
			$restoreDbErrors = $this->suppressWpDbErrors();
			add_filter( 'query', $filter, 999 );

			try {
				$this->applySnapshotDiscoveryRequest();
				$this->writeAuditEvent();
			}
			finally {
				remove_filter( 'query', $filter, 999 );
				$restoreDbErrors();
			}

			$this->assertGreaterThan( 0, $corrupted );
			$line = $this->singleTrafficLine();
			$this->assertSame( 'request_record_update', $this->fieldValue( $line, 'stage' ) );
			$this->assertNotSame( '', $this->fieldValue( $line, 'db_error' ) );
			$this->assertNotSame( '', $this->fieldValue( $line, 'type' ) );
			$this->assertSame( '/wp-admin/admin-ajax.php', $this->fieldValue( $line, 'path' ) );
			$this->assertSame( [], RequestLogDiagnosticSpy::messages( 'audit' ) );
			$this->assertSame( 1, $this->rowCount( 'activity_logs' ) );
			$this->assertSame( 1, $this->rowCount( 'req_logs' ) );
			$this->assertSame( $this->reqLogIds(), $this->activityLogRequestRefs() );
			$this->assertDiagnosticLineIsPrivate( $line );
		}

		public function test_ip_record_failure_logs_precise_traffic_line_without_audit_duplicate() :void {
			$this->applySnapshotDiscoveryRequest( 'not-an-ip' );
			$this->writeAuditEvent();

			$line = $this->singleTrafficLine();
			$this->assertSame( 'ip_record_load', $this->fieldValue( $line, 'stage' ) );
			$this->assertNotSame( '', $this->fieldValue( $line, 'type' ) );
			$this->assertSame( [], RequestLogDiagnosticSpy::messages( 'audit' ) );
			$this->assertSame( 0, $this->rowCount( 'activity_logs' ) );
			$this->assertSame( 0, $this->rowCount( 'req_logs' ) );
			$this->assertDiagnosticLineIsPrivate( $line );
		}

		public function test_php_cli_cron_without_request_identity_creates_linked_logs_without_diagnostic() :void {
			$this->applyPhpCliCronRequestWithoutTransport();
			$this->writeAuditEvent();

			$this->assertSame( [], RequestLogDiagnosticSpy::messages( 'traffic' ) );
			$this->assertSame( [], RequestLogDiagnosticSpy::messages( 'audit' ) );
			$this->assertSame( 1, $this->rowCount( 'activity_logs' ) );
			$this->assertSame( 1, $this->rowCount( 'req_logs' ) );
			$this->assertSame( $this->reqLogIds(), $this->activityLogRequestRefs() );

			$requestRow = $this->latestRequestLogRow();
			$this->assertSame( ReqLogsHandler::TYPE_CRON, (string)$requestRow[ 'type' ] );
			$this->assertSame( '/wp-cron.php', (string)$requestRow[ 'path' ] );
			$this->assertSame( '127.0.0.1', $this->requestLogIpHuman( (int)$requestRow[ 'ip_ref' ] ) );
		}

		public function test_cloudflare_transport_ip_is_logged_when_request_detection_excludes_it() :void {
			$this->applyCloudflareTransportRequestWithoutDetectedVisitorIp();

			$this->assertSame( 'REMOTE_ADDR', Services::Request()->getIpDetector()->getPreferredSource() );
			$this->assertSame( '', Services::Request()->ip() );
			$this->assertSame( '', $this->requireController()->this_req->ip );

			$this->writeAuditEvent();

			$this->assertSame( [], RequestLogDiagnosticSpy::messages( 'traffic' ) );
			$this->assertSame( [], RequestLogDiagnosticSpy::messages( 'audit' ) );
			$this->assertSame( 1, $this->rowCount( 'activity_logs' ) );
			$this->assertSame( 1, $this->rowCount( 'req_logs' ) );

			$requestRow = $this->latestRequestLogRow();
			$this->assertSame( [ (int)$requestRow[ 'id' ] ], $this->activityLogRequestRefs() );
			$this->assertSame( '173.245.48.5', $this->requestLogIpHuman( (int)$requestRow[ 'ip_ref' ] ) );

			$meta = $this->decodedRequestMeta( (string)$requestRow[ 'meta' ] );
			$this->assertSame( 'transport', $meta[ 'ip_attribution' ] ?? null );
			$this->assertSame( 'cloudflare', $meta[ 'ip_provider' ] ?? null );
			$this->assertSame( 'REMOTE_ADDR', $meta[ 'ip_source' ] ?? null );

			$this->assertSame( '', Services::Request()->ip() );
			$this->assertSame( '', $this->requireController()->this_req->ip );
		}

		private function applySnapshotDiscoveryRequest( string $ip = '198.51.100.91' ) :void {
			$this->applyCurrentRequestState(
				[
					'REQUEST_METHOD'  => 'POST',
					'REQUEST_URI'     => '/wp-admin/admin-ajax.php?action=icwp-wpsf_snapshot_discovery&secret=private-query',
					'HTTP_USER_AGENT' => 'private-user-agent',
					'REMOTE_ADDR'     => $ip,
				],
				[
					'action' => 'icwp-wpsf_snapshot_discovery',
					'secret' => 'private-query',
				],
				[
					'payload' => 'private-body',
				],
				[
					'path'       => '/wp-admin/admin-ajax.php',
					'wp_is_ajax' => true,
				]
			);
		}

		private function applyPhpCliCronRequestWithoutTransport() :void {
			$this->applyCurrentRequestState(
				[
					'REQUEST_METHOD'  => '',
					'REQUEST_URI'     => '',
					'HTTP_USER_AGENT' => '',
					'REMOTE_ADDR'     => '',
				],
				[],
				[],
				[
					'path'       => '',
					'wp_is_ajax' => false,
					'wp_is_cron' => true,
				]
			);

			ServicesState::mergeItems( [
				'service_wpgeneral' => new class extends General {
					public function isWpCli() :bool {
						return false;
					}

					public function isMultisite_SubdomainInstall() :bool {
						return false;
					}

					public function isAjax() :bool {
						return false;
					}

					public function isXmlrpc() :bool {
						return false;
					}

					public function isCron() :bool {
						return true;
					}

					public function isLoginRequest() :bool {
						return false;
					}

					public function isLoginUrl() :bool {
						return false;
					}
				},
			] );
		}

		private function applyCloudflareTransportRequestWithoutDetectedVisitorIp() :void {
			ServicesState::mergeItems( [
				'service_ip' => new class extends IpUtils {
					public function getServerPublicIPs( $forceRefresh = false ) :array {
						return [];
					}
				},
				'service_serviceproviders' => new class extends ServiceProviders {
					public function getProviders() :array {
						return [
							'services' => [
								'cloudflare' => [
									'name' => 'Cloudflare',
									'ips'  => [
										4 => [ '173.245.48.0/20' ],
										6 => [ '2400:cb00::/32' ],
									],
								],
							],
							'crawlers' => [],
						];
					}
				},
			] );

			$this->applyCurrentRequestState(
				[
					'REQUEST_METHOD'          => 'GET',
					'REQUEST_URI'             => '/wp-json/titanium-site-watch/v1/health',
					'HTTP_USER_AGENT'         => 'generic-test-agent',
					'REMOTE_ADDR'             => '173.245.48.5',
					'HTTP_CF_CONNECTING_IP'   => '',
					'HTTP_X_FORWARDED_FOR'    => '',
					'HTTP_X_FORWARDED'        => '',
					'HTTP_X_REAL_IP'          => '',
					'HTTP_X_SUCURI_CLIENTIP'  => '',
					'HTTP_INCAP_CLIENT_IP'    => '',
					'HTTP_X_SP_FORWARDED_IP'  => '',
					'HTTP_FORWARDED'          => '',
					'HTTP_CLIENT_IP'          => '',
				],
				[],
				[],
				[
					'path' => '/wp-json/titanium-site-watch/v1/health',
				]
			);

			$request = Services::Request();
			$request->setIpDetector( ( new RequestIpDetect() )->setPreferredSource( 'REMOTE_ADDR' ) );
			$this->requireController()->this_req->ip = $request->ip();
			$this->requireController()->this_req->ip_is_public = !empty( $this->requireController()->this_req->ip )
				&& Services::IP()->isValidIp_PublicRemote( $this->requireController()->this_req->ip );
		}

		private function writeAuditEvent( array $auditParams = [] ) :void {
			$logger = $this->makeLogger();
			$this->captureEvent( $logger, 'lic_activation_success', [
				'audit_params' => $auditParams,
			], [
				'audit'           => true,
				'audit_countable' => false,
				'audit_multiple'  => true,
				'level'           => 'notice',
			] );
			$this->shutdownLogger( $logger );
		}

		private function reqLogInsertFailureFilter( int &$corrupted ) :callable {
			$table = $this->requireController()->db_con->req_logs->getTable();
			return function ( string $query ) use ( &$corrupted, $table ) :string {
				if ( \str_starts_with( \ltrim( $query ), 'INSERT' )
					 && \str_contains( $query, '`'.$table.'`' )
					 && \str_contains( $query, '`req_id`' )
					 && \str_contains( $query, '`ip_ref`' )
					 && !\str_contains( $query, '`path`' ) ) {
					$corrupted++;
					return 'INSERT INTO `shield_missing_req_logs_table` (`id`) VALUES (1)';
				}
				return $query;
			};
		}

		private function reqLogUpdateFailureFilter( int &$corrupted ) :callable {
			$table = $this->requireController()->db_con->req_logs->getTable();
			return function ( string $query ) use ( &$corrupted, $table ) :string {
				if ( \str_starts_with( \ltrim( $query ), 'UPDATE' )
					 && \str_contains( $query, '`'.$table.'`' )
					 && \str_contains( $query, '`path`' ) ) {
					$corrupted++;
					return 'UPDATE `shield_missing_req_logs_table` SET `id`=1';
				}
				return $query;
			};
		}

		private function activityLogMetaInsertFailureFilter( int &$corrupted ) :callable {
			$table = $this->requireController()->db_con->activity_logs_meta->getTable();
			return function ( string $query ) use ( &$corrupted, $table ) :string {
				if ( \str_starts_with( \ltrim( $query ), 'INSERT' )
					 && \str_contains( $query, '`'.$table.'`' )
					 && \str_contains( $query, '`log_ref`' )
					 && \str_contains( $query, '`meta_key`' )
					 && \str_contains( $query, '`meta_value`' ) ) {
					$corrupted++;
					return 'INSERT INTO `shield_missing_activity_logs_meta_table` (`id`) VALUES (1)';
				}
				return $query;
			};
		}

		private function suppressWpDbErrors() :callable {
			global $wpdb;
			$previous = $wpdb->suppress_errors( true );
			return function () use ( $wpdb, $previous ) :void {
				$wpdb->suppress_errors( $previous );
			};
		}

		private function singleTrafficLine() :string {
			$messages = RequestLogDiagnosticSpy::messages( 'traffic' );
			$this->assertCount( 1, $messages );
			$line = (string)$messages[ 0 ];
			$this->assertStringStartsWith( 'Shield request log write failed: ', $line );
			$this->assertStringNotContainsString( 'DEBUG::'.'EXCEPTION', $line );
			return $line;
		}

		private function singleAuditLine() :string {
			$messages = RequestLogDiagnosticSpy::messages( 'audit' );
			$this->assertCount( 1, $messages );
			$line = (string)$messages[ 0 ];
			$this->assertStringStartsWith( 'Shield activity log write failed: ', $line );
			$this->assertStringNotContainsString( 'DEBUG::'.'EXCEPTION', $line );
			return $line;
		}

		private function assertDiagnosticLineIsPrivate( string $line ) :void {
			$this->assertStringNotContainsString( 'secret=private-query', $line );
			$this->assertStringNotContainsString( 'private-body', $line );
			$this->assertStringNotContainsString( 'private-user-agent', $line );
			$this->assertStringNotContainsString( 'INSERT INTO', $line );
			$this->assertStringNotContainsString( 'UPDATE `', $line );
		}

		private function fieldValue( string $line, string $field ) :string {
			$pattern = '/(?:^|[:;] )'.\preg_quote( $field, '/' ).'=([^;]*)/';
			$this->assertMatchesRegularExpression( $pattern, $line );
			\preg_match( $pattern, $line, $matches );
			return (string)( $matches[ 1 ] ?? '' );
		}

		private function makeLogger() :AuditLogger {
			$ref = new \ReflectionClass( AuditLogger::class );
			/** @var AuditLogger $logger */
			$logger = $ref->newInstanceWithoutConstructor();
			return $logger;
		}

		private function captureEvent( AuditLogger $logger, string $event, array $meta, array $def ) :void {
			$method = new \ReflectionMethod( $logger, 'captureEvent' );
			$method->setAccessible( true );
			$method->invoke( $logger, $event, $meta, $def );
		}

		private function shutdownLogger( AuditLogger $logger ) :void {
			$method = new \ReflectionMethod( $logger, 'onShutdown' );
			$method->setAccessible( true );
			$method->invoke( $logger );
		}

		private function rowCount( string $dbKey ) :int {
			global $wpdb;
			return (int)$wpdb->get_var( \sprintf(
				'SELECT COUNT(*) FROM `%s`',
				$this->requireController()->db_con->{$dbKey}->getTable()
			) );
		}

		private function activityLogRequestRefs() :array {
			global $wpdb;
			return \array_map( 'intval', (array)$wpdb->get_col( \sprintf(
				'SELECT `req_ref` FROM `%s` ORDER BY `id` ASC',
				$this->requireController()->db_con->activity_logs->getTable()
			) ) );
		}

		private function reqLogIds() :array {
			global $wpdb;
			return \array_map( 'intval', (array)$wpdb->get_col( \sprintf(
				'SELECT `id` FROM `%s` ORDER BY `id` ASC',
				$this->requireController()->db_con->req_logs->getTable()
			) ) );
		}

		private function latestRequestLogRow() :array {
			global $wpdb;
			return (array)$wpdb->get_row( \sprintf(
				'SELECT * FROM `%s` ORDER BY `id` DESC LIMIT 1',
				$this->requireController()->db_con->req_logs->getTable()
			), \ARRAY_A );
		}

		private function requestLogIpHuman( int $ipRef ) :string {
			global $wpdb;
			$ip = (string)$wpdb->get_var( $wpdb->prepare(
				\sprintf(
					'SELECT `ip` FROM `%s` WHERE `id`=%%d',
					$this->requireController()->db_con->ips->getTable()
				),
				$ipRef
			) );

			$unpacked = \function_exists( 'inet_ntop' ) && \in_array( \strlen( $ip ), [ 4, 16 ], true )
				? \inet_ntop( $ip )
				: false;

			return \is_string( $unpacked ) ? $unpacked : $ip;
		}

		private function decodedRequestMeta( string $encoded ) :array {
			$decoded = \json_decode( (string)\base64_decode( $encoded ), true );
			return \is_array( $decoded ) ? $decoded : [];
		}
	}
}
