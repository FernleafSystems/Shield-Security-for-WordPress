<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\DashboardLiveMonitorSetState,
	Actions\Render\Components\Widgets\DashboardLiveMonitorTicker
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\HighValueEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

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
		$vars = $payload[ 'render_data' ][ 'vars' ] ?? [];
		$logs = \is_array( $vars[ 'logs' ] ?? null ) ? $vars[ 'logs' ] : [];

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertNotEmpty( $logs );
		$this->assertSame( $highId, (int)( $vars[ 'latest_id' ] ?? 0 ) );
		$this->assertNotSame( $lowId, (int)( $vars[ 'latest_id' ] ?? 0 ) );
	}

	public function test_set_state_action_persists_collapsed_preference() :void {
		$pref = new DashboardLiveMonitorPreference();
		$pref->setCollapsed( false );
		$this->assertFalse( $pref->isCollapsed() );

		$collapsePayload = $this->processor()->processAction( DashboardLiveMonitorSetState::SLUG, [
			'is_collapsed' => 1,
		] )->payload();
		$this->assertTrue( (bool)( $collapsePayload[ 'success' ] ?? false ) );
		$this->assertTrue( (bool)( $collapsePayload[ 'is_collapsed' ] ?? false ) );
		$this->assertTrue( $pref->isCollapsed() );

		$expandPayload = $this->processor()->processAction( DashboardLiveMonitorSetState::SLUG, [
			'is_collapsed' => 0,
		] )->payload();
		$this->assertTrue( (bool)( $expandPayload[ 'success' ] ?? false ) );
		$this->assertFalse( (bool)( $expandPayload[ 'is_collapsed' ] ?? true ) );
		$this->assertFalse( $pref->isCollapsed() );
	}
}

