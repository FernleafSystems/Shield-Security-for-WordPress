<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\InvestigateByUserPanelBody,
	Actions\Render\PluginAdminPages\PageInvestigateByUser,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as ReqLogsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	LookupRouteFormAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateByUserPageIntegrationTest extends ShieldIntegrationTestCase {

	use LookupRouteFormAssertions, PluginAdminRouteRenderAssertions;

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

	private function renderByUserInnerPage( string $lookup = '' ) :array {
		$params = [
			Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_USER,
		];
		if ( $lookup !== '' ) {
			$params[ 'user_lookup' ] = $lookup;
		}
		return $this->processActionPayloadWithAdminBypass( PageInvestigateByUser::SLUG, $params );
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

		$renderData = (array)( $this->renderByUserInnerPage( (string)$userId )[ 'render_data' ] ?? [] );
		$vars = (array)( $renderData[ 'vars' ] ?? [] );
		$tables = (array)( $vars[ 'tables' ] ?? [] );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertSame( 'sessions', (string)( $tables[ 'sessions' ][ 'table_type' ] ?? '' ) );
		$this->assertSame( 'activity', (string)( $tables[ 'activity' ][ 'table_type' ] ?? '' ) );
		$this->assertSame( 'requests', (string)( $tables[ 'requests' ][ 'table_type' ] ?? '' ) );
		$this->assertSame( 'user', (string)( $tables[ 'sessions' ][ 'subject_type' ] ?? '' ) );
		$this->assertSame( $userId, (int)( $tables[ 'sessions' ][ 'subject_id' ] ?? 0 ) );

		$payload = $this->renderByUserPage( (string)$userId );
		$this->assertRouteRenderOutputHealthy( $payload, 'legacy by-user route' );
		$routeVars = (array)( $payload[ 'render_data' ][ 'vars' ] ?? [] );
		$subjects = [];
		foreach ( (array)( $routeVars[ 'subjects' ] ?? [] ) as $subject ) {
			if ( \is_array( $subject ) && isset( $subject[ 'key' ] ) ) {
				$subjects[ (string)$subject[ 'key' ] ] = $subject;
			}
		}

		$this->assertSame( 'user', (string)( $routeVars[ 'mode_panel' ][ 'active_target' ] ?? '' ) );
		$this->assertTrue( (bool)( $routeVars[ 'mode_panel' ][ 'is_open' ] ?? false ) );
		$this->assertTrue( (bool)( $subjects[ 'user' ][ 'is_loaded' ] ?? false ) );
	}

	public function test_no_lookup_renders_without_investigation_table_markers() :void {
		$payload = $this->renderByUserPage();
		$this->assertRouteRenderOutputHealthy( $payload, 'legacy by-user route without lookup' );
		$routeVars = (array)( $payload[ 'render_data' ][ 'vars' ] ?? [] );
		$subjects = [];
		foreach ( (array)( $routeVars[ 'subjects' ] ?? [] ) as $subject ) {
			if ( \is_array( $subject ) && isset( $subject[ 'key' ] ) ) {
				$subjects[ (string)$subject[ 'key' ] ] = $subject;
			}
		}

		$this->assertSame( 'user', (string)( $routeVars[ 'mode_panel' ][ 'active_target' ] ?? '' ) );
		$this->assertTrue( (bool)( $routeVars[ 'mode_panel' ][ 'is_open' ] ?? false ) );
		$this->assertFalse( (bool)( $subjects[ 'user' ][ 'is_loaded' ] ?? true ) );
	}

	public function test_full_log_links_include_user_search_prefilter() :void {
		$userId = \get_current_user_id();
		$this->assertGreaterThan( 0, $userId );
		$this->seedRequestLogForUser( $userId );

		$renderData = (array)( $this->renderByUserInnerPage( (string)$userId )[ 'render_data' ] ?? [] );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );

		$tables = (array)( $renderData[ 'vars' ][ 'tables' ] ?? [] );
		$searchValues = [];
		foreach ( [ 'sessions', 'activity', 'requests' ] as $tableKey ) {
			$query = [];
			\parse_str(
				(string)\parse_url( (string)( $tables[ $tableKey ][ 'full_log_href' ] ?? '' ), \PHP_URL_QUERY ),
				$query
			);
			if ( isset( $query[ 'search' ] ) ) {
				$searchValues[] = (string)$query[ 'search' ];
			}
		}

		$this->assertContains( 'user_id:'.$userId, $searchValues );
		$this->assertGreaterThanOrEqual( 2, \count( \array_filter(
			$searchValues,
			static fn( string $search ) :bool => $search === 'user_id:'.$userId
		) ) );
	}

	public function test_ip_panel_renders_card_wrapper_status_and_counts_for_related_ip() :void {
		$userId = \get_current_user_id();
		$this->assertGreaterThan( 0, $userId );
		$this->seedRequestLogForUser( $userId, '203.0.113.88' );

		$renderData = (array)( $this->renderByUserInnerPage( (string)$userId )[ 'render_data' ] ?? [] );
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
		$this->assertNotEmpty( (string)( $relatedIp[ 'status_label' ] ?? '' ) );
		$this->assertNotEmpty( (string)( $relatedIp[ 'investigate_href' ] ?? '' ) );

		$query = [];
		\parse_str( (string)\parse_url( (string)( $relatedIp[ 'investigate_href' ] ?? '' ), \PHP_URL_QUERY ), $query );
		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_BY_IP, (string)( $query[ Constants::NAV_SUB_ID ] ?? '' ) );
		$this->assertSame( '203.0.113.88', (string)( $query[ 'analyse_ip' ] ?? '' ) );
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderByUserPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_USER );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_USER );
	}

	public function test_full_page_and_panel_body_actions_share_the_same_user_markup_contract() :void {
		$userId = \get_current_user_id();
		$this->assertGreaterThan( 0, $userId );
		$this->seedRequestLogForUser( $userId );

		$fullHtml = $this->assertRouteRenderOutputHealthy(
			$this->renderByUserInnerPage( (string)$userId ),
			'investigate by-user full page action'
		);
		$this->assertStringContainsString( 'data-inner-page-body-shell="1"', $fullHtml );
		$this->assertStringContainsString( 'data-investigate-subject-header="1"', $fullHtml );

		$panelHtml = $this->assertRouteRenderOutputHealthy(
			$this->renderByUserPanelBody( (string)$userId ),
			'investigate by-user panel body action'
		);
		$this->assertStringContainsString( 'data-investigate-subject-header="1"', $panelHtml );
		$this->assertStringContainsString( 'ShieldInvestigateByUserTabsNav', $panelHtml );
		$this->assertStringNotContainsString( 'data-inner-page-body-shell="1"', $panelHtml );
	}
}
