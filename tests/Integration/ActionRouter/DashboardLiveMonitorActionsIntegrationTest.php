<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\DashboardLiveMonitorSetState,
	Actions\Render\Components\Traffic\TrafficLiveLogs,
	Actions\Render\Components\Widgets\DashboardLiveMonitorTicker
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\HighValueEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class DashboardLiveMonitorActionsIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'activity_logs' );
		$this->requireDb( 'activity_logs_meta' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function requireRenderVars( array $payload ) :array {
		$this->assertIsArray( $payload[ 'render_data' ] );
		$this->assertIsArray( $payload[ 'render_data' ][ 'vars' ] );

		return $payload[ 'render_data' ][ 'vars' ];
	}

	public function test_high_value_events_allowlist_contains_expected_slugs() :void {
		$events = ( new HighValueEvents() )->forDashboardTicker();

		$this->assertContains( 'ip_blocked', $events );
		$this->assertNotContains( 'user_login', $events );
	}

	public function test_ticker_render_uses_high_value_events_only() :void {
		$highId = TestDataFactory::insertActivityLog( 'ip_blocked', '198.51.100.21' );
		$lowId = TestDataFactory::insertActivityLog( 'user_login', '198.51.100.22' );

		$payload = $this->processor()->processAction( DashboardLiveMonitorTicker::SLUG, [
			'limit' => 12,
		] )->payload();
		$vars = $this->requireRenderVars( $payload );
		$this->assertIsArray( $vars[ 'rows' ] );
		$rows = $vars[ 'rows' ];

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertNotEmpty( $rows );
		$this->assertSame( $highId, $vars[ 'latest_id' ] );
		$this->assertNotSame( $lowId, $vars[ 'latest_id' ] );
		$this->assertSame( '198.51.100.21', $rows[ 0 ][ 'ip' ] );
		$this->assertIsString( $rows[ 0 ][ 'title' ] );
		$this->assertNotSame( '', $rows[ 0 ][ 'title' ] );
		$this->assertArrayHasKey( 'description', $rows[ 0 ] );
	}

	public function test_live_traffic_render_returns_structured_rows() :void {
		$originalProviders = get_transient( 'apto_provider_ips' );
		$this->seedProviderIps( [
			'services' => [
				'icontrolwp' => [
					'name' => 'iControlWP',
					'type' => [ 'wp_site_management' ],
					'ips'  => [
						'4' => [ '203.0.113.0/24' ],
						'6' => [],
					],
				],
			],
			'crawlers' => [
				'google' => [
					'name'         => 'GoogleBot',
					'type'         => [ 'search' ],
					'host_pattern' => '#.+\\.google(bot)?\\.com\\.?$#i',
					'agents'       => [ 'Googlebot' ],
				],
			],
		] );

		try {
			TestDataFactory::insertRequestLog( '203.0.113.61', [
				'verb'    => 'POST',
				'path'    => '/wp-login.php',
				'code'    => 403,
				'uid'     => get_current_user_id(),
				'offense' => true,
				'meta'    => [
					'query' => 'reauth=1',
					'ua'    => 'iControlWPApp/1.0',
				],
			] );

			$payload = $this->processor()->processAction( TrafficLiveLogs::SLUG, [
				'limit' => 5,
			] )->payload();
			$vars = $this->requireRenderVars( $payload );
			$this->assertIsArray( $vars[ 'rows' ] );
			$rows = $vars[ 'rows' ];
			$firstRow = $rows[ 0 ];
			$badgeLabels = \array_column( $firstRow[ 'badges' ], 'label' );
			$badgeClasses = \array_column( $firstRow[ 'badges' ], 'class' );
			$currentUser = wp_get_current_user();

			$this->assertTrue( $payload[ 'success' ] );
			$this->assertCount( 1, $rows );
			$this->assertSame(
				[ 'timestamp', 'ip', 'ip_href', 'title', 'description', 'badges' ],
				\array_keys( $firstRow )
			);
			$this->assertSame( '203.0.113.61', $firstRow[ 'ip' ] );
			$this->assertSame( '/ip/203.0.113.61', $firstRow[ 'ip_href' ] );
			$this->assertContains( 'iControlWP', $badgeLabels );
			$this->assertContains( $currentUser->user_login, $badgeLabels );
			$this->assertContains( '403', $badgeLabels );
			$this->assertContains( 'shield-live-logs__badge--identity', $badgeClasses );
			$this->assertGreaterThanOrEqual( 4, \count( $firstRow[ 'badges' ] ) );
			$this->assertNotSame( '', $firstRow[ 'timestamp' ] );
			$this->assertNotSame( '', \trim( $firstRow[ 'title' ] ) );
			$this->assertNotSame( '', \trim( $firstRow[ 'description' ] ) );
		}
		finally {
			$this->restoreProviderIps( $originalProviders );
		}
	}

	public function test_set_state_action_persists_collapsed_preference() :void {
		$pref = new DashboardLiveMonitorPreference();
		$pref->setCollapsed( false );
		$this->assertFalse( $pref->isCollapsed() );

		$collapsePayload = $this->processor()->processAction( DashboardLiveMonitorSetState::SLUG, [
			'is_collapsed' => 1,
		] )->payload();
		$this->assertTrue( $collapsePayload[ 'success' ] );
		$this->assertTrue( $collapsePayload[ 'is_collapsed' ] );
		$this->assertTrue( $pref->isCollapsed() );

		$expandPayload = $this->processor()->processAction( DashboardLiveMonitorSetState::SLUG, [
			'is_collapsed' => 0,
		] )->payload();
		$this->assertTrue( $expandPayload[ 'success' ] );
		$this->assertFalse( $expandPayload[ 'is_collapsed' ] );
		$this->assertFalse( $pref->isCollapsed() );
	}

	private function seedProviderIps( array $providers ) :void {
		set_transient( 'apto_provider_ips', $providers, DAY_IN_SECONDS );
		$this->resetServiceProviderCache();
	}

	private function restoreProviderIps( $providers ) :void {
		if ( $providers === false ) {
			delete_transient( 'apto_provider_ips' );
		}
		else {
			set_transient( 'apto_provider_ips', $providers, DAY_IN_SECONDS );
		}
		$this->resetServiceProviderCache();
	}

	private function resetServiceProviderCache() :void {
		$serviceProviders = Services::ServiceProviders();
		$reflection = new \ReflectionObject( $serviceProviders );
		$property = $reflection->getProperty( 'providers' );
		$property->setAccessible( true );
		$property->setValue( $serviceProviders, null );
	}
}
