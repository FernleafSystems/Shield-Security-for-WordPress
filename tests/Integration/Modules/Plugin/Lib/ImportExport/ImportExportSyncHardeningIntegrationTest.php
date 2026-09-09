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
			'importexport_secretkey',
			'importexport_secretkey_expires_at',
		] );
		$this->notifyCronHook = $this->requireController()->prefix( PluginImportExport_UpdateNotified::SLUG );
		\wp_clear_scheduled_hook( $this->notifyCronHook );
		$this->deleteNotifyCooldown( self::CONFIGURED_MASTER_URL );
	}

	public function tear_down() {
		if ( \is_callable( $this->httpStub ) ) {
			remove_filter( 'pre_http_request', $this->httpStub, 10 );
			$this->httpStub = null;
		}
		\wp_clear_scheduled_hook( $this->notifyCronHook );
		$this->deleteNotifyCooldown( self::CONFIGURED_MASTER_URL );
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

	public function test_legacy_import_from_site_still_allows_private_master_url() :void {
		$con = $this->requireController();
		$privateMaster = 'http://10.0.0.25/private-master';
		$con->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_masterurl', '' )
			->store();

		$this->stubImportResponse( [
			'importexport_enable'    => 'N',
			'importexport_masterurl' => '',
		] );

		( new Import() )->fromSite( $privateMaster, '', true );

		$this->assertSame( 'Y', (string)$con->opts->optGet( 'importexport_enable' ) );
		$this->assertSame( $privateMaster, (string)$con->opts->optGet( 'importexport_masterurl' ) );
	}

	public function test_notify_noops_when_local_sync_is_disabled() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_masterurl', self::CONFIGURED_MASTER_URL )
			->store();
		$this->captureShieldEvents();

		$accepted = $con->comps->import_export->runOptionsUpdateNotified( self::CONFIGURED_MASTER_URL );

		$this->assertFalse( $accepted );
		$this->assertFalse( \wp_next_scheduled( $this->notifyCronHook ) );
		$this->assertCount( 0, $this->getCapturedEventsByKey( 'import_notify_received' ) );
	}

	public function test_notify_schedules_due_now_when_local_sync_is_enabled_and_master_is_configured() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', self::CONFIGURED_MASTER_URL )
			->store();
		$this->captureShieldEvents();
		$before = \time();

		$accepted = $con->comps->import_export->runOptionsUpdateNotified( self::CONFIGURED_MASTER_URL );

		$scheduled = \wp_next_scheduled( $this->notifyCronHook );
		$this->assertTrue( $accepted );
		$this->assertGreaterThanOrEqual( $before, $scheduled );
		$this->assertLessThanOrEqual( \time() + 1, $scheduled );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'import_notify_received' ) );
	}

	public function test_notify_replaces_future_import_event_with_due_now_event() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', self::CONFIGURED_MASTER_URL )
			->store();
		$future = \time() + 300;
		\wp_schedule_single_event( $future, $this->notifyCronHook );
		$this->assertSame( $future, \wp_next_scheduled( $this->notifyCronHook ) );

		$accepted = $con->comps->import_export->runOptionsUpdateNotified( self::CONFIGURED_MASTER_URL );

		$this->assertTrue( $accepted );
		$this->assertLessThan( $future, \wp_next_scheduled( $this->notifyCronHook ) );
	}

	public function test_notify_rejects_mismatched_master_url() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', self::CONFIGURED_MASTER_URL )
			->store();

		$accepted = $con->comps->import_export->runOptionsUpdateNotified( 'https://example.com/other-master' );

		$this->assertFalse( $accepted );
		$this->assertFalse( \wp_next_scheduled( $this->notifyCronHook ) );
	}

	public function test_update_notified_action_schedules_without_output_or_die() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', self::CONFIGURED_MASTER_URL )
			->store();

		$action = new PluginImportExport_UpdateNotified( [
			'master_url' => self::CONFIGURED_MASTER_URL,
		] );
		$method = new \ReflectionMethod( $action, 'exec' );
		$method->setAccessible( true );
		\ob_start();
		try {
			$method->invoke( $action );
		}
		finally {
			$output = \ob_get_clean();
		}

		$this->assertSame( '', \trim( (string)$output ) );
		$this->assertNotFalse( \wp_next_scheduled( $this->notifyCronHook ) );
	}

	public function test_secret_key_verification_accepts_exact_match() :void {
		$this->seedSecretKey( 'fixture-import-export-secret' );

		$this->assertTrue(
			$this->requireController()->comps->import_export->verifySecretKey( 'fixture-import-export-secret' )
		);
	}

	/**
	 * @dataProvider invalidSecretProvider
	 */
	public function test_secret_key_verification_rejects_non_exact_values( string $stored, string $provided ) :void {
		$this->seedSecretKey( $stored );

		$this->assertFalse(
			$this->requireController()->comps->import_export->verifySecretKey( $provided )
		);
	}

	public static function invalidSecretProvider() :array {
		return [
			'empty provided secret'   => [ 'fixture-import-export-secret', '' ],
			'mismatched secret'       => [ 'fixture-import-export-secret', 'fixture-import-export-secret-2' ],
			'numeric-looking secret'  => [ '12345', '12346' ],
			'zero exponent loose hit' => [ '0e123456789', '0' ],
			'zero exponent mismatch'  => [ '0e123456789', '0e987654321' ],
		];
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

	private function seedSecretKey( string $secret ) :void {
		$this->requireController()->opts
			->optSet( 'importexport_secretkey', $secret )
			->optSet( 'importexport_secretkey_expires_at', \time() + \DAY_IN_SECONDS )
			->store();
	}

	private function deleteNotifyCooldown( string $masterUrl ) :void {
		\delete_transient(
			$this->requireController()->prefix( 'importexport_updatenotified_' )
			.\hash( 'sha256', \strtolower( \trim( $masterUrl ) ) )
		);
	}
}
