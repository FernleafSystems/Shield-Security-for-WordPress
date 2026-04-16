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
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_UpdateNotified;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Import;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
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
			static fn( string $url ) => \parse_url( $url ) ?: false
		);
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

	public function providerNotifyNoopScenarios() :array {
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

	private function installControllerStub() :void {
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

	public function setResponseOptions( array $options ) :void {
		$this->responseOptions = $options;
	}

	public function lastRequestedUrl() :string {
		return $this->lastRequestedUrl;
	}

	public function getContent( string $url, $args = [] ) :string {
		$this->lastRequestedUrl = $url;

		return (string)\json_encode( [
			'success' => true,
			'data'    => [
				'options'  => $this->responseOptions,
				'ip_rules' => [],
			],
		] );
	}
}

class ImportExportGeneralStub extends General {

	private bool $isCron = false;

	public function getHomeUrl( string $path = '', bool $wpms = false ) :string {
		return 'https://local.example.com';
	}

	public function isCron() :bool {
		return $this->isCron;
	}

	public function setIsCron( bool $isCron ) :void {
		$this->isCron = $isCron;
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
