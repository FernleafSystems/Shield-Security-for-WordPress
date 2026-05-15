<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Import;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class ImportExportContractsIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private const SLAVE_URL = 'https://slave.example.com';
	private const SLAVE_IMPORT_ID = 'shi280-slave-import-id';

	private array $optionsSnapshot = [];
	private array $requestSnapshot = [];
	private array $tempFiles = [];

	public function set_up() {
		parent::set_up();
		$this->enablePremiumCapabilities( [ 'import_export_level_1', 'import_export_level_2' ] );
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'importexport_enable',
			'importexport_masterurl',
			'importexport_whitelist',
			'importexport_secretkey',
			'importexport_secretkey_expires_at',
			'importexport_handshake_expires_at',
			'import_id',
			'import_url_ids',
			'xfer_excluded',
			'display_plugin_badge',
			'visitor_address_source',
			'enable_tracking',
		] );
	}

	public function tear_down() {
		foreach ( $this->tempFiles as $path ) {
			if ( \is_string( $path ) && \file_exists( $path ) ) {
				@\unlink( $path );
			}
		}
		if ( !empty( $this->requestSnapshot ) ) {
			$this->restoreCurrentRequestState( $this->requestSnapshot );
		}
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_import_export_enable_option_gates_controller() :void {
		$con = $this->requireController();
		$probe = new ImportExportControllerContractProbe();

		$con->opts->optSet( 'importexport_enable', 'N' )->store();
		$this->assertFalse( $probe->canRunForTest() );

		$con->opts->optSet( 'importexport_enable', 'Y' )->store();
		$this->assertTrue( $probe->canRunForTest() );
	}

	public function test_export_payload_contains_machine_contract_and_excludes_transfer_opt_outs() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'display_plugin_badge', 'light' )
			->optSet( 'visitor_address_source', 'REMOTE_ADDR' )
			->optSet( 'enable_tracking', 'Y' )
			->optSet( 'xfer_excluded', [ 'enable_tracking' ] )
			->store();

		$export = ( new Export() )->getExportData();

		foreach ( [ 'site_url', 'exported_at', 'exported_date', 'slug', 'version', 'options', 'ip_rules' ] as $key ) {
			$this->assertArrayHasKey( $key, $export );
		}
		$this->assertIsString( $export[ 'site_url' ] );
		$this->assertIsInt( $export[ 'exported_at' ] );
		$this->assertIsString( $export[ 'exported_date' ] );
		$this->assertSame( 'wp-simple-firewall', $export[ 'slug' ] );
		$this->assertIsString( $export[ 'version' ] );
		$this->assertIsArray( $export[ 'options' ] );
		$this->assertIsArray( $export[ 'ip_rules' ] );
		$this->assertSame( 'light', $export[ 'options' ][ 'display_plugin_badge' ] ?? null );
		$this->assertSame( 'REMOTE_ADDR', $export[ 'options' ][ 'visitor_address_source' ] ?? null );
		$this->assertArrayNotHasKey( 'enable_tracking', $export[ 'options' ] );
		$this->assertArrayNotHasKey( 'xfer_excluded', $export[ 'options' ] );
	}

	public function test_standard_file_import_applies_transferable_options_respects_exclusions_and_deletes_file() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'display_plugin_badge', 'light' )
			->optSet( 'visitor_address_source', 'REMOTE_ADDR' )
			->optSet( 'enable_tracking', 'Y' )
			->optSet( 'xfer_excluded', [] )
			->store();

		$file = $this->writeTempFile( \implode( "\n", ( new Export() )->toStandardArray() ) );

		$con->opts
			->optSet( 'display_plugin_badge', 'disabled' )
			->optSet( 'visitor_address_source', 'AUTO_DETECT_IP' )
			->optSet( 'enable_tracking', 'N' )
			->optSet( 'xfer_excluded', [ 'enable_tracking' ] )
			->store();
		$this->captureShieldEvents();

		( new Import() )->fromFile( $file, true );

		$this->assertFileDoesNotExist( $file );
		$this->assertSame( 'light', $con->opts->optGet( 'display_plugin_badge' ) );
		$this->assertSame( 'REMOTE_ADDR', $con->opts->optGet( 'visitor_address_source' ) );
		$this->assertSame( 'N', $con->opts->optGet( 'enable_tracking' ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'options_imported' ) );
	}

	public function test_invalid_file_inputs_fail_without_changing_options() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'display_plugin_badge', 'disabled' )
			->optSet( 'visitor_address_source', 'AUTO_DETECT_IP' )
			->optSet( 'enable_tracking', 'N' )
			->store();

		$missing = $this->writeTempFile( 'temporary' );
		@\unlink( $missing );

		foreach ( [
			$missing,
			$this->writeTempFile( '' ),
			$this->writeTempFile( "# comment only\nnot-json" ),
			$this->writeTempFile( "{not-json" ),
		] as $path ) {
			$this->assertFileImportFailsWithoutOptionChanges( $path );
		}
	}

	public function test_exported_manual_bypass_rules_are_imported_for_exported_ip() :void {
		$con = $this->requireController();
		$ip = '10.22.33.44';
		$sourceRecord = ( new AddRule() )
			->setIP( $ip )
			->toManualWhitelist( 'source bypass' );

		$export = ( new Export() )->getExportData();
		$this->assertNotEmpty( $export[ 'ip_rules' ] );

		$con->db_con->ip_rules->getQueryDeleter()->deleteById( $sourceRecord->id );
		$this->resetIpCaches();
		$this->assertCount( 0, $this->loadManualBypassRulesForIp( $ip ) );

		$file = $this->writeTempFile( \implode( "\n", [
			'# contract fixture',
			\wp_json_encode( $export ),
		] ) );

		( new Import() )->fromFile( $file, true );
		$this->resetIpCaches();

		$rules = $this->loadManualBypassRulesForIp( $ip );
		$this->assertCount( 1, $rules );
		$this->assertSame( $con->db_con->ip_rules::T_MANUAL_BYPASS, $rules[ 0 ]->type );
		$this->assertTrue( $rules[ 0 ]->can_export );
		$this->assertGreaterThan( 0, $rules[ 0 ]->imported_at );
	}

	public function test_export_json_success_path_stores_import_id_and_fires_events() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_whitelist', [] )
			->optSet( 'import_url_ids', [] )
			->store();
		$secret = $con->comps->import_export->getImportExportSecretKey();
		$this->captureShieldEvents();

		$addPayload = $this->captureExportJson( [
			'url'     => self::SLAVE_URL,
			'id'      => self::SLAVE_IMPORT_ID,
			'secret'  => $secret,
			'network' => 'Y',
		] );

		$this->assertExportJsonPayload( $addPayload );
		$urlIDs = $con->opts->optGet( 'import_url_ids' );
		$this->assertSame( self::SLAVE_IMPORT_ID, $urlIDs[ \hash( 'md5', self::SLAVE_URL ) ] ?? '' );
		$this->assertContains( self::SLAVE_URL, $con->comps->import_export->getImportExportWhitelist() );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'options_exported' ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'whitelist_site_added' ) );

		$removePayload = $this->captureExportJson( [
			'url'     => self::SLAVE_URL,
			'id'      => self::SLAVE_IMPORT_ID,
			'secret'  => $secret,
			'network' => 'N',
		] );

		$this->assertExportJsonPayload( $removePayload );
		$this->assertNotContains( self::SLAVE_URL, $con->comps->import_export->getImportExportWhitelist() );
		$this->assertCount( 2, $this->getCapturedEventsByKey( 'options_exported' ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'whitelist_site_removed' ) );
	}

	private function assertFileImportFailsWithoutOptionChanges( string $path ) :void {
		$before = $this->currentOptionValues( [
			'display_plugin_badge',
			'visitor_address_source',
			'enable_tracking',
		] );

		try {
			( new Import() )->fromFile( $path );
			$this->fail( 'Expected import from invalid file to fail.' );
		}
		catch ( \Exception $e ) {
			$this->assertSame( $before, $this->currentOptionValues( \array_keys( $before ) ) );
		}
	}

	private function assertExportJsonPayload( array $payload ) :void {
		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertSame( 0, $payload[ 'code' ] ?? null );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertIsArray( $payload[ 'data' ] ?? null );
		foreach ( [ 'site_url', 'exported_at', 'exported_date', 'slug', 'version', 'options' ] as $key ) {
			$this->assertArrayHasKey( $key, $payload[ 'data' ] );
		}
	}

	private function captureExportJson( array $query ) :array {
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/',
		], $query );

		$caught = false;
		$level = \ob_get_level();
		$ajaxFilter = '__return_true';
		$filter = static fn() => static function () :void {
			throw new ImportExportContractsWpDieException();
		};
		\add_filter( 'wp_doing_ajax', $ajaxFilter );
		\add_filter( 'wp_die_ajax_handler', $filter );
		\ob_start();

		try {
			( new Export() )->toJson();
		}
		catch ( ImportExportContractsWpDieException $e ) {
			$caught = true;
		}
		finally {
			\remove_filter( 'wp_die_ajax_handler', $filter );
			\remove_filter( 'wp_doing_ajax', $ajaxFilter );
			$output = \ob_get_level() > $level ? (string)\ob_get_clean() : '';
		}

		$this->assertTrue( $caught, 'Expected export JSON to terminate through wp_die().' );
		$decoded = \json_decode( \trim( $output ), true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}

	private function writeTempFile( string $content ) :string {
		$path = \tempnam( \sys_get_temp_dir(), 'shi280_' );
		if ( $path === false ) {
			$this->fail( 'Could not create temporary import file.' );
		}
		\file_put_contents( $path, $content );
		$this->tempFiles[] = $path;
		return $path;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function currentOptionValues( array $keys ) :array {
		$con = $this->requireController();
		$values = [];
		foreach ( $keys as $key ) {
			$values[ (string)$key ] = $con->opts->optGet( (string)$key );
		}
		return $values;
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Record[]
	 */
	private function loadManualBypassRulesForIp( string $ip ) :array {
		$loader = ( new LoadIpRules() )->setIP( $ip );
		$loader->wheres = [
			sprintf( "`ir`.`type`='%s'", $this->requireController()->db_con->ip_rules::T_MANUAL_BYPASS ),
		];
		return \array_values( $loader->select() );
	}
}

class ImportExportControllerContractProbe extends ImportExportController {

	public function canRunForTest() :bool {
		return $this->canRun();
	}
}

class ImportExportContractsWpDieException extends \RuntimeException {
}
