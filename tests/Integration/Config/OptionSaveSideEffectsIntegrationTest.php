<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Config;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\HandleOptionsSaveRequest;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\EmailDeliveryVerification;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class OptionSaveSideEffectsIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private const PREMIUM_CAPABILITIES = [
		'scan_file_locker',
		'scan_frequent',
	];

	private const SNAPSHOT_KEYS = [
		'enable_email_authentication',
		'email_can_send_verified_at',
		'email_can_send_verification_sent_at',
		'block_send_email_address',
		'enable_auto_integrations',
		'auto_integrations_track',
		'cs_block',
		'importexport_enable',
		'importexport_masterurl',
		'importexport_pending_network_invites',
		'importexport_network_invite_block_until',
		'transgression_limit',
		'scan_frequency',
		'file_locker',
		'snapi_data',
	];

	private array $originalOptions = [];

	private array $requestSnapshot = [];

	/**
	 * @var array<int,array<string,mixed>>
	 */
	private array $mails = [];

	public function set_up() {
		parent::set_up();
		$this->enablePremiumCapabilities( self::PREMIUM_CAPABILITIES );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$con = $this->requireController();
		foreach ( self::SNAPSHOT_KEYS as $key ) {
			$this->originalOptions[ $key ] = $con->opts->optGet( $key );
		}
		$this->mails = [];
		add_filter( 'pre_wp_mail', [ $this, 'captureWpMail' ], 10, 2 );
	}

	public function tear_down() {
		remove_filter( 'pre_wp_mail', [ $this, 'captureWpMail' ], 10 );
		$con = static::con();
		if ( $con !== null ) {
			foreach ( $this->originalOptions as $key => $value ) {
				if ( \in_array( $key, [ 'auto_integrations_track', NetworkInviteRepository::OPTION_KEY ], true ) ) {
					continue;
				}
				$con->opts->optSet( $key, $value );
			}
			if ( $con->opts->hasChanges() ) {
				$con->opts->store();
			}
			$con->opts
				->optSet( 'auto_integrations_track', $this->originalOptions[ 'auto_integrations_track' ] )
				->optSet( NetworkInviteRepository::OPTION_KEY, $this->originalOptions[ NetworkInviteRepository::OPTION_KEY ] );
			if ( $con->opts->hasChanges() ) {
				$con->opts->store();
			}
		}
		if ( !empty( $this->requestSnapshot ) ) {
			$this->restoreCurrentRequestState( $this->requestSnapshot );
		}
		$this->mails = [];
		parent::tear_down();
	}

	/**
	 * @param mixed $pre
	 */
	public function captureWpMail( $pre, array $atts ) :bool {
		$this->mails[] = $atts;
		return true;
	}

	public function test_direct_email_authentication_toggle_does_not_send_verification_mail() :void {
		$con = $this->requireController();
		$this->loginAsSecurityAdmin();

		$con->opts
			->optSet( 'enable_email_authentication', 'N' )
			->optSet( 'email_can_send_verified_at', 0 )
			->optSet( 'email_can_send_verification_sent_at', 0 )
			->store();

		$this->mails = [];
		$con->opts->optSet( 'enable_email_authentication', 'Y' )->store();

		$this->assertCount( 0, $this->mails );
		$this->assertSame( 0, $con->opts->optGet( 'email_can_send_verified_at' ) );
		$this->assertSame( 0, $con->opts->optGet( 'email_can_send_verification_sent_at' ) );
		$this->assertSame( EmailDeliveryVerification::STATUS_UNSENT, ( new EmailDeliveryVerification() )->status() );
	}

	public function test_direct_email_authentication_disable_clears_pending_sent_timestamp() :void {
		$con = $this->requireController();
		$this->loginAsSecurityAdmin();
		$con->opts
			->optSet( 'enable_email_authentication', 'Y' )
			->optSet( 'email_can_send_verified_at', 0 )
			->optSet( 'email_can_send_verification_sent_at', \time() - 60 )
			->store();

		$con->opts->optSet( 'enable_email_authentication', 'N' )->store();

		$this->assertSame( 'N', $con->opts->optGet( 'enable_email_authentication' ) );
		$this->assertSame( 0, $con->opts->optGet( 'email_can_send_verification_sent_at' ) );
		$this->assertSame( EmailDeliveryVerification::STATUS_DISABLED, ( new EmailDeliveryVerification() )->status() );
	}

	public function test_manual_email_authentication_save_sends_verification_to_report_email() :void {
		$con = $this->requireController();
		$this->prepareEmailVerificationOptions( 'N', 0, 0 );

		$this->mails = [];
		$this->assertTrue( $this->manualEmailAuthSave( 'Y' ) );

		$this->assertCount( 1, $this->mails );
		$this->assertContains( 'mfa-report@example.com', $this->mailRecipients( $this->mails[ 0 ] ) );
		$this->assertSame( 0, $con->opts->optGet( 'email_can_send_verified_at' ) );
		$this->assertGreaterThan( 0, $con->opts->optGet( 'email_can_send_verification_sent_at' ) );
		$this->assertSame( EmailDeliveryVerification::STATUS_PENDING, ( new EmailDeliveryVerification() )->status() );
	}

	public function test_manual_email_authentication_save_does_not_duplicate_pending_verification() :void {
		$sentAt = \time() - 60;
		$con = $this->requireController();
		$this->prepareEmailVerificationOptions( 'Y', 0, $sentAt );

		$this->mails = [];
		$this->assertTrue( $this->manualEmailAuthSave( 'Y' ) );

		$this->assertCount( 0, $this->mails );
		$this->assertSame( $sentAt, $con->opts->optGet( 'email_can_send_verification_sent_at' ) );
		$this->assertSame( EmailDeliveryVerification::STATUS_PENDING, ( new EmailDeliveryVerification() )->status() );
	}

	public function test_manual_email_authentication_save_resends_stale_verification() :void {
		$sentAt = \time() - \DAY_IN_SECONDS - 60;
		$con = $this->requireController();
		$this->prepareEmailVerificationOptions( 'Y', 0, $sentAt );

		$this->mails = [];
		$this->assertTrue( $this->manualEmailAuthSave( 'Y' ) );

		$this->assertCount( 1, $this->mails );
		$this->assertGreaterThan( $sentAt, $con->opts->optGet( 'email_can_send_verification_sent_at' ) );
		$this->assertSame( EmailDeliveryVerification::STATUS_PENDING, ( new EmailDeliveryVerification() )->status() );
	}

	public function test_auto_integrations_enabling_clears_detection_track() :void {
		$con = $this->requireController();
		$this->storeAutoIntegrationsBaseline( 'N', $this->autoIntegrationsTrackFixture() );

		$con->opts->optSet( 'enable_auto_integrations', 'Y' )->store();

		$this->assertSame( [], $con->opts->optGet( 'auto_integrations_track' ) );
	}

	public function test_auto_integrations_disabling_clears_detection_track() :void {
		$con = $this->requireController();
		$this->storeAutoIntegrationsBaseline( 'Y', $this->autoIntegrationsTrackFixture() );

		$con->opts->optSet( 'enable_auto_integrations', 'N' )->store();

		$this->assertSame( [], $con->opts->optGet( 'auto_integrations_track' ) );
	}

	public function test_auto_integrations_track_is_preserved_when_enabled_toggle_unchanged() :void {
		$con = $this->requireController();
		$track = $this->autoIntegrationsTrackFixture();
		$this->storeAutoIntegrationsBaseline( 'Y', $track );

		$con->opts->optSet( 'enable_auto_integrations', 'Y' )->store();

		$this->assertSame( $track, $con->opts->optGet( 'auto_integrations_track' ) );
	}

	public function test_auto_integrations_track_is_preserved_when_disabled_toggle_unchanged() :void {
		$con = $this->requireController();
		$track = $this->autoIntegrationsTrackFixture();
		$this->storeAutoIntegrationsBaseline( 'N', $track );

		$con->opts->optSet( 'enable_auto_integrations', 'N' )->store();

		$this->assertSame( $track, $con->opts->optGet( 'auto_integrations_track' ) );
	}

	public function test_disabling_import_export_clears_pending_network_invites() :void {
		$this->enableImportExportSyncCapability();
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( NetworkInviteRepository::OPTION_KEY, $this->pendingNetworkInviteFixture() )
			->optSet( NetworkInviteRepository::INVITE_BLOCK_UNTIL_OPTION_KEY, 1713225600 )
			->store();

		$con->opts->optSet( 'importexport_enable', 'N' )->store();

		$this->assertSame( [], $con->opts->optGet( NetworkInviteRepository::OPTION_KEY ) );
		$this->assertSame( 1713225600, (int)$con->opts->optGet( NetworkInviteRepository::INVITE_BLOCK_UNTIL_OPTION_KEY ) );
	}

	public function test_import_export_noop_enabled_save_preserves_pending_network_invites() :void {
		$this->enableImportExportSyncCapability();
		$con = $this->requireController();
		$invites = $this->pendingNetworkInviteFixture();
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( NetworkInviteRepository::OPTION_KEY, $invites )
			->store();

		$con->opts->optSet( 'importexport_enable', 'Y' )->store();

		$this->assertSame( $invites, $con->opts->optGet( NetworkInviteRepository::OPTION_KEY ) );
	}

	public function test_master_url_save_clears_pending_network_invites_without_cooldown_change() :void {
		$this->enableImportExportSyncCapability();
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_masterurl', '' )
			->optSet( NetworkInviteRepository::OPTION_KEY, $this->pendingNetworkInviteFixture() )
			->optSet( NetworkInviteRepository::INVITE_BLOCK_UNTIL_OPTION_KEY, 1713225600 )
			->store();

		$con->opts->optSet( 'importexport_masterurl', 'https://master.example.com' )->store();

		$this->assertSame( [], $con->opts->optGet( NetworkInviteRepository::OPTION_KEY ) );
		$this->assertSame( 1713225600, (int)$con->opts->optGet( NetworkInviteRepository::INVITE_BLOCK_UNTIL_OPTION_KEY ) );
	}

	public function test_disabling_crowdsec_block_deletes_crowdsec_rows() :void {
		$con = $this->requireController();
		$dbh = $this->requireDb( 'ip_rules' );
		$enabledValue = $this->alternateSelectValue( 'cs_block', 'disabled' );

		$con->opts->optSet( 'cs_block', $enabledValue )->store();
		TestDataFactory::insertCrowdsecBlock( '198.51.100.71' );
		TestDataFactory::insertManualBlock( '198.51.100.72' );

		$con->opts->optSet( 'cs_block', 'disabled' )->store();

		$records = $this->loadIpRulesByType();
		$this->assertCount( 1, $records );
		$this->assertSame( $dbh::T_MANUAL_BLOCK, $records[ 0 ]->type );
	}

	public function test_zero_transgression_limit_deletes_auto_block_rows() :void {
		$con = $this->requireController();
		$dbh = $this->requireDb( 'ip_rules' );

		$con->opts->optSet( 'transgression_limit', 5 )->store();
		TestDataFactory::insertAutoBlock( '198.51.100.81' );
		TestDataFactory::insertManualBlock( '198.51.100.82' );

		$con->opts->optSet( 'transgression_limit', 0 )->store();

		$records = $this->loadIpRulesByType();
		$this->assertCount( 1, $records );
		$this->assertSame( $dbh::T_MANUAL_BLOCK, $records[ 0 ]->type );
	}

	public function test_scan_frequency_change_deletes_scan_cron() :void {
		$con = $this->requireController();
		$hook = $con->prefix( 'all-scans' );
		$nextRun = time() + 600;
		$initial = (string)$con->opts->optGet( 'scan_frequency' );
		$newFrequency = $this->alternateSelectValue( 'scan_frequency', $initial );

		wp_schedule_single_event( $nextRun, $hook );
		$this->assertNotFalse( wp_next_scheduled( $hook ) );

		$con->opts->optSet( 'scan_frequency', $newFrequency )->store();

		$this->assertFalse( wp_next_scheduled( $hook ) );
	}

	/** @group database-transaction-exception */
	public function test_file_locker_option_change_recreates_storage_when_table_caches_are_stale() :void {
		global $wpdb;

		$con = $this->requireController();
		$handler = $con->db_con->file_locker;
		$schema = $handler->getTableSchema();
		$table = $handler->getTable();
		$this->runWithPersistentDatabaseMutation(
			function () use ( $con, $wpdb, $schema, $table ) :void {
				RuntimeTestState::primeShieldNetHandshake();
				$con->opts->optSet( 'file_locker', [] )->store();
				$con->opts->optSet( 'file_locker', [ 'wpconfig' ] )->store();

				$handler = $con->db_con->file_locker;
				$handler::GetTableReadyCache()->setReady( $schema );
				\FernleafSystems\Wordpress\Services\Services::WpDb()->clearResultShowTables();
				$this->assertTrue( \FernleafSystems\Wordpress\Services\Services::WpDb()->tableExists( $table ) );

				$this->assertNotFalse( $wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' ) );
				$this->assertNotFalse( $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ) );
				$this->assertNotFalse( $wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' ) );

				$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
				$this->assertTrue( $handler::GetTableReadyCache()->isReady( $schema ) );
				$this->assertTrue( \FernleafSystems\Wordpress\Services\Services::WpDb()->tableExists( $table ) );

				$con->opts->optSet( 'file_locker', [ 'wpconfig', 'root_index' ] )->store();
				$this->assertNotNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
				$reloadedHandler = $con->db_con->file_locker;
				$this->assertTrue( $reloadedHandler->isReady() );
				$this->assertTrue( $reloadedHandler->tableExists() );
			},
			function () use ( $con, $wpdb, $schema, $table ) :void {
				$this->assertNotFalse( $wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' ) );
				$this->assertNotFalse( $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ) );
				$this->assertNotFalse( $wpdb->query( $schema->buildCreate() ) );
				$this->assertNotFalse( $wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' ) );
				\FernleafSystems\Wordpress\Services\Services::WpDb()->clearResultShowTables();
				$con->db_con->reset();
				$this->restoreSelectedOptions( $this->originalOptions );
			}
		);
	}

	public function test_file_locker_option_change_reconciles_against_fresh_lock_records() :void {
		global $wpdb;

		$con = $this->requireController();
		RuntimeTestState::primeShieldNetHandshake();
		$con->opts->optSet( 'file_locker', [ 'wpconfig', 'root_index' ] )->store();

		$handler = $this->requireTransactionScopedDb( 'file_locker' );
		$con->comps->file_locker->clearLocks();

		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php' );
		$memoizedLocks = \array_values( $con->comps->file_locker->getLocks() );
		$this->assertCount( 1, $memoizedLocks );
		$this->assertSame( 'wpconfig', $memoizedLocks[ 0 ]->type );

		TestDataFactory::insertFileLockRecord( 'root_index', ABSPATH.'index.php' );
		$this->assertSame( 2, (int)$wpdb->get_var( "SELECT COUNT(*) FROM {$handler->getTable()}" ) );
		$this->assertCount( 1, $con->comps->file_locker->getLocks() );

		$con->opts->optSet( 'file_locker', [ 'wpconfig' ] )->store();

		$reloadedHandler = $this->requireTransactionScopedDb( 'file_locker' );
		$this->assertSame(
			[ 'wpconfig' ],
			$wpdb->get_col( "SELECT type FROM {$reloadedHandler->getTable()} ORDER BY id ASC" )
		);
	}

	private function alternateSelectValue( string $key, string $avoid ) :string {
		$values = \array_map(
			fn( array $valueOpt ) :string => (string)$valueOpt[ 'value_key' ],
			$this->requireController()->opts->optDef( $key )[ 'value_options' ] ?? []
		);
		foreach ( $values as $value ) {
			if ( $value !== $avoid ) {
				return $value;
			}
		}
		$this->fail( sprintf( 'No alternative value found for option %s.', $key ) );
	}

	private function autoIntegrationsTrackFixture() :array {
		return [
			'last_check_at' => 1234567890,
			'profile_hash'  => 'existing-profile',
		];
	}

	private function pendingNetworkInviteFixture() :array {
		$url = 'https://93.184.216.88/pending-master';
		$id = \hash( 'sha256', $url );
		return [
			$id => [
				'id'         => $id,
				'master_url' => $url,
				'created_at' => 1712620800,
				'updated_at' => 1712620800,
			],
		];
	}

	private function storeAutoIntegrationsBaseline( string $enabled, array $track ) :void {
		$con = $this->requireController();
		$con->opts->optSet( 'enable_auto_integrations', $enabled )->store();
		$con->opts->optSet( 'auto_integrations_track', $track )->store();
	}

	private function enableImportExportSyncCapability() :void {
		$this->enablePremiumCapabilities( \array_merge( self::PREMIUM_CAPABILITIES, [ 'import_export_level_2' ] ) );
	}

	private function prepareEmailVerificationOptions( string $enabled, int $verifiedAt, int $sentAt ) :void {
		$this->loginAsSecurityAdmin();
		$this->requireController()->opts
			->optSet( 'block_send_email_address', 'mfa-report@example.com' )
			->optSet( 'enable_email_authentication', $enabled )
			->optSet( 'email_can_send_verified_at', $verifiedAt )
			->optSet( 'email_can_send_verification_sent_at', $sentAt )
			->store();
	}

	private function manualEmailAuthSave( string $enabled ) :bool {
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-admin/admin-ajax.php',
			],
			[],
			[
				'all_opts_keys'               => 'enable_email_authentication',
				'enable_email_authentication' => $enabled,
			],
			[
				'path'              => '/wp-admin/admin-ajax.php',
				'is_security_admin' => true,
			]
		);

		$bypass = static fn() :bool => true;
		\add_filter( $this->requireController()->prefix( 'bypass_is_plugin_admin' ), $bypass, 1000 );
		try {
			return ( new HandleOptionsSaveRequest() )->handleSave();
		}
		finally {
			\remove_filter( $this->requireController()->prefix( 'bypass_is_plugin_admin' ), $bypass, 1000 );
		}
	}

	/**
	 * @param array<string,mixed> $mail
	 * @return string[]
	 */
	private function mailRecipients( array $mail ) :array {
		$to = $mail[ 'to' ] ?? [];
		if ( \is_string( $to ) ) {
			$to = [ $to ];
		}
		return \array_values( \array_filter( \array_map( 'strval', \is_array( $to ) ? $to : [] ) ) );
	}

	private function loadIpRulesByType( string $type = '' ) :array {
		$records = \array_values( ( new LoadIpRules() )->select() );
		if ( $type === '' ) {
			return $records;
		}
		return \array_values( \array_filter(
			$records,
			static fn( $record ) :bool => (string)( $record->type ?? '' ) === $type
		) );
	}

}
