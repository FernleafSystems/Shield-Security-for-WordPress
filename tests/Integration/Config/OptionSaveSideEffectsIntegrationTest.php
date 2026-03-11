<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Config;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class OptionSaveSideEffectsIntegrationTest extends ShieldIntegrationTestCase {

	private const PREMIUM_CAPABILITIES = [
		'scan_file_locker',
		'scan_frequent',
	];

	private const SNAPSHOT_KEYS = [
		'enable_email_authentication',
		'email_can_send_verified_at',
		'cs_block',
		'transgression_limit',
		'scan_frequency',
		'file_locker',
	];

	private array $originalOptions = [];

	/**
	 * @var array<int,array<string,mixed>>
	 */
	private array $mails = [];

	public function set_up() {
		parent::set_up();
		$this->enablePremiumCapabilities( self::PREMIUM_CAPABILITIES );
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
				$con->opts->optSet( $key, $value );
			}
			if ( $con->opts->hasChanges() ) {
				$con->opts->store();
			}
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

	public function test_email_authentication_toggle_triggers_single_verification_send() :void {
		$con = $this->requireController();
		$this->loginAsSecurityAdmin( [
			'user_login' => 'secadmin',
			'user_email' => 'secadmin@example.com',
		] );

		$con->opts
			->optSet( 'enable_email_authentication', 'N' )
			->optSet( 'email_can_send_verified_at', 0 )
			->store();

		$this->mails = [];
		$con->opts->optSet( 'enable_email_authentication', 'N' )->store();
		$this->assertCount( 0, $this->mails );

		$con->opts->optSet( 'enable_email_authentication', 'Y' )->store();

		$this->assertCount( 1, $this->mails );
		$this->assertContains( Services::WpGeneral()->getSiteAdminEmail(), $this->mailRecipients( $this->mails[ 0 ] ) );
		$this->assertSame( 0, $con->opts->optGet( 'email_can_send_verified_at' ) );
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
