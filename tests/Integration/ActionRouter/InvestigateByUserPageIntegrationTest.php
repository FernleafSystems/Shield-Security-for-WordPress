<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\InvestigateByUserPanelBody,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as ReqLogsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateByUserPageIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'user_meta' );

		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderByUserPage( string $lookup = '' ) :array {
		return $this->renderPluginAdminRoutePayload(
			PluginNavs::NAV_ACTIVITY,
			PluginNavs::SUBNAV_ACTIVITY_BY_USER,
			$lookup !== '' ? [ 'user_lookup' => $lookup ] : []
		);
	}

	private function renderByUserPanelBody( string $lookup = '' ) :array {
		$params = [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_USER,
		];
		if ( $lookup !== '' ) {
			$params[ 'user_lookup' ] = $lookup;
		}
		return $this->processActionPayloadWithAdminBypass( InvestigateByUserPanelBody::SLUG, $params );
	}

	private function seedRequestLogForUser( int $userId, string $ip = '198.51.100.24' ) :void {
		$ipRecord = TestDataFactory::createIpRecord( $ip );

		$record = self::con()->db_con->req_logs->getRecord();
		$record->req_id = \substr( \wp_generate_uuid4(), 0, 10 );
		$record->ip_ref = $ipRecord->id;
		$record->uid = $userId;
		$record->type = ReqLogsHandler::TYPE_HTTP;
		$record->path = '/integration/investigate-by-user';
		$record->verb = 'GET';
		$record->code = 403;
		$record->offense = true;
		$record->meta = [
			'query' => 'from=integration',
		];

		self::con()->db_con->req_logs->getQueryInserter()->insert( $record );
	}

	public function test_valid_lookup_renders_three_investigation_tables_with_expected_types() :void {
		$userId = \get_current_user_id();
		$this->assertGreaterThan( 0, $userId );
		$this->seedRequestLogForUser( $userId );

		$renderData = (array)( $this->renderByUserPanelBody( (string)$userId )[ 'render_data' ] ?? [] );
		$vars = (array)( $renderData[ 'vars' ] ?? [] );
		$tables = (array)( $vars[ 'tables' ] ?? [] );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertSame( 'sessions', (string)( $tables[ 'sessions' ][ 'table_type' ] ?? '' ) );
		$this->assertSame( 'activity', (string)( $tables[ 'activity' ][ 'table_type' ] ?? '' ) );
		$this->assertSame( 'traffic', (string)( $tables[ 'requests' ][ 'table_type' ] ?? '' ) );
		$this->assertSame( 'user', (string)( $tables[ 'sessions' ][ 'subject_type' ] ?? '' ) );
		$this->assertSame( $userId, (int)( $tables[ 'sessions' ][ 'subject_id' ] ?? 0 ) );
		$this->assertFalse( (bool)( $tables[ 'sessions' ][ 'show_header' ] ?? true ) );
		$this->assertFalse( (bool)( $tables[ 'activity' ][ 'show_header' ] ?? true ) );
		$this->assertFalse( (bool)( $tables[ 'requests' ][ 'show_header' ] ?? true ) );
		$this->assertArrayNotHasKey( 'full_log_href', $tables[ 'sessions' ] ?? [] );
		$this->assertArrayNotHasKey( 'full_log_href', $tables[ 'activity' ] ?? [] );
		$this->assertArrayNotHasKey( 'full_log_href', $tables[ 'requests' ] ?? [] );

		$routePayload = $this->renderByUserPage( (string)$userId );
		$this->assertRouteRenderOutputHealthy( $routePayload, 'activity by-user route' );
		$this->assertPluginAdminShellRouteState( $routePayload, PluginNavs::SUBNAV_ACTIVITY_BY_USER );
	}

	public function test_no_lookup_route_preloads_user_panel_lookup_form() :void {
		$routePayload = $this->renderByUserPage();
		$this->assertRouteRenderOutputHealthy( $routePayload, 'activity by-user route without lookup' );
		$this->assertPluginAdminShellRouteState( $routePayload, PluginNavs::SUBNAV_ACTIVITY_BY_USER );
	}

	public function test_ip_panel_renders_card_wrapper_status_and_counts_for_related_ip() :void {
		$userId = \get_current_user_id();
		$this->assertGreaterThan( 0, $userId );
		$this->seedRequestLogForUser( $userId, '203.0.113.88' );

		$renderData = (array)( $this->renderByUserPanelBody( (string)$userId )[ 'render_data' ] ?? [] );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$relatedIps = \array_values(
			\array_filter(
				(array)( $renderData[ 'vars' ][ 'related_ips' ] ?? [] ),
				static fn( $card ) :bool => \is_array( $card ) && ( $card[ 'ip' ] ?? '' ) === '203.0.113.88'
			)
		);
		$this->assertCount( 1, $relatedIps );

		$relatedIp = $relatedIps[ 0 ];
		$this->assertGreaterThanOrEqual( 1, (int)( $relatedIp[ 'requests_count' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $relatedIp[ 'status' ] ?? '' ) );

		$query = [];
		\parse_str( (string)\parse_url( (string)( $relatedIp[ 'investigate_href' ] ?? '' ), \PHP_URL_QUERY ), $query );
		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_BY_IP, (string)( $query[ Constants::NAV_SUB_ID ] ?? '' ) );
		$this->assertSame( '203.0.113.88', (string)( $query[ 'analyse_ip' ] ?? '' ) );
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$renderData = (array)( $this->renderByUserPanelBody()[ 'render_data' ] ?? [] );
		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_BY_USER, (string)( $renderData[ 'vars' ][ 'lookup_route' ][ Constants::NAV_SUB_ID ] ?? '' ) );
		$this->assertSame( 'shield-investigate-user-lookup-user_lookup-control', (string)( $renderData[ 'vars' ][ 'lookup_field' ][ 'control_id' ] ?? '' ) );
	}

	public function test_panel_body_action_preserves_user_render_contract() :void {
		$userId = \get_current_user_id();
		$this->assertGreaterThan( 0, $userId );
		$this->seedRequestLogForUser( $userId );

		$panelPayload = $this->renderByUserPanelBody( (string)$userId );
		$this->assertRouteRenderOutputHealthy(
			$panelPayload,
			'investigate by-user panel body action'
		);

		$this->assertSame( '/wpadmin/components/investigate/user_body.twig', (string)( $panelPayload[ 'render_template' ] ?? '' ) );
		$this->assertSame(
			[
				'has_subject'       => true,
				'has_lookup'        => true,
				'subject_not_found' => false,
			],
			\array_intersect_key(
				(array)( $panelPayload[ 'render_data' ][ 'flags' ] ?? [] ),
				[
					'has_subject'       => true,
					'has_lookup'        => true,
					'subject_not_found' => true,
				]
			)
		);
		$this->assertArrayHasKey( 'tables', (array)( $panelPayload[ 'render_data' ][ 'vars' ] ?? [] ) );
	}
}
