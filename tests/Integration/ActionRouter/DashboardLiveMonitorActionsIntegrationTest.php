<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\DashboardLiveMonitorSetState,
	Actions\Render\Components\Traffic\TrafficLiveLogs,
	Actions\Render\Components\Widgets\DashboardLiveMonitorTicker,
	Exceptions\InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\HighValueEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ActionRequestNonceFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class DashboardLiveMonitorActionsIntegrationTest extends ShieldIntegrationTestCase {

	use ActionRequestNonceFixture;

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
		TestDataFactory::insertRequestLog( '203.0.113.61', [
			'verb'    => 'POST',
			'path'    => '/wp-login.php',
			'code'    => 403,
			'uid'     => get_current_user_id(),
			'offense' => true,
			'meta'    => [
				'query' => 'reauth=1',
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
		$currentUser = wp_get_current_user();

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertCount( 1, $rows );
		$this->assertSame(
			[ 'timestamp', 'ip', 'ip_href', 'title', 'description', 'badges' ],
			\array_keys( $firstRow )
		);
		$this->assertSame( '203.0.113.61', $firstRow[ 'ip' ] );
		$this->assertNotSame( '', $firstRow[ 'ip_href' ] );
		$this->assertStringContainsString( '203.0.113.61', $firstRow[ 'ip_href' ] );
		$this->assertContains( $currentUser->user_login, $badgeLabels );
		$this->assertContains( '403', $badgeLabels );
		$this->assertNotSame( '', $firstRow[ 'timestamp' ] );
		$this->assertNotSame( '', \trim( $firstRow[ 'title' ] ) );
		$this->assertNotSame( '', \trim( $firstRow[ 'description' ] ) );
	}

	public function test_live_traffic_render_clamps_excessive_limit() :void {
		$this->seedRequestLogs( 201 );

		$payload = $this->processor()->processAction( TrafficLiveLogs::SLUG, [
			'limit' => 100000,
		] )->payload();
		$vars = $this->requireRenderVars( $payload );

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertCount( 200, $vars[ 'rows' ] );
	}

	public function test_live_traffic_render_clamps_zero_limit_to_minimum() :void {
		$this->seedRequestLogs( 3 );

		$payload = $this->processor()->processAction( TrafficLiveLogs::SLUG, [
			'limit' => 0,
		] )->payload();
		$vars = $this->requireRenderVars( $payload );

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertCount( 1, $vars[ 'rows' ] );
	}

	public function test_set_state_non_ajax_action_requires_valid_nonce_before_mutation() :void {
		$pref = new DashboardLiveMonitorPreference();
		$pref->setCollapsed( false );
		$snapshot = $this->seedActionNonceContext( DashboardLiveMonitorSetState::class );
		$this->mergeCurrentRequestTransport( [
			ActionData::FIELD_NONCE => '',
		] );

		try {
			$this->expectException( InvalidActionNonceException::class );
			$this->processor()->processAction( DashboardLiveMonitorSetState::SLUG, [
				'is_collapsed' => 1,
			] );
		}
		finally {
			$this->assertFalse( $pref->isCollapsed() );
			$this->restoreActionNonceContext( $snapshot );
		}
	}

	public function test_set_state_action_persists_collapsed_preference() :void {
		$pref = new DashboardLiveMonitorPreference();
		$pref->setCollapsed( false );
		$this->assertFalse( $pref->isCollapsed() );
		$this->requireController()->this_req->wp_is_ajax = true;

		$collapsePayload = $this->processor()->processAction(
			DashboardLiveMonitorSetState::SLUG,
			ActionData::Build( DashboardLiveMonitorSetState::class, true, [
				'is_collapsed' => 1,
			] )
		)->payload();
		$this->assertTrue( $collapsePayload[ 'success' ] );
		$this->assertTrue( $collapsePayload[ 'is_collapsed' ] );
		$this->assertTrue( $pref->isCollapsed() );

		$expandPayload = $this->processor()->processAction(
			DashboardLiveMonitorSetState::SLUG,
			ActionData::Build( DashboardLiveMonitorSetState::class, true, [
				'is_collapsed' => 0,
			] )
		)->payload();
		$this->assertTrue( $expandPayload[ 'success' ] );
		$this->assertFalse( $expandPayload[ 'is_collapsed' ] );
		$this->assertFalse( $pref->isCollapsed() );
	}

	private function seedRequestLogs( int $count ) :void {
		for ( $i = 0; $i < $count; $i++ ) {
			TestDataFactory::insertRequestLog( '203.0.113.72', [
				'path'       => '/live-log-'.$i,
				'created_at' => Services::Request()->ts() + $i,
			] );
		}
	}
}
