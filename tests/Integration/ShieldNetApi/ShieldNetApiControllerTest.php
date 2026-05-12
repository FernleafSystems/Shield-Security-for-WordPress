<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldNetApi;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\IpTelemetryMode;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ShieldNetApiControllerTest extends ShieldIntegrationTestCase {

	public function testTelemetryModeDefaultsToCanonicalEvidence() :void {
		$controller = $this->requireController()->comps->shieldnet;
		$method = new \ReflectionMethod( $controller, 'getIpTelemetryMode' );
		$method->setAccessible( true );

		$this->assertSame( IpTelemetryMode::CANONICAL_EVIDENCE, $method->invoke( $controller ) );
	}

	public function testTelemetryModeCanBeOverriddenBackToLegacySignals() :void {
		$controller = $this->requireController()->comps->shieldnet;
		$method = new \ReflectionMethod( $controller, 'getIpTelemetryMode' );
		$method->setAccessible( true );

		$filter = static function () :string {
			return IpTelemetryMode::LEGACY_SIGNALS;
		};
		\add_filter( 'shield/ip_reputation_telemetry_mode', $filter );

		try {
			$this->assertSame( IpTelemetryMode::LEGACY_SIGNALS, $method->invoke( $controller ) );
		}
		finally {
			\remove_filter( 'shield/ip_reputation_telemetry_mode', $filter );
		}
	}

	public function testTelemetrySendGateIsNoLongerTiedToCrowdSecAutoblock() :void {
		$this->enablePremiumCapabilities( [] );
		RuntimeTestState::primeShieldNetHandshake();

		$controller = $this->requireController();
		$controller->opts->optSet( 'cs_block', 'disabled' )->store();

		$shieldNet = $controller->comps->shieldnet;
		$method = new \ReflectionMethod( $shieldNet, 'canSendShieldNetIpTelemetry' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( $shieldNet ) );
	}
}
