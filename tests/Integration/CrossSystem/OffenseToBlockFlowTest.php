<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\CrossSystem;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler as IpRulesHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\ProcessOffense;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\BlacklistHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\OffenseTracker;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Cross-system integration tests: verifies that offense events propagate
 * through the tracker, and that bypass rules are respected throughout the chain.
 */
class OffenseToBlockFlowTest extends ShieldIntegrationTestCase {

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'transgression_limit',
		] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		parent::tear_down();
	}

	public function test_offense_event_increments_tracker_via_event_system() {
		$con = $this->requireController();
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$tracker = new OffenseTracker();
		$initialCount = $tracker->getOffenseCount();

		$con->comps->events->fireEvent( 'bottrack_404', [
			'audit_params'  => [
				'path' => '/missing.php',
			],
			'offense_count' => 2,
		] );

		$this->assertSame( $initialCount + 2, $tracker->getOffenseCount() );
	}

	public function test_non_offense_event_does_not_increment_tracker() {
		$con = $this->requireController();

		$tracker = new OffenseTracker();
		$initialCount = $tracker->getOffenseCount();

		$con->comps->events->fireEvent( 'bottrack_notbot' );

		$this->assertSame( $initialCount, $tracker->getOffenseCount() );
	}

	public function test_offense_limit_six_blocks_only_on_threshold_crossing() :void {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.0.252';
		$this->requireController()->opts->optSet( 'transgression_limit', 6 );
		$this->captureShieldEvents();

		( new ProcessOffense() )
			->setIP( $ip )
			->incrementOffenses( 5 );

		$this->resetIpCaches();
		$status = new IpRuleStatus( $ip );
		$this->assertTrue( $status->isAutoBlacklisted() );
		$this->assertFalse( $status->hasAutoBlock() );
		$this->assertFalse( $status->isBlocked() );
		$this->assertSame( 5, $status->getOffenses() );

		( new ProcessOffense() )
			->setIP( $ip )
			->incrementOffenses( 1 );

		$this->resetIpCaches();
		$status = new IpRuleStatus( $ip );
		$this->assertTrue( $status->hasAutoBlock() );
		$this->assertTrue( $status->isBlockedByShield() );
		$this->assertSame( 6, $status->getOffenses() );

		$ipOffenseEvents = $this->getCapturedEventsByKey( 'ip_offense' );
		$ipBlockedEvents = $this->getCapturedEventsByKey( 'ip_blocked' );

		$this->assertCount( 2, $ipOffenseEvents );
		$this->assertCount( 1, $ipBlockedEvents );
		$this->assertArrayHasKey( 'audit_params', $ipOffenseEvents[ 0 ][ 'meta' ] );
		$this->assertArrayHasKey( 'audit_params', $ipBlockedEvents[ 0 ][ 'meta' ] );
		$this->assertArrayHasKey( 'suppress_audit', $ipOffenseEvents[ 1 ][ 'meta' ] );
		$this->assertArrayHasKey( 'audit_params', $ipOffenseEvents[ 1 ][ 'meta' ] );
		$this->assertSame( [ 'from' => 0, 'to' => 5 ], $ipOffenseEvents[ 0 ][ 'meta' ][ 'audit_params' ] );
		$this->assertSame( [ 'from' => 5, 'to' => 6 ], $ipBlockedEvents[ 0 ][ 'meta' ][ 'audit_params' ] );
		$this->assertTrue( $ipOffenseEvents[ 1 ][ 'meta' ][ 'suppress_audit' ] );
		$this->assertSame( [ 'from' => 5, 'to' => 6 ], $ipOffenseEvents[ 1 ][ 'meta' ][ 'audit_params' ] );
	}

	public function test_zero_offense_limit_disables_blacklist_handler_entrypoint() :void {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$con = $this->requireController();
		$con->opts->optSet( 'transgression_limit', 0 );
		$hook = $con->prefix( 'pre_plugin_shutdown' );
		$before = \has_action( $hook );

		( new BlacklistHandler() )->execute();

		$this->assertFalse( $con->comps->opts_lookup->enabledIpAutoBlock() );
		$this->assertSame( $before, \has_action( $hook ) );
	}

	public function test_bypassed_ip_is_not_auto_blocked_by_offense_processing() :void {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.0.253';
		$this->requireController()->opts->optSet( 'transgression_limit', 6 );
		TestDataFactory::insertBypass( $ip );
		$this->resetIpCaches();
		$this->captureShieldEvents();

		try {
			( new ProcessOffense() )
				->setIP( $ip )
				->incrementOffenses( 6 );
			$this->fail( 'Bypassed IPs must not be auto-blocked by offense processing.' );
		}
		catch ( \Exception $e ) {
			$this->assertSame( [], $this->loadAutoBlockRulesForIp( $ip ) );
		}

		$this->resetIpCaches();
		$status = new IpRuleStatus( $ip );
		$this->assertTrue( $status->isBypass() );
		$this->assertFalse( $status->isBlocked() );
		$this->assertSame( 0, $status->getOffenses() );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'ip_blocked' ) );
	}

	public function test_bypassed_ip_status_unaffected_by_block_rules() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '10.0.0.251';

		// Create bypass first
		TestDataFactory::insertBypass( $ip );
		// Then create a manual block (simulating raw DB insert, bypassing AddRule validation)
		TestDataFactory::insertManualBlock( $ip );

		$this->resetIpCaches();
		$status = new IpRuleStatus( $ip );

		$this->assertTrue( $status->isBypass(), 'IP should have bypass' );
		$this->assertFalse( $status->isBlockedByShield(),
			'Even with a block rule, bypass should prevent isBlockedByShield from being true' );
		$this->assertFalse( $status->isBlocked(),
			'Bypass should override all block types' );
	}

	private function loadAutoBlockRulesForIp( string $ip ) :array {
		$loader = ( new LoadIpRules() )->setIP( $ip );
		$loader->wheres = [
			sprintf( "`ir`.`type`='%s'", IpRulesHandler::T_AUTO_BLOCK ),
		];

		return \array_values( $loader->select() );
	}
}
