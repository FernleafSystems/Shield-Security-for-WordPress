<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\ImportExport;

use Brain\Monkey\Functions;
use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	PluginImportExport_NetworkInviteRequest,
	PluginImportExport_UpdateNotified
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Import;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\PingSender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\QueueScheduler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SyncSiteInviteSender;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Services\Core\General;
use FernleafSystems\Wordpress\Services\Core\Request;
use FernleafSystems\Wordpress\Services\Utilities\HttpRequest;
use FernleafSystems\Wordpress\Services\Utilities\Data;

class ImportExportSyncHardeningTest extends BaseUnitTest {

	private Controller $controller;
	private ImportExportOptsStoreStub $opts;
	private ImportExportEventsRecorderStub $events;
	private ImportExportHttpRequestStub $httpRequest;
	private ImportExportGeneralStub $wpGeneral;
	private array $scheduledEvents = [];
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
			static fn( string $hook ) => $scheduledEvents[ $hook ] ?? false
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

		$this->assertTrue( $result[ 'success' ] ?? false );
		$this->assertScopedExternalHostFilterEvents( $events, 'http_post' );
		$this->assertStringContainsString( PluginImportExport_NetworkInviteRequest::SLUG, $this->httpRequest->lastPostRequestedUrl() );
		$this->assertTrue( (bool)( $this->httpRequest->lastPostArgs()[ 'reject_unsafe_urls' ] ?? false ) );
		$this->assertSame( 'https://93.184.216.35', $this->httpRequest->lastPostArgs()[ 'body' ][ 'master_url' ] ?? '' );
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

		( new ImportExportController() )->runOptionsUpdateNotified();

		$this->assertFalse( $this->scheduledEvents[ $this->notifyCronHook() ] ?? false );
		$this->assertCount( 0, $this->events->byKey( 'import_notify_received' ) );
	}

	public static function providerNotifyNoopScenarios() :array {
		return [
			'local sync disabled'  => [ 'N', 'https://configured-master.example.com' ],
			'master url missing'   => [ 'Y', '' ],
		];
	}

	public function test_notify_schedules_once_for_enabled_configured_slave() :void {
		$this->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', 'https://configured-master.example.com' )
			->store();

		( new ImportExportController() )->runOptionsUpdateNotified();

		$this->assertNotFalse( $this->scheduledEvents[ $this->notifyCronHook() ] ?? false );
		$this->assertCount( 1, $this->events->byKey( 'import_notify_received' ) );
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

	public function test_ping_sender_allows_external_hosts_only_around_request() :void {
		$events = [];
		$sender = $this->buildPingSenderWithRecordedFilters( $events );
		$this->httpRequest->setOnGet( static function () use ( &$events ) :void {
			$events[] = [
				'operation' => 'http_get',
			];
		} );

		$result = $sender->send( 'http://wordpress-slave' );

		$this->assertTrue( $result[ 'success' ] ?? false );
		$this->assertScopedExternalHostFilterEvents( $events );
		$this->assertStringContainsString( PluginImportExport_UpdateNotified::SLUG, $this->httpRequest->lastGetRequestedUrl() );
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
			$sender->send( 'http://wordpress-slave' );
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
		$this->recordExternalHostFilterEvents( $events, 'wordpress-slave' );

		$events = [];
		return new PingSender();
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

	public function fireEvent( string $event, array $meta = [] ) :void {
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
	private string $lastRequestedUrl = '';
	private string $lastGetRequestedUrl = '';
	private string $lastPostRequestedUrl = '';
	private array $lastContentArgs = [];
	private array $lastPostArgs = [];
	private ?\Throwable $getException = null;
	private ?\Throwable $getContentException = null;
	private $onGet = null;
	private $onGetContent = null;
	private $onPost = null;

	public function setResponseOptions( array $options ) :void {
		$this->responseOptions = $options;
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

	public function setOnGet( callable $callback ) :void {
		$this->onGet = $callback;
	}

	public function setOnGetContent( callable $callback ) :void {
		$this->onGetContent = $callback;
	}

	public function setOnPost( callable $callback ) :void {
		$this->onPost = $callback;
	}

	public function get( $url, $args = [] ) :bool {
		$this->lastGetRequestedUrl = (string)$url;
		if ( \is_callable( $this->onGet ) ) {
			( $this->onGet )( $url, $args );
		}
		if ( $this->getException !== null ) {
			throw $this->getException;
		}
		return true;
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

		return (string)\json_encode( [
			'success' => true,
			'data'    => [
				'options'  => $this->responseOptions,
				'ip_rules' => [],
			],
		] );
	}

	public function post( string $url, $args = [] ) :bool {
		$this->lastPostRequestedUrl = $url;
		$this->lastPostArgs = \is_array( $args ) ? $args : [];
		if ( \is_callable( $this->onPost ) ) {
			( $this->onPost )( $url, $args );
		}
		return true;
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
