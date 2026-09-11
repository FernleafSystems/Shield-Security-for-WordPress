<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers\AlertHandlerAdmins;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers\AlertHandlerFirewallBlock;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\FirewallBlock;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class FirewallInstantAlertIntegrationTest extends ShieldIntegrationTestCase {

	use LocalEmailCapture;

	private array $optionsSnapshot = [];
	private int $firewallPreBlockCalls = 0;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->enablePremiumCapabilities( [ 'instant_alerts' ] );
		$this->startLocalEmailCapture();
		$this->firewallPreBlockCalls = 0;
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'instant_alert_firewall_block',
			'instant_alert_admins',
			'instant_alerts_data',
			'block_send_email_address',
		] );
		$this->requireController()->comps->asset_coordinator->deleteState();
		\wp_clear_scheduled_hook( $this->requireController()->prefix( 'asset_coordinator' ) );
	}

	public function tear_down() {
		remove_action( 'shield/firewall_pre_block', [ $this, 'captureFirewallPreBlock' ] );
		$this->stopLocalEmailCapture();
		if ( static::con() !== null ) {
			self::con()->comps->asset_coordinator->deleteState();
			\wp_clear_scheduled_hook( self::con()->prefix( 'asset_coordinator' ) );
			$this->restoreSelectedOptions( $this->optionsSnapshot );
		}
		parent::tear_down();
	}

	public function test_firewall_block_instant_alert_sends_immediately_and_preserves_other_queued_alerts() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'instant_alert_firewall_block', 'email' )
			->optSet( 'instant_alert_admins', 'email' )
			->optSet( 'block_send_email_address', 'firewall-alerts@example.com' )
			->optSet( 'instant_alerts_data', [] )
			->store();
		$alertsProperty = new \ReflectionProperty( $con->comps->instant_alerts, 'alerts' );
		$alertsProperty->setAccessible( true );
		$alertsProperty->setValue( $con->comps->instant_alerts, null );

		$con->comps->instant_alerts->updateAlertDataFor( new AlertHandlerAdmins(), [
			'added' => [ 'queued-admin' ],
		] );
		$this->insertActiveScan( 'afs', ScanStatus::RUNNING );
		$this->assertTrue( $con->comps->asset_coordinator->enqueueAsset(
			'plugin',
			$con->base_file,
			60
		) );
		$this->assertFalse( $con->comps->scans->isReadyForScanResultNotifications() );

		$duplicateValue = 'dup-marker-77';
		$con->this_req->ip = '203.0.113.10';
		$con->rules->getConditionMeta()->match_name = 'Firewall Rule';
		$con->rules->getConditionMeta()->match_pattern = $duplicateValue;
		$con->rules->getConditionMeta()->match_request_param = $duplicateValue;
		$con->rules->getConditionMeta()->match_request_value = $duplicateValue;

		$this->captureShieldEvents();
		add_action( 'shield/firewall_pre_block', [ $this, 'captureFirewallPreBlock' ] );

		$reflection = new \ReflectionClass( FirewallBlock::class );
		$method = $reflection->getMethod( 'preBlock' );
		$method->setAccessible( true );
		$payloadMethod = $reflection->getMethod( 'buildAlertPayload' );
		$payloadMethod->setAccessible( true );
		$response = ( new FirewallBlock() )->setThisRequest( $con->this_req );
		$originalRequestUri = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI'] = '/blocked-request';
		try {
			$expectedPayload = $payloadMethod->invoke( $response );
			$method->invoke( $response );
		}
		finally {
			if ( $originalRequestUri === null ) {
				unset( $_SERVER['REQUEST_URI'] );
			}
			else {
				$_SERVER['REQUEST_URI'] = $originalRequestUri;
			}
		}

		$this->assertSame( 1, $this->firewallPreBlockCalls );
		$this->assertCount( 1, $this->capturedMails() );

		$mail = $this->lastCapturedMail();
		$recipients = \is_array( $mail['to'] ?? null ) ? \implode( ',', $mail['to'] ) : (string)( $mail['to'] ?? '' );

		$this->assertStringContainsString( 'firewall-alerts@example.com', $recipients );
		$this->assertHtmlContainsMarker( '203.0.113.10', (string)( $mail['html_body'] ?? '' ), 'Firewall alert HTML body' );
		$this->assertHtmlContainsMarker( (string)( $expectedPayload['request_path'] ?? '' ), (string)( $mail['html_body'] ?? '' ), 'Firewall alert HTML body' );
		$this->assertHtmlContainsMarker( 'Firewall Rule', (string)( $mail['html_body'] ?? '' ), 'Firewall alert HTML body' );
		$this->assertGreaterThanOrEqual(
			3,
			\substr_count( (string)( $mail['html_body'] ?? '' ), $duplicateValue ),
			'Duplicate-valued firewall payload fields should survive associative instant-alert merges.'
		);
		$this->assertStringNotContainsString( 'queued-admin', (string)( $mail['html_body'] ?? '' ) );

		$queuedData = $con->opts->optGet( 'instant_alerts_data' );
		$this->assertArrayHasKey( AlertHandlerAdmins::class, $queuedData );
		$this->assertSame( [ 'queued-admin' ], $queuedData[ AlertHandlerAdmins::class ][ 'added' ] ?? [] );
		$this->assertArrayNotHasKey( AlertHandlerFirewallBlock::class, $queuedData );

		$successEvents = $this->getCapturedEventsByKey( 'fw_email_success' );
		$this->assertCount( 1, $successEvents );
		$this->assertSame( 'firewall-alerts@example.com', $successEvents[ 0 ][ 'meta' ][ 'audit_params' ][ 'to' ] ?? '' );
		$this->assertCount( 0, $this->getCapturedEventsByKey( 'fw_email_fail' ) );
	}

	public function test_scheduled_alert_list_payloads_merge_and_dedupe_on_supported_runtime() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'instant_alert_admins', 'email' )
			->optSet( 'instant_alerts_data', [] )
			->store();
		$alertsProperty = new \ReflectionProperty( $con->comps->instant_alerts, 'alerts' );
		$alertsProperty->setAccessible( true );
		$alertsProperty->setValue( $con->comps->instant_alerts, null );

		$handler = new AlertHandlerAdmins();
		$con->comps->instant_alerts->updateAlertDataFor( $handler, [
			'added' => [ 'queued-admin', 'queued-admin', 'second-admin' ],
		] );
		$con->comps->instant_alerts->updateAlertDataFor( $handler, [
			'added' => [ 'second-admin', 'queued-admin', 'third-admin' ],
		] );

		$queuedData = $con->opts->optGet( 'instant_alerts_data' );
		$this->assertSame(
			[ 'queued-admin', 'second-admin', 'third-admin' ],
			$queuedData[ AlertHandlerAdmins::class ][ 'added' ] ?? []
		);
		$this->assertCount( 0, $this->capturedMails() );
	}

	public function captureFirewallPreBlock() :void {
		$this->firewallPreBlockCalls++;
	}

	private function insertActiveScan( string $scanSlug, string $status ) :int {
		$now = Services::Request()->ts();
		$dbh = self::con()->db_con->scans;
		$record = $dbh->getRecord();
		$record->scan = $scanSlug;
		$record->status = $status;
		$record->scope_type = 'full';
		$record->scope_key = '';
		$record->run_trigger = 'manual';
		$record->started_at = $now;
		$record->last_process_at = $now;
		$record->ready_at = $now;
		$record->finished_at = 0;
		$dbh->getQueryInserter()->insert( $record );
		return (int)Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' );
	}
}
