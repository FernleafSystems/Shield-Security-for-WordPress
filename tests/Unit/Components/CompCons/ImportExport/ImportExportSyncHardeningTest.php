<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\ImportExport;

use Brain\Monkey\Functions;
use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	PluginImportExport_NetworkInviteRequest,
	PluginImportExport_UpdateNotified
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Import;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Diagnostics\SyncObservation;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites\PingSender;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites\{
	QueueProcessor,
	QueueScheduler
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites\SyncSiteInviteSender;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites\SyncSiteUrlValidator;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites\InvitationMetadata;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Services\Core\General;
use FernleafSystems\Wordpress\Services\Core\Request;
use FernleafSystems\Wordpress\Services\Core\VOs\WpHttpResponseVo;
use FernleafSystems\Wordpress\Services\Utilities\HttpRequest;
use FernleafSystems\Wordpress\Services\Utilities\Data;

class ImportExportSyncHardeningTest extends BaseUnitTest {

	private Controller $controller;
	private ImportExportOptsStoreStub $opts;
	private ImportExportEventsRecorderStub $events;
	private ImportExportHttpRequestStub $httpRequest;
	private ImportExportGeneralStub $wpGeneral;
	private array $scheduledEvents = [];
	private array $transients = [];
	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url, int $component = -1 ) => $component === -1
				? ( \parse_url( $url ) ?: false )
				: \parse_url( $url, $component )
		);
		Functions\when( 'wp_http_validate_url' )->alias( static fn( string $url ) :string => $url );
		Functions\when( 'wp_generate_password' )->justReturn( 'uniq' );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'remove_filter' )->justReturn( true );

		$scheduledEvents = &$this->scheduledEvents;
		Functions\when( 'wp_next_scheduled' )->alias(
			static function ( string $hook ) use ( &$scheduledEvents ) {
				return $scheduledEvents[ $hook ] ?? false;
			}
		);
		Functions\when( 'wp_schedule_single_event' )->alias(
			static function ( int $timestamp, string $hook ) use ( &$scheduledEvents ) :bool {
				$scheduledEvents[ $hook ] = $timestamp;
				return true;
			}
		);
		Functions\when( 'wp_clear_scheduled_hook' )->alias(
			static function ( string $hook ) use ( &$scheduledEvents ) :bool {
				unset( $scheduledEvents[ $hook ] );
				return true;
			}
		);
		$transients = &$this->transients;
		Functions\when( 'get_transient' )->alias(
			static function ( string $key ) use ( &$transients ) {
				return $transients[ $key ][ 'value' ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			static function ( string $key, $value, int $expiration = 0 ) use ( &$transients ) :bool {
				$transients[ $key ] = [
					'value'      => $value,
					'expiration' => $expiration,
				];
				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			static function ( string $key ) use ( &$transients ) :bool {
				unset( $transients[ $key ] );
				return true;
			}
		);

		$this->opts = new ImportExportOptsStoreStub( [
			'importexport_enable'               => 'N',
			'importexport_masterurl'            => '',
			'importexport_handshake_expires_at' => 0,
			'import_id'                         => '',
		] );
		$this->events = new ImportExportEventsRecorderStub();
		$this->httpRequest = new ImportExportHttpRequestStub();
		$this->wpGeneral = new ImportExportGeneralStub();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request'     => new ImportExportRequestStub(),
			'service_data'        => new Data(),
			'service_httprequest' => $this->httpRequest,
			'service_wpgeneral'   => $this->wpGeneral,
		] );

		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_explicit_import_restores_local_master_url_and_enables_sync() :void {
		$this->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_masterurl', 'https://current-master.example.com' )
			->store();
		$this->httpRequest->setResponseOptions( [
			'importexport_enable'    => 'N',
			'importexport_masterurl' => 'https://imported-master.example.com',
		] );

		( new Import() )->fromSite( 'https://source-master.example.com' );

		$this->assertSame( 'Y', (string)$this->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( 'https://current-master.example.com', (string)$this->opts->optGet( 'importexport_masterurl' ) );
		$this->assertStringContainsString( 'importexport_export', $this->httpRequest->lastRequestedUrl() );
		$this->assertStringNotContainsString( 'secret', $this->httpRequest->lastRequestedUrl() );
	}

	/**
	 * @dataProvider clientImportObservationProvider
	 */
	public function test_from_site_records_bounded_client_import_observation(
		string $body,
		int $httpCode,
		bool $httpSuccess,
		string $expectedResult,
		?int $expectedExceptionCode,
		?string $expectedErrorCategory = null
	) :void {
		$this->httpRequest->setGetResponse( $body, $httpCode, $httpSuccess );
		$import = new Import();

		try {
			$import->fromSite( 'https://source-master.example.com' );
			$this->assertNull( $expectedExceptionCode );
		}
		catch ( \Exception $e ) {
			$this->assertSame( $expectedExceptionCode, $e->getCode() );
		}

		$observation = $import->latestObservation();
		$this->assertSame( $expectedResult, $observation[ 'result' ] ?? null );
		$this->assertSame( SyncObservation::PHASE_CLIENT_IMPORT, $observation[ 'phase' ] ?? null );
		$this->assertSame(
			SyncObservation::targetFingerprint( 'https://source-master.example.com' ),
			$observation[ 'target_fingerprint' ] ?? null
		);
		$this->assertSame( $httpCode > 0 ? $httpCode : null, $observation[ 'http_status' ] ?? null );
		$this->assertSame( $expectedErrorCategory, $observation[ 'error_category' ] ?? null );
	}

	public static function clientImportObservationProvider() :array {
		return [
			'generic transport failure' => [ '', 0, false, SyncObservation::RESULT_TRANSPORT_FAILURE, 5 ],
			'empty response' => [ '', 200, true, SyncObservation::RESULT_EMPTY_RESPONSE, 5 ],
			'HTML response' => [ '<html><body>Upstream error</body></html>', 502, true, SyncObservation::RESULT_INVALID_RESPONSE, 5 ],
			'malformed JSON response' => [ '{"success":', 200, true, SyncObservation::RESULT_INVALID_RESPONSE, 5 ],
			'invalid response' => [ '"scalar"', 200, true, SyncObservation::RESULT_INVALID_RESPONSE, 5 ],
			'parsed rejection' => [ '{"success":false,"code":3,"message":"remote secret"}', 403, true, SyncObservation::RESULT_PARSED_REJECTION, 7, SyncObservation::ERROR_REMOTE_COOLDOWN ],
			'unknown parsed rejection' => [ '{"success":false,"code":999,"message":"remote secret"}', 403, true, SyncObservation::RESULT_PARSED_REJECTION, 7 ],
			'invalid export data' => [ '{"success":true,"data":[]}', 200, true, SyncObservation::RESULT_INVALID_EXPORT_DATA, 8 ],
			'invalid nested options' => [ '{"success":true,"data":{"options":"invalid","ip_rules":[]}}', 200, true, SyncObservation::RESULT_INVALID_EXPORT_DATA, 0 ],
			'invalid nested IP rule' => [ '{"success":true,"data":{"options":[],"ip_rules":["invalid"]}}', 200, true, SyncObservation::RESULT_INVALID_EXPORT_DATA, 0 ],
			'http 403 success' => [ '{"success":true,"data":{"options":[],"ip_rules":[]}}', 403, true, SyncObservation::RESULT_NETWORK_IMPORT_COMPLETED, null ],
			'completed' => [ '{"success":true,"data":{"options":[],"ip_rules":[]}}', 200, true, SyncObservation::RESULT_NETWORK_IMPORT_COMPLETED, null ],
		];
	}

	public function test_from_site_records_genuine_local_application_exception() :void {
		$this->httpRequest->setGetResponse( '{"success":true,"data":{"options":{"test_option":"value"},"ip_rules":[]}}' );
		$this->events->throwOn( 'options_imported', new \RuntimeException( 'local application failed' ) );
		$import = new Import();

		try {
			$import->fromSite( 'https://source-master.example.com' );
			$this->fail( 'Expected local application failure.' );
		}
		catch ( \RuntimeException $e ) {
			$this->assertSame( 'local application failed', $e->getMessage() );
		}

		$this->assertSame( SyncObservation::RESULT_LOCAL_IMPORT_EXCEPTION, $import->latestObservation()[ 'result' ] ?? null );
	}

	public function test_parsed_rejection_observation_keeps_allowlisted_category_without_remote_message() :void {
		$hostile = '<script>remote secret</script>';
		$this->httpRequest->setGetResponse( (string)\json_encode( [
			'success' => false,
			'code'    => 4,
			'message' => $hostile,
		] ), 403 );
		$import = new Import();

		try {
			$import->fromSite( 'https://source-master.example.com' );
			$this->fail( 'Expected parsed rejection.' );
		}
		catch ( \Exception $e ) {
			$this->assertSame( 7, $e->getCode() );
			$this->assertSame( 'The source site rejected the import request.', $e->getMessage() );
			$this->assertStringNotContainsString( 'remote secret', $e->getMessage() );
		}

		$observation = $import->latestObservation();
		$this->assertSame( SyncObservation::ERROR_REMOTE_EXPORT_EXCEPTION, $observation[ 'error_category' ] ?? null );
		$this->assertStringNotContainsString( 'remote secret', (string)\json_encode( $observation ) );
	}

	public function test_local_validation_observation_has_no_unvalidated_target_fingerprint() :void {
		$import = new Import();
		try {
			$import->fromSite( 'not a URL' );
			$this->fail( 'Expected URL validation failure.' );
		}
		catch ( \Exception $e ) {
			$this->assertSame( 4, $e->getCode() );
		}

		$this->assertSame( SyncObservation::RESULT_LOCAL_INPUT_VALIDATION_FAILED, $import->latestObservation()[ 'result' ] ?? null );
		$this->assertArrayNotHasKey( 'target_fingerprint', $import->latestObservation() ?? [] );
		$this->assertSame( '', $this->httpRequest->lastRequestedUrl() );
	}

	public function test_cron_import_preserves_local_sync_state_and_master_url() :void {
		$this->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_masterurl', 'https://configured-master.example.com' )
			->store();
		$this->wpGeneral->setIsCron( true );
		$this->httpRequest->setResponseOptions( [
			'importexport_enable'    => 'Y',
			'importexport_masterurl' => 'https://imported-master.example.com',
		] );

		( new Import() )->fromSite( 'https://source-master.example.com' );

		$this->assertSame( 'N', (string)$this->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( 'https://configured-master.example.com', (string)$this->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_explicit_network_add_sets_requested_master_url() :void {
		$this->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_masterurl', 'https://current-master.example.com' )
			->store();
		$this->httpRequest->setResponseOptions( [
			'importexport_enable'    => 'N',
			'importexport_masterurl' => 'https://imported-master.example.com',
		] );

		( new Import() )->fromSite( 'https://source-master.example.com', '', true );

		$this->assertSame( 'Y', (string)$this->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( 'https://source-master.example.com', (string)$this->opts->optGet( 'importexport_masterurl' ) );
		$this->assertCount( 1, $this->events->byKey( 'master_url_set' ) );
	}

	public function test_explicit_network_remove_clears_master_url() :void {
		$this->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_masterurl', 'https://current-master.example.com' )
			->store();
		$this->httpRequest->setResponseOptions( [
			'importexport_enable'    => 'Y',
			'importexport_masterurl' => 'https://imported-master.example.com',
		] );

		( new Import() )->fromSite( 'https://source-master.example.com', '', false );

		$this->assertSame( 'Y', (string)$this->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( '', (string)$this->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_from_site_uses_generic_failure_for_non_string_remote_message() :void {
		$this->httpRequest->setContentResponse( [
			'success' => false,
			'message' => [ 'not-a-string' ],
		] );

		try {
			( new Import() )->fromSite( 'https://source-master.example.com' );
			$this->fail( 'Expected the remote import failure to be reported.' );
		}
		catch ( \Exception $e ) {
			$this->assertSame( 6, $e->getCode() );
			$this->assertSame( "Request failed with no error message from the source site.", $e->getMessage() );
		}
	}

	public function test_from_site_legacy_private_mode_wraps_export_request_with_external_host_filter() :void {
		$events = [];
		$this->recordExternalHostFilterEvents( $events );
		$this->httpRequest->setOnGetContent( static function () use ( &$events ) :void {
			$events[] = [
				'operation' => 'http_get_content',
			];
		} );

		( new Import() )->fromSite( 'http://wordpress-master' );

		$this->assertLegacyImportFilterEvents( $events );
		$this->assertSame( [], $this->httpRequest->lastContentArgs() );
	}

	public function test_from_site_legacy_private_mode_removes_external_filter_when_export_request_fails() :void {
		$events = [];
		$this->recordExternalHostFilterEvents( $events );
		$this->httpRequest->setOnGetContent( static function () use ( &$events ) :void {
			$events[] = [
				'operation' => 'http_get_content',
			];
		} );
		$this->httpRequest->throwOnGetContent( new \RuntimeException( 'export failed' ) );

		try {
			( new Import() )->fromSite( 'http://wordpress-master' );
			$this->fail( 'Expected import request failure.' );
		}
		catch ( \Throwable $e ) {
			$this->assertSame( 'export failed', $e->getMessage() );
		}

		$this->assertLegacyImportFilterEvents( $events );
	}

	public function test_from_site_public_only_mode_does_not_add_external_host_filter_and_rejects_unsafe_urls() :void {
		$events = [];
		$this->recordExternalHostFilterEvents( $events );
		$this->httpRequest->setOnGetContent( static function () use ( &$events ) :void {
			$events[] = [
				'operation' => 'http_get_content',
			];
		} );

		( new Import() )->fromSite( 'https://93.184.216.34', '', true, Import::REQUEST_SAFETY_PUBLIC_ONLY );

		$this->assertSame( [
			[
				'operation' => 'http_get_content',
			],
		], $events );
		$this->assertTrue( (bool)( $this->httpRequest->lastContentArgs()[ 'reject_unsafe_urls' ] ?? false ) );
		$this->assertSame( 'https://93.184.216.34', (string)$this->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_from_site_public_only_validation_failure_leaves_sync_state_unchanged() :void {
		$this->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_masterurl', 'https://current-master.example.com' )
			->optSet( 'importexport_handshake_expires_at', 0 )
			->store();

		try {
			( new Import() )->fromSite( 'https://10.0.0.25', '', true, Import::REQUEST_SAFETY_PUBLIC_ONLY );
			$this->fail( 'Expected public-only import URL validation to fail.' );
		}
		catch ( \Exception $e ) {
			$this->assertSame( 4, $e->getCode() );
		}

		$this->assertSame( 'N', (string)$this->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( 'https://current-master.example.com', (string)$this->opts->optGet( 'importexport_masterurl' ) );
		$this->assertSame( 0, (int)$this->opts->optGet( 'importexport_handshake_expires_at' ) );
		$this->assertSame( '', $this->httpRequest->lastRequestedUrl() );
	}

	public function test_from_site_trusted_sync_mode_wraps_export_request_with_scoped_filter() :void {
		$events = [];
		$this->recordExternalHostFilterEvents( $events, '93.184.216.34' );
		$this->httpRequest->setOnGetContent( static function () use ( &$events ) :void {
			$events[] = [
				'operation' => 'http_get_content',
			];
		} );

		( new Import() )->fromSite( 'https://93.184.216.34', '', true, Import::REQUEST_SAFETY_TRUSTED_SYNC );

		$this->assertScopedExternalHostFilterEvents( $events, 'http_get_content' );
		$this->assertTrue( (bool)( $this->httpRequest->lastContentArgs()[ 'reject_unsafe_urls' ] ?? false ) );
		$this->assertSame( 'https://93.184.216.34', (string)$this->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_from_site_trusted_sync_mode_removes_scoped_filter_when_export_request_fails() :void {
		$events = [];
		$this->recordExternalHostFilterEvents( $events, '93.184.216.34' );
		$this->httpRequest->setOnGetContent( static function () use ( &$events ) :void {
			$events[] = [
				'operation' => 'http_get_content',
			];
		} );
		$this->httpRequest->throwOnGetContent( new \RuntimeException( 'trusted export failed' ) );

		try {
			( new Import() )->fromSite( 'https://93.184.216.34', '', true, Import::REQUEST_SAFETY_TRUSTED_SYNC );
			$this->fail( 'Expected trusted sync import request failure.' );
		}
		catch ( \Throwable $e ) {
			$this->assertSame( 'trusted export failed', $e->getMessage() );
		}

		$this->assertScopedExternalHostFilterEvents( $events, 'http_get_content' );
	}

	public function test_invite_sender_trusted_sync_mode_wraps_post_with_scoped_filter() :void {
		$this->wpGeneral->setHomeUrl( 'https://93.184.216.35' );
		$events = [];
		$this->recordExternalHostFilterEvents( $events, '93.184.216.36' );
		$this->httpRequest->setOnPost( static function () use ( &$events ) :void {
			$events[] = [
				'operation' => 'http_post',
			];
		} );

		$result = ( new SyncSiteInviteSender() )->send( 'https://93.184.216.36/client-site' );

		$this->assertSame(
			\FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites\InvitationMetadata::RESULT_HTTP_RESPONSE,
			$result[ 'result' ] ?? ''
		);
		$this->assertScopedExternalHostFilterEvents( $events, 'http_post' );
		$this->assertStringContainsString( PluginImportExport_NetworkInviteRequest::SLUG, $this->httpRequest->lastPostRequestedUrl() );
		$this->assertTrue( (bool)( $this->httpRequest->lastPostArgs()[ 'reject_unsafe_urls' ] ?? false ) );
		$this->assertSame( 'https://93.184.216.35', $this->httpRequest->lastPostArgs()[ 'body' ][ 'master_url' ] ?? '' );
	}

	/**
	 * @dataProvider inviteSenderResultProvider
	 */
	public function test_invite_sender_classifies_sanitized_outcomes(
		int $httpCode,
		bool $success,
		?\Throwable $exception,
		string $expectedResult
	) :void {
		$this->wpGeneral->setHomeUrl( 'https://93.184.216.35' );
		$this->httpRequest->setPostOutcome( $httpCode, $success, $exception );

		$result = ( new SyncSiteInviteSender() )->send( 'https://93.184.216.36/client-site' );

		$this->assertSame( $expectedResult, $result[ 'result' ] );
		$this->assertSame( $httpCode, $result[ 'http_status' ] );
		$this->assertSame( [ 'result', 'http_status' ], \array_keys( $result ) );
	}

	public static function inviteSenderResultProvider() :array {
		return [
			'http 200'        => [ 200, true, null, InvitationMetadata::RESULT_HTTP_RESPONSE ],
			'http 204'        => [ 204, true, null, InvitationMetadata::RESULT_HTTP_RESPONSE ],
			'http 403'        => [ 403, false, null, InvitationMetadata::RESULT_HTTP_FAILURE ],
			'http 429'        => [ 429, false, null, InvitationMetadata::RESULT_HTTP_FAILURE ],
			'http 500'        => [ 500, false, null, InvitationMetadata::RESULT_HTTP_FAILURE ],
			'transport error' => [ 0, false, null, InvitationMetadata::RESULT_TRANSPORT_FAILURE ],
			'sender exception' => [ 0, false, new \RuntimeException( 'secret diagnostic' ), InvitationMetadata::RESULT_SENDER_FAILURE ],
		];
	}

	public function test_invite_sender_rejects_unsafe_url_without_http() :void {
		$result = ( new SyncSiteInviteSender() )->send( 'https://127.0.0.1/client-site' );

		$this->assertSame( InvitationMetadata::RESULT_URL_VALIDATION_FAILURE, $result[ 'result' ] );
		$this->assertSame( '', $this->httpRequest->lastPostRequestedUrl() );
	}

	public function test_from_site_rejects_unknown_request_safety_mode_without_mutation() :void {
		$this->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_masterurl', 'https://current-master.example.com' )
			->optSet( 'importexport_handshake_expires_at', 0 )
			->store();

		try {
			( new Import() )->fromSite( 'https://93.184.216.34', '', true, 'typo_public_only' );
			$this->fail( 'Expected unknown import request safety mode to fail.' );
		}
		catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'Invalid import request safety mode.', $e->getMessage() );
		}

		$this->assertSame( 'N', (string)$this->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( 'https://current-master.example.com', (string)$this->opts->optGet( 'importexport_masterurl' ) );
		$this->assertSame( 0, (int)$this->opts->optGet( 'importexport_handshake_expires_at' ) );
		$this->assertSame( '', $this->httpRequest->lastRequestedUrl() );
	}

	/**
	 * @dataProvider providerNotifyNoopScenarios
	 */
	public function test_notify_requires_enabled_local_slave_configuration(
		string $importExportEnabled,
		string $masterUrl
	) :void {
		$this->opts
			->optSet( 'importexport_enable', $importExportEnabled )
			->optSet( 'importexport_masterurl', $masterUrl )
			->store();

		$accepted = ( new ImportExportController() )->runOptionsUpdateNotified( $masterUrl );

		$this->assertFalse( $accepted );
		$this->assertFalse( $this->scheduledEvents[ $this->notifyCronHook() ] ?? false );
		$this->assertCount( 0, $this->events->byKey( 'import_notify_received' ) );
	}

	public function providerNotifyNoopScenarios() :array {
		return [
			'local sync disabled'  => [ 'N', 'https://configured-master.example.com' ],
			'master url missing'   => [ 'Y', '' ],
		];
	}

	public function test_notify_rejects_when_sync_is_unavailable() :void {
		$this->installControllerStub( new class {
			public function canImportExportSync() :bool {
				return false;
			}
		} );
		$this->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://configured-master.example.com' )
			->store();

		$accepted = ( new ImportExportController() )->runOptionsUpdateNotified( 'https://configured-master.example.com' );

		$this->assertFalse( $accepted );
		$this->assertFalse( $this->scheduledEvents[ $this->notifyCronHook() ] ?? false );
	}

	public function test_notify_rejects_mismatched_master_url_without_scheduling() :void {
		$this->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://configured-master.example.com' )
			->store();

		$accepted = ( new ImportExportController() )->runOptionsUpdateNotified( 'https://different-master.example.com' );

		$this->assertFalse( $accepted );
		$this->assertFalse( $this->scheduledEvents[ $this->notifyCronHook() ] ?? false );
		$this->assertCount( 0, $this->events->byKey( 'import_notify_received' ) );
	}

	public function test_notify_accepts_normalised_matching_master_url() :void {
		$this->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'HTTPS://Configured-Master.Example.COM:443/Master/' )
			->store();

		$accepted = ( new ImportExportController() )->runOptionsUpdateNotified(
			'https://configured-master.example.com/Master?notify=1#source'
		);

		$this->assertTrue( $accepted );
		$this->assertSame( 1712620800, $this->scheduledEvents[ $this->notifyCronHook() ] ?? false );
	}

	public function test_notify_schedules_due_now_for_enabled_configured_slave() :void {
		$this->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://configured-master.example.com' )
			->store();
		$accepted = ( new ImportExportController() )->runOptionsUpdateNotified( 'https://configured-master.example.com' );

		$this->assertTrue( $accepted );
		$this->assertSame( 1712620800, $this->scheduledEvents[ $this->notifyCronHook() ] ?? false );
		$this->assertCount( 1, $this->events->byKey( 'import_notify_received' ) );
	}

	public function test_notify_keeps_existing_due_import_event_as_already_scheduled() :void {
		$this->scheduledEvents[ $this->notifyCronHook() ] = 1712620799;
		$this->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://configured-master.example.com' )
			->store();

		$accepted = ( new ImportExportController() )->runOptionsUpdateNotified( 'https://configured-master.example.com' );

		$this->assertTrue( $accepted );
		$this->assertSame( 1712620799, $this->scheduledEvents[ $this->notifyCronHook() ] ?? false );
		$this->assertCount( 0, $this->events->byKey( 'import_notify_received' ) );
	}

	public function test_notify_replaces_future_import_event_with_due_now_event() :void {
		$this->scheduledEvents[ $this->notifyCronHook() ] = 1712620900;
		$this->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://configured-master.example.com' )
			->store();

		$accepted = ( new ImportExportController() )->runOptionsUpdateNotified( 'https://configured-master.example.com' );

		$this->assertTrue( $accepted );
		$this->assertSame( 1712620800, $this->scheduledEvents[ $this->notifyCronHook() ] ?? false );
		$this->assertCount( 1, $this->events->byKey( 'import_notify_received' ) );
	}

	public function test_repeated_valid_notify_inside_cooldown_is_silently_dropped() :void {
		$this->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://configured-master.example.com' )
			->store();

		$first = ( new ImportExportController() )->runOptionsUpdateNotified( 'https://configured-master.example.com' );
		unset( $this->scheduledEvents[ $this->notifyCronHook() ] );
		$second = ( new ImportExportController() )->runOptionsUpdateNotified( 'https://configured-master.example.com' );

		$this->assertTrue( $first );
		$this->assertFalse( $second );
		$this->assertFalse( $this->scheduledEvents[ $this->notifyCronHook() ] ?? false );
		$this->assertCount( 1, $this->events->byKey( 'import_notify_received' ) );
	}

	public function test_notify_rejects_mismatched_import_id_without_scheduling() :void {
		$this->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://configured-master.example.com' )
			->optSet( 'import_id', 'local-import-id' )
			->store();

		$accepted = ( new ImportExportController() )->runOptionsUpdateNotified(
			'https://configured-master.example.com',
			'other-import-id'
		);

		$this->assertFalse( $accepted );
		$this->assertSame( 'local-import-id', $this->opts->optGet( 'import_id' ) );
		$this->assertFalse( $this->scheduledEvents[ $this->notifyCronHook() ] ?? false );
	}

	public function test_notify_allows_missing_import_id_for_legacy_sync() :void {
		$this->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://configured-master.example.com' )
			->optSet( 'import_id', 'local-import-id' )
			->store();

		$accepted = ( new ImportExportController() )->runOptionsUpdateNotified( 'https://configured-master.example.com' );

		$this->assertTrue( $accepted );
		$this->assertSame( 1712620800, $this->scheduledEvents[ $this->notifyCronHook() ] ?? false );
	}

	public function test_notify_does_not_generate_local_import_id_when_none_exists() :void {
		$this->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://configured-master.example.com' )
			->optSet( 'import_id', '' )
			->store();

		$accepted = ( new ImportExportController() )->runOptionsUpdateNotified(
			'https://configured-master.example.com',
			'master-row-import-id'
		);

		$this->assertTrue( $accepted );
		$this->assertSame( '', $this->opts->optGet( 'import_id' ) );
	}

	public function test_sync_availability_is_false_when_caps_are_missing() :void {
		$this->installControllerStub( new class {} );

		$this->assertFalse( ( new ImportExportController() )->isSyncAvailable() );
	}

	public function test_unavailable_sync_rejects_enable_and_queue_without_scheduling() :void {
		$this->installControllerStub( new class {
			public function canImportExportSync() :bool {
				return false;
			}
		} );

		try {
			( new ImportExportController() )->enableAutomaticImportExport();
			$this->fail( 'Expected unavailable sync enable to fail.' );
		}
		catch ( \RuntimeException $e ) {
			$this->assertSame( 'Import/export sync is not available on this plan.', $e->getMessage() );
		}

		try {
			( new ImportExportController() )->queueSitesForSync( [ 1 ] );
			$this->fail( 'Expected unavailable sync queue to fail.' );
		}
		catch ( \RuntimeException $e ) {
			$this->assertSame( 'Import/export sync is not available on this plan.', $e->getMessage() );
		}

		$this->assertSame( 'N', (string)$this->opts->optGet( 'importexport_enable' ) );
		$this->assertFalse( $this->scheduledEvents[ $this->queueCronHook() ] ?? false );
	}

	public function test_schedule_queue_soon_noops_when_caps_are_unavailable() :void {
		$this->installControllerStub( new class {
			public function canImportExportSync() :bool {
				return false;
			}
		} );
		$this->opts->optSet( 'importexport_enable', 'Y' )->store();

		( new ImportExportController() )->scheduleQueueSoonIfSyncEnabled();

		$this->assertFalse( $this->scheduledEvents[ $this->queueCronHook() ] ?? false );
	}

	public function test_schedule_queue_soon_noops_when_caps_throw() :void {
		$this->installControllerStub( new class {
			public function canImportExportSync() :bool {
				throw new \RuntimeException( 'license unavailable' );
			}
		} );
		$this->opts->optSet( 'importexport_enable', 'Y' )->store();

		( new ImportExportController() )->scheduleQueueSoonIfSyncEnabled();

		$this->assertFalse( $this->scheduledEvents[ $this->queueCronHook() ] ?? false );
	}

	public function test_default_queue_scheduler_cannot_schedule_without_predicate() :void {
		( new QueueScheduler() )->scheduleSoon();

		$this->assertFalse( $this->scheduledEvents[ $this->queueCronHook() ] ?? false );
	}

	public function test_queue_scheduler_false_predicate_clears_existing_event() :void {
		$this->scheduledEvents[ $this->queueCronHook() ] = 1712620900;

		( new QueueScheduler( static fn() :bool => false ) )->scheduleSoon();

		$this->assertFalse( $this->scheduledEvents[ $this->queueCronHook() ] ?? false );
	}

	public function test_queue_scheduler_true_predicate_schedules_promptly() :void {
		( new QueueScheduler( static fn() :bool => true ) )->scheduleSoon( 45 );

		$this->assertSame( 1712620845, $this->scheduledEvents[ $this->queueCronHook() ] ?? false );
	}

	public function test_queue_scheduler_recreates_health_event_before_running_worker() :void {
		$callbacks = [];
		Functions\when( 'add_action' )->alias( static function ( string $hook, callable $callback ) use ( &$callbacks ) :bool {
			$callbacks[ $hook ] = $callback;
			return true;
		} );
		$hook = $this->queueCronHook();
		$workerRan = false;
		$scheduler = new QueueScheduler(
			static fn() :bool => true,
			function () use ( &$workerRan, $hook ) :void {
				$this->assertSame( 1712621100, $this->scheduledEvents[ $hook ] ?? false );
				$workerRan = true;
			}
		);
		$scheduler->setup();
		unset( $this->scheduledEvents[ $hook ] );

		$callbacks[ $hook ]();

		$this->assertTrue( $workerRan );
	}

	public function test_queue_processor_identity_is_specific_to_current_blog() :void {
		$blogID = 17;
		Functions\when( 'get_current_blog_id' )->alias( static function () use ( &$blogID ) :int {
			return $blogID;
		} );
		Functions\when( 'add_action' )->justReturn( true );

		$first = ( new ImportExportQueueProcessorIdentityTestDouble() )->identifierForTest();
		$blogID = 29;
		$second = ( new ImportExportQueueProcessorIdentityTestDouble() )->identifierForTest();

		$this->assertNotSame( $first, $second );
		$this->assertStringEndsWith( 'importexport_sites_queue_17', $first );
		$this->assertStringEndsWith( 'importexport_sites_queue_29', $second );
	}

	public function test_schedule_queue_soon_schedules_when_enabled_and_available() :void {
		$this->opts->optSet( 'importexport_enable', 'Y' )->store();

		( new ImportExportController() )->scheduleQueueSoonIfSyncEnabled( 45 );

		$this->assertSame( 1712620845, $this->scheduledEvents[ $this->queueCronHook() ] ?? false );
	}

	public function test_schedule_queue_soon_clears_existing_event_when_available_but_disabled() :void {
		$this->scheduledEvents[ $this->queueCronHook() ] = 1712620900;
		$this->opts->optSet( 'importexport_enable', 'N' )->store();

		( new ImportExportController() )->scheduleQueueSoonIfSyncEnabled();

		$this->assertFalse( $this->scheduledEvents[ $this->queueCronHook() ] ?? false );
	}

	public function test_disabling_sync_clears_existing_queue_health_event() :void {
		$this->opts->optSet( 'importexport_enable', 'Y' )->store();
		$this->scheduledEvents[ $this->queueCronHook() ] = 1712620900;

		( new ImportExportController() )->setAutomaticImportExportEnabled( false );

		$this->assertSame( 'N', $this->opts->optGet( 'importexport_enable' ) );
		$this->assertFalse( $this->scheduledEvents[ $this->queueCronHook() ] ?? false );
	}

	public function test_ping_sender_uses_trusted_sync_policy_for_private_resolved_hosts() :void {
		$events = [];
		$sender = $this->buildPingSenderWithRecordedFilters( $events );
		$this->httpRequest->setOnGet( static function () use ( &$events ) :void {
			$events[] = [
				'operation' => 'http_get',
			];
		} );

		$result = $sender->send( 'https://client.example.com' );

		$this->assertPingResult( true, 200, '', SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED, 200, $result );
		$this->assertScopedExternalHostFilterEvents( $events );
		$this->assertStringContainsString( PluginImportExport_UpdateNotified::SLUG, $this->httpRequest->lastGetRequestedUrl() );
		$this->assertStringContainsString( 'master_url=', $this->httpRequest->lastGetRequestedUrl() );
		$args = $this->httpRequest->lastGetArgs();
		$this->assertSame( 5, $args[ 'timeout' ] );
		$this->assertTrue( $args[ 'reject_unsafe_urls' ] );
	}

	public function test_ping_sender_adds_import_id_when_supplied() :void {
		$this->buildPingSender()->send( 'https://client.example.com', 5, 'client-import-id' );

		$this->assertStringContainsString( 'id=client-import-id', $this->httpRequest->lastGetRequestedUrl() );
	}

	public function test_ping_sender_uses_canonical_client_and_master_urls() :void {
		$this->wpGeneral->setHomeUrl( 'HTTPS://LOCAL.Example.COM:443/Master/' );

		$this->buildPingSender()->send( 'HTTPS://CLIENT.Example.COM:443/Path/' );

		$requestedUrl = \urldecode( $this->httpRequest->lastGetRequestedUrl() );
		$this->assertStringContainsString( 'https://client.example.com/Path', $requestedUrl );
		$this->assertStringContainsString( 'master_url=https://local.example.com/Master', $requestedUrl );
	}

	/**
	 * @dataProvider providePingSenderOutcomes
	 */
	public function test_ping_sender_records_bounded_outcome_without_changing_dispatch_semantics(
		string $url,
		int $httpCode,
		bool $httpSuccess,
		bool $expectedSuccess,
		string $expectedError,
		string $expectedResult,
		?int $expectedStatus,
		bool $requestExpected
	) :void {
		$this->httpRequest->setGetResponse( '{not-json', $httpCode, $httpSuccess, 'Operation timed out' );

		$result = $this->buildPingSender()->send( $url, 5 );

		$this->assertPingResult( $expectedSuccess, $httpCode, $expectedError, $expectedResult, $expectedStatus, $result );
		$this->assertSame( $requestExpected, $this->httpRequest->lastGetRequestedUrl() !== '' );
	}

	public static function providePingSenderOutcomes() :array {
		return [
			'http 200'       => [ 'https://client.example.com', 200, true, true, '', SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED, 200, true ],
			'http 403'       => [ 'https://client.example.com', 403, true, true, '', SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED, 403, true ],
			'http 500'       => [ 'https://client.example.com', 500, true, true, '', SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED, 500, true ],
			'no response'    => [ 'https://client.example.com', 0, false, true, '', SyncObservation::RESULT_NO_HTTP_RESPONSE, null, true ],
			'invalid target' => [ 'not-a-url', 0, true, false, 'invalid_url', SyncObservation::RESULT_LOCAL_TARGET_VALIDATION_FAILED, null, false ],
		];
	}

	public function test_ping_sender_rejects_literal_private_ip_before_request() :void {
		$result = $this->buildPingSender()->send( 'https://10.0.0.25', 5 );

		$this->assertPingResult( false, 0, 'invalid_url', SyncObservation::RESULT_LOCAL_TARGET_VALIDATION_FAILED, null, $result );
		$this->assertSame( '', $this->httpRequest->lastGetRequestedUrl() );
	}

	public function test_ping_sender_removes_external_host_filter_when_request_fails() :void {
		$events = [];
		$sender = $this->buildPingSenderWithRecordedFilters( $events );
		$this->httpRequest->setOnGet( static function () use ( &$events ) :void {
			$events[] = [
				'operation' => 'http_get',
			];
		} );
		$this->httpRequest->throwOnGet( new \RuntimeException( 'notify failed' ) );

		try {
			$sender->send( 'https://client.example.com' );
			$this->fail( 'Expected whitelist notification request failure.' );
		}
		catch ( \RuntimeException $exception ) {
			$this->assertSame( 'notify failed', $exception->getMessage() );
		}

		$this->assertScopedExternalHostFilterEvents( $events );
	}

	private function installControllerStub( ?object $caps = null ) :void {
		$this->controller = UnitTestControllerFactory::install(
			null,
			null,
			(object)[
				'cfg'  => (object)[
					'properties' => [
						'slug_parent' => 'icwp',
						'slug_plugin' => 'wpsf',
					],
				],
				'opts' => $this->opts,
				'caps' => $caps ?? new class {
					public function canImportExportSync() :bool {
						return true;
					}
				},
				'comps' => (object)[
					'events'      => $this->events,
					'opts_lookup' => new class {
						public function getXferExcluded() :array {
							return [];
						}
					},
				],
			]
		);
	}

	private function notifyCronHook() :string {
		return $this->controller->prefix( PluginImportExport_UpdateNotified::SLUG );
	}

	private function queueCronHook() :string {
		return ( new QueueScheduler() )->hook();
	}

	/**
	 * @param array<int,array<string,mixed>> $events
	 */
	private function buildPingSenderWithRecordedFilters( array &$events ) :PingSender {
		Functions\when( 'add_action' )->justReturn( true );
		$this->recordExternalHostFilterEvents( $events, 'client.example.com' );

		$events = [];
		return $this->buildPingSender();
	}

	private function buildPingSender( array $resolvedIps = [ '10.0.0.25' ] ) :PingSender {
		return new PingSender( new SyncSiteUrlValidator( static fn() :array => $resolvedIps ) );
	}

	private function assertPingResult(
		bool $success,
		int $httpCode,
		string $error,
		string $observationResult,
		?int $httpStatus,
		array $result
	) :void {
		$this->assertSame( $success, $result[ 'success' ] );
		$this->assertSame( $httpCode, $result[ 'http_code' ] );
		$this->assertSame( $error, $result[ 'error' ] );
		$this->assertIsArray( $result[ 'observation' ] );
		$this->assertSame( SyncObservation::PHASE_NOTIFICATION, $result[ 'observation' ][ 'phase' ] );
		$this->assertSame( SyncObservation::VERIFICATION_NOT_APPLICABLE, $result[ 'observation' ][ 'verification' ] );
		$this->assertSame( $observationResult, $result[ 'observation' ][ 'result' ] );
		$this->assertSame( $httpStatus, $result[ 'observation' ][ 'http_status' ] ?? null );
	}

	private function recordExternalHostFilterEvents( array &$events, string $probeTargetHost = '' ) :void {
		Functions\when( 'add_filter' )->alias(
			static function ( $tag, $callback, $priority = 10, $acceptedArgs = 1 ) use ( &$events, $probeTargetHost ) :bool {
				$event = [
					'operation'     => 'add_filter',
					'tag'           => (string)$tag,
					'callback'      => \is_string( $callback ) ? $callback : 'callable',
					'callback_id'   => \is_object( $callback ) ? \spl_object_id( $callback ) : 0,
					'priority'      => (int)$priority,
					'accepted_args' => (int)$acceptedArgs,
				];
				if ( $probeTargetHost !== '' && (string)$tag === 'http_request_host_is_external' && \is_callable( $callback ) ) {
					$event[ 'allows_target_host' ] = $callback( false, $probeTargetHost );
					$event[ 'allows_other_host' ] = $callback( false, 'wordpress-other' );
					$event[ 'preserves_existing_external_host' ] = $callback( true, 'wordpress-other' );
				}
				$events[] = $event;
				return true;
			}
		);
		Functions\when( 'remove_filter' )->alias(
			static function ( $tag, $callback, $priority = 10 ) use ( &$events ) :bool {
				$events[] = [
					'operation'   => 'remove_filter',
					'tag'         => (string)$tag,
					'callback'    => \is_string( $callback ) ? $callback : 'callable',
					'callback_id' => \is_object( $callback ) ? \spl_object_id( $callback ) : 0,
					'priority'    => (int)$priority,
				];
				return true;
			}
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $events
	 */
	private function assertLegacyImportFilterEvents( array $events ) :void {
		$this->assertCount( 3, $events );
		$this->assertSame( 'add_filter', $events[ 0 ][ 'operation' ] ?? '' );
		$this->assertSame( 'http_request_host_is_external', $events[ 0 ][ 'tag' ] ?? '' );
		$this->assertSame( '\__return_true', $events[ 0 ][ 'callback' ] ?? '' );
		$this->assertSame( 11, $events[ 0 ][ 'priority' ] ?? 0 );

		$this->assertSame( 'http_get_content', $events[ 1 ][ 'operation' ] ?? '' );

		$this->assertSame( 'remove_filter', $events[ 2 ][ 'operation' ] ?? '' );
		$this->assertSame( 'http_request_host_is_external', $events[ 2 ][ 'tag' ] ?? '' );
		$this->assertSame( '\__return_true', $events[ 2 ][ 'callback' ] ?? '' );
		$this->assertSame( 11, $events[ 2 ][ 'priority' ] ?? 0 );
	}

	/**
	 * @param array<int,array<string,mixed>> $events
	 */
	private function assertScopedExternalHostFilterEvents( array $events, string $requestOperation = 'http_get' ) :void {
		$this->assertCount( 3, $events );
		$this->assertSame( 'add_filter', $events[ 0 ][ 'operation' ] ?? '' );
		$this->assertSame( 'http_request_host_is_external', $events[ 0 ][ 'tag' ] ?? '' );
		$this->assertSame( 'callable', $events[ 0 ][ 'callback' ] ?? '' );
		$this->assertSame( 11, $events[ 0 ][ 'priority' ] ?? 0 );
		$this->assertSame( 2, $events[ 0 ][ 'accepted_args' ] ?? 0 );
		$this->assertTrue( $events[ 0 ][ 'allows_target_host' ] ?? false );
		$this->assertFalse( $events[ 0 ][ 'allows_other_host' ] ?? true );
		$this->assertTrue( $events[ 0 ][ 'preserves_existing_external_host' ] ?? false );

		$this->assertSame( $requestOperation, $events[ 1 ][ 'operation' ] ?? '' );

		$this->assertSame( 'remove_filter', $events[ 2 ][ 'operation' ] ?? '' );
		$this->assertSame( 'http_request_host_is_external', $events[ 2 ][ 'tag' ] ?? '' );
		$this->assertSame( 'callable', $events[ 2 ][ 'callback' ] ?? '' );
		$this->assertSame( 11, $events[ 2 ][ 'priority' ] ?? 0 );
		$this->assertSame( $events[ 0 ][ 'callback_id' ] ?? null, $events[ 2 ][ 'callback_id' ] ?? null );
	}

}

class ImportExportQueueProcessorIdentityTestDouble extends QueueProcessor {

	public function __construct() {
		parent::__construct( static fn() :bool => true );
	}

	public function identifierForTest() :string {
		return $this->identifier;
	}
}

class ImportExportOptsStoreStub {

	private array $values;
	private bool $hasChanges = false;

	public function __construct( array $values ) {
		$this->values = $values;
	}

	public function hasChanges() :bool {
		return $this->hasChanges;
	}

	public function optGet( string $key ) {
		return $this->values[ $key ] ?? null;
	}

	public function optIs( string $key, $value ) :bool {
		return $this->optGet( $key ) == $value;
	}

	public function optSet( string $key, $value ) :self {
		if ( !\array_key_exists( $key, $this->values ) || $this->values[ $key ] !== $value ) {
			$this->hasChanges = true;
		}
		$this->values[ $key ] = $value;
		return $this;
	}

	public function store() :self {
		$this->hasChanges = false;
		return $this;
	}
}

class ImportExportEventsRecorderStub {

	/** @var array<int,array{event:string,meta:array}> */
	public array $fired = [];
	/** @var array<string,\Throwable> */
	private array $exceptions = [];

	public function throwOn( string $event, \Throwable $exception ) :void {
		$this->exceptions[ $event ] = $exception;
	}

	public function fireEvent( string $event, array $meta = [] ) :void {
		if ( isset( $this->exceptions[ $event ] ) ) {
			throw $this->exceptions[ $event ];
		}
		$this->fired[] = [
			'event' => $event,
			'meta'  => $meta,
		];
	}

	public function byKey( string $eventKey ) :array {
		return \array_values( \array_filter(
			$this->fired,
			static fn( array $event ) :bool => $event[ 'event' ] === $eventKey
		) );
	}
}

class ImportExportHttpRequestStub extends HttpRequest {

	private array $responseOptions = [];
	private ?array $contentResponse = null;
	private string $lastRequestedUrl = '';
	private string $lastGetRequestedUrl = '';
	private string $lastPostRequestedUrl = '';
	private array $lastContentArgs = [];
	private array $lastGetArgs = [];
	private array $lastPostArgs = [];
	private ?string $getResponseBody = null;
	private int $getResponseCode = 200;
	private bool $getSuccess = true;
	private string $getError = '';
	private ?\Throwable $getException = null;
	private ?\Throwable $getContentException = null;
	private $onGet = null;
	private $onGetContent = null;
	private $onPost = null;
	private int $postResponseCode = 200;
	private bool $postSuccess = true;
	private ?\Throwable $postException = null;

	public function setResponseOptions( array $options ) :void {
		$this->responseOptions = $options;
	}

	public function setContentResponse( array $response ) :void {
		$this->contentResponse = $response;
	}

	public function lastRequestedUrl() :string {
		return $this->lastRequestedUrl;
	}

	public function lastGetRequestedUrl() :string {
		return $this->lastGetRequestedUrl;
	}

	public function lastPostRequestedUrl() :string {
		return $this->lastPostRequestedUrl;
	}

	public function lastGetArgs() :array {
		return $this->lastGetArgs;
	}

	public function lastContentArgs() :array {
		return $this->lastContentArgs;
	}

	public function lastPostArgs() :array {
		return $this->lastPostArgs;
	}

	public function throwOnGet( \Throwable $throwable ) :void {
		$this->getException = $throwable;
	}

	public function throwOnGetContent( \Throwable $throwable ) :void {
		$this->getContentException = $throwable;
	}

	public function setGetResponse( string $body, int $code = 200, bool $success = true, string $error = '' ) :void {
		$this->getResponseBody = $body;
		$this->getResponseCode = $code;
		$this->getSuccess = $success;
		$this->getError = $error;
	}

	public function setOnGet( callable $callback ) :void {
		$this->onGet = $callback;
	}

	public function setOnGetContent( callable $callback ) :void {
		$this->onGetContent = $callback;
	}

	public function setOnPost( callable $callback ) :void {
		$this->onPost = $callback;
	}

	public function setPostOutcome( int $httpCode, bool $success, ?\Throwable $exception = null ) :void {
		$this->postResponseCode = $httpCode;
		$this->postSuccess = $success;
		$this->postException = $exception;
	}

	public function get( $url, $args = [] ) :bool {
		$this->lastGetRequestedUrl = (string)$url;
		$this->lastGetArgs = \is_array( $args ) ? $args : [];
		if ( \is_callable( $this->onGet ) ) {
			( $this->onGet )( $url, $args );
		}
		if ( $this->getException !== null ) {
			throw $this->getException;
		}
		$this->lastResponse = !$this->getSuccess && $this->getResponseCode <= 0 ? null : ( new WpHttpResponseVo() )->applyFromArray( [
			'headers'  => [],
			'body'     => $this->getResponseBody(),
			'response' => [
				'code'    => $this->getResponseCode,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		] );
		$this->lastError = $this->getSuccess ? null : new class( $this->getError ) {
			private string $message;

			public function __construct( string $message ) {
				$this->message = $message;
			}

			public function get_error_message() :string {
				return $this->message;
			}
		};
		return $this->getSuccess;
	}

	public function getContent( string $url, $args = [] ) :string {
		$this->lastRequestedUrl = $url;
		$this->lastContentArgs = \is_array( $args ) ? $args : [];
		if ( \is_callable( $this->onGetContent ) ) {
			( $this->onGetContent )( $url, $args );
		}
		if ( $this->getContentException !== null ) {
			throw $this->getContentException;
		}

		$body = $this->getResponseBody ?? (string)\json_encode( $this->contentResponse ?? [
			'success' => true,
			'data'    => [
				'options'  => $this->responseOptions,
				'ip_rules' => [],
			],
		] );
		$this->lastResponse = $this->getResponseCode > 0 ? ( new WpHttpResponseVo() )->applyFromArray( [
			'headers'  => [],
			'body'     => $body,
			'response' => [
				'code'    => $this->getResponseCode,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		] ) : null;
		$this->lastError = $this->getSuccess ? null : new class( $this->getError ) {
			private string $message;

			public function __construct( string $message ) {
				$this->message = $message;
			}

			public function get_error_message() :string {
				return $this->message;
			}
		};

		return $body;
	}

	public function post( string $url, $args = [] ) :bool {
		$this->lastPostRequestedUrl = $url;
		$this->lastPostArgs = \is_array( $args ) ? $args : [];
		if ( \is_callable( $this->onPost ) ) {
			( $this->onPost )( $url, $args );
		}
		if ( $this->postException !== null ) {
			throw $this->postException;
		}
		$this->lastResponse = $this->postResponseCode > 0 ? ( new WpHttpResponseVo() )->applyFromArray( [
			'headers'  => [],
			'body'     => '',
			'response' => [
				'code'    => $this->postResponseCode,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		] ) : null;
		return $this->postSuccess;
	}

	private function getResponseBody() :string {
		if ( $this->getResponseBody !== null ) {
			return $this->getResponseBody;
		}

		return '';
	}
}

class ImportExportGeneralStub extends General {

	private bool $isCron = false;
	private string $homeUrl = 'https://local.example.com';

	public function getHomeUrl( string $path = '', bool $wpms = false ) :string {
		return $this->homeUrl;
	}

	public function isCron() :bool {
		return $this->isCron;
	}

	public function setIsCron( bool $isCron ) :void {
		$this->isCron = $isCron;
	}

	public function setHomeUrl( string $homeUrl ) :void {
		$this->homeUrl = $homeUrl;
	}
}

class ImportExportRequestStub extends Request {

	public function carbon( $setTimezone = false, bool $userLocale = true ) :Carbon {
		return Carbon::createFromTimestampUTC( $this->ts() );
	}

	public function ts( bool $update = true ) :int {
		return 1712620800;
	}
}
