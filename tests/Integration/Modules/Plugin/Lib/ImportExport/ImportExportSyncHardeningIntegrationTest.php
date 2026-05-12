<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_UpdateNotified;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_Export;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Import;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\General;

class ImportExportSyncHardeningIntegrationTest extends ShieldIntegrationTestCase {

	private const SOURCE_MASTER_URL = 'https://example.com';
	private const CONFIGURED_MASTER_URL = 'https://example.com/configured-master';

	private array $optionsSnapshot = [];
	private array $servicesSnapshot = [];
	private string $notifyCronHook = '';
	/** @var callable|null */
	private $httpStub = null;

	public function set_up() {
		parent::set_up();
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'importexport_enable',
			'importexport_masterurl',
			'importexport_handshake_expires_at',
			'import_id',
		] );
		$this->notifyCronHook = $this->requireController()->prefix( PluginImportExport_UpdateNotified::SLUG );
		\wp_clear_scheduled_hook( $this->notifyCronHook );
	}

	public function tear_down() {
		if ( \is_callable( $this->httpStub ) ) {
			remove_filter( 'pre_http_request', $this->httpStub, 10 );
			$this->httpStub = null;
		}
		\wp_clear_scheduled_hook( $this->notifyCronHook );
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		ServicesState::restore( $this->servicesSnapshot );
		parent::tear_down();
	}

	public function test_explicit_url_import_restores_local_master_url_and_enables_sync() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_masterurl', 'https://current-master.example.com' )
			->store();

		$this->stubImportResponse( [
			'importexport_enable'    => 'N',
			'importexport_masterurl' => 'https://imported-master.example.com',
		] );

		( new Import() )->fromSite( self::SOURCE_MASTER_URL );

		$this->assertSame( 'Y', (string)$con->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( 'https://current-master.example.com', (string)$con->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_cron_import_preserves_local_sync_state_and_master_url() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_masterurl', self::CONFIGURED_MASTER_URL )
			->store();

		$this->forceCronMode( true );
		$this->stubImportResponse( [
			'importexport_enable'    => 'Y',
			'importexport_masterurl' => 'https://imported-master.example.com',
		] );

		( new Import() )->fromSite();

		$this->assertSame( 'N', (string)$con->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( self::CONFIGURED_MASTER_URL, (string)$con->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_explicit_network_add_sets_requested_master_url() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_masterurl', 'https://current-master.example.com' )
			->store();

		$this->stubImportResponse( [
			'importexport_enable'    => 'N',
			'importexport_masterurl' => 'https://imported-master.example.com',
		] );

		( new Import() )->fromSite( self::SOURCE_MASTER_URL, '', true );

		$this->assertSame( 'Y', (string)$con->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( self::SOURCE_MASTER_URL, (string)$con->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_notify_noops_when_local_sync_is_disabled() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_masterurl', self::CONFIGURED_MASTER_URL )
			->store();
		$this->captureShieldEvents();

		$con->comps->import_export->runOptionsUpdateNotified();

		$this->assertFalse( \wp_next_scheduled( $this->notifyCronHook ) );
		$this->assertCount( 0, $this->getCapturedEventsByKey( 'import_notify_received' ) );
	}

	public function test_notify_schedules_when_local_sync_is_enabled_and_master_is_configured() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', self::CONFIGURED_MASTER_URL )
			->store();
		$this->captureShieldEvents();

		$con->comps->import_export->runOptionsUpdateNotified();

		$this->assertNotFalse( \wp_next_scheduled( $this->notifyCronHook ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'import_notify_received' ) );
	}

	private function forceCronMode( bool $isCron ) :void {
		ServicesState::mergeItems( [
			'service_wpgeneral' => new class( $isCron ) extends General {

				private bool $isCron;

				public function __construct( bool $isCron ) {
					$this->isCron = $isCron;
				}

				public function isCron() :bool {
					return $this->isCron;
				}
			}
		] );
	}

	private function stubImportResponse( array $options ) :void {
		if ( \is_callable( $this->httpStub ) ) {
			remove_filter( 'pre_http_request', $this->httpStub, 10 );
		}

		$this->httpStub = static function ( $pre, $args, $url ) use ( $options ) {
			if ( !\is_string( $url ) ) {
				return $pre;
			}
			$query = [];
			\parse_str( (string)( \wp_parse_url( $url, \PHP_URL_QUERY ) ?? '' ), $query );
			if ( (string)( $query[ 'ex' ] ?? '' ) !== PluginImportExport_Export::SLUG ) {
				return $pre;
			}

			return [
				'headers'  => [],
				'body'     => \wp_json_encode( [
					'success' => true,
					'data'    => [
						'options' => $options,
						'ip_rules' => [],
					],
				] ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};

		add_filter( 'pre_http_request', $this->httpStub, 10, 3 );
	}
}
