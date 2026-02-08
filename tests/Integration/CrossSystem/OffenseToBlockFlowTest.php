<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\CrossSystem;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\OffenseTracker;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Cross-system integration tests: verifies that offense events propagate
 * through the tracker, and that bypass rules are respected throughout the chain.
 */
class OffenseToBlockFlowTest extends ShieldIntegrationTestCase {

	public function test_offense_event_increments_tracker_via_event_system() {
		$con = $this->requireController();
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		// Find an event that definitely has offense=true in its definition
		$events = $con->comps->events->getEvents();
		$offenseEvent = null;
		foreach ( $events as $key => $def ) {
			if ( !empty( $def[ 'offense' ] ) ) {
				$offenseEvent = $key;
				break;
			}
		}

		if ( $offenseEvent === null ) {
			$this->markTestSkipped( 'No event with offense=true found in event definitions' );
		}

		$tracker = $con->comps->offense_tracker;
		$this->assertInstanceOf( OffenseTracker::class, $tracker );

		$initialCount = $tracker->getOffenseCount();

		$con->comps->events->fireEvent( $offenseEvent );

		$this->assertGreaterThan( $initialCount, $tracker->getOffenseCount(),
			"Firing offense event '{$offenseEvent}' should increment the OffenseTracker" );
	}

	public function test_non_offense_event_does_not_increment_tracker() {
		$con = $this->requireController();

		$tracker = $con->comps->offense_tracker;
		$initialCount = $tracker->getOffenseCount();

		// Find a non-offense event
		$events = $con->comps->events->getEvents();
		$nonOffenseEvent = null;
		foreach ( $events as $key => $def ) {
			if ( empty( $def[ 'offense' ] ) && empty( $def[ 'audit_params' ] ) ) {
				$nonOffenseEvent = $key;
				break;
			}
		}

		if ( $nonOffenseEvent === null ) {
			$this->markTestSkipped( 'No non-offense event without audit_params found' );
		}

		$con->comps->events->fireEvent( $nonOffenseEvent );

		$this->assertSame( $initialCount, $tracker->getOffenseCount(),
			'Non-offense event should not increment the tracker' );
	}

	public function test_bypassed_ip_status_unaffected_by_block_rules() {
		$this->requireDb( 'ip_rules' );
		$this->requireDb( 'ips' );

		$ip = '192.0.2.251';

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
}
