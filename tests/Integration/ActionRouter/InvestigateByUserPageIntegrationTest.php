<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\PageAdminPlugin,
	Actions\Render\PluginAdminPages\PageInvestigateByUser,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as ReqLogsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\LookupRouteFormAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateByUserPageIntegrationTest extends ShieldIntegrationTestCase {

	use LookupRouteFormAssertions;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'user_meta' );

		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function renderByUserPage( string $lookup = '' ) :array {
		return $this->renderByUserPageAction( PageAdminPlugin::SLUG, $lookup );
	}

	private function renderByUserInnerPage( string $lookup = '' ) :array {
		return $this->renderByUserPageAction( PageInvestigateByUser::SLUG, $lookup );
	}

	private function renderByUserPageAction( string $actionSlug, string $lookup = '' ) :array {
		$filter = self::con()->prefix( 'bypass_is_plugin_admin' );
		add_filter( $filter, '__return_true', 1000 );

		try {
			$params = [
				Constants::NAV_ID     => PluginNavs::NAV_ACTIVITY,
				Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ACTIVITY_BY_USER,
			];
			if ( $lookup !== '' ) {
				$params[ 'user_lookup' ] = $lookup;
			}

			return $this->processor()
						->processAction( $actionSlug, $params )
						->payload();
		}
		finally {
			remove_filter( $filter, '__return_true', 1000 );
		}
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
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );

		$payload = $this->renderByUserPage( (string)$userId );
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertHtmlContainsMarker( 'tab-navlink-user-overview', $html, 'By-user overview rail nav marker' );
		$this->assertHtmlContainsMarker( 'id="tabInvestigateUserOverview"', $html, 'By-user overview tab panel marker' );
		$this->assertSame( 3, \substr_count( $html, 'data-investigation-table="1"' ) );
		$this->assertHtmlContainsMarker( 'data-table-type="sessions"', $html, 'By-user sessions table marker' );
		$this->assertHtmlContainsMarker( 'data-table-type="activity"', $html, 'By-user activity table marker' );
		$this->assertHtmlContainsMarker( 'data-table-type="traffic"', $html, 'By-user traffic table marker' );
	}

	public function test_no_lookup_renders_without_investigation_table_markers() :void {
		$payload = $this->renderByUserPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertHtmlNotContainsMarker( 'data-investigation-table="1"', $html, 'By-user page without lookup' );
	}

	public function test_full_log_links_include_user_search_prefilter() :void {
		$userId = \get_current_user_id();
		$this->assertGreaterThan( 0, $userId );
		$this->seedRequestLogForUser( $userId );

		$renderData = (array)( $this->renderByUserInnerPage( (string)$userId )[ 'render_data' ] ?? [] );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );

		$payload = $this->renderByUserPage( (string)$userId );
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		\preg_match_all( '#href="([^"]+)"#', $html, $hrefMatches );
		$searchValues = [];
		foreach ( $hrefMatches[ 1 ] ?? [] as $href ) {
			$query = [];
			\parse_str(
				(string)\parse_url( \html_entity_decode( (string)$href, \ENT_QUOTES, 'UTF-8' ), \PHP_URL_QUERY ),
				$query
			);
			if ( isset( $query[ 'search' ] ) ) {
				$searchValues[] = (string)$query[ 'search' ];
			}
		}

		$matches = \array_filter(
			$searchValues,
			static fn( string $search ) :bool => $search === 'user_id:'.$userId
		);
		$this->assertGreaterThanOrEqual( 2, \count( $matches ) );
	}

	public function test_ip_panel_renders_card_wrapper_status_and_counts_for_related_ip() :void {
		$userId = \get_current_user_id();
		$this->assertGreaterThan( 0, $userId );
		$this->seedRequestLogForUser( $userId, '203.0.113.88' );

		$renderData = (array)( $this->renderByUserInnerPage( (string)$userId )[ 'render_data' ] ?? [] );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_lookup' ] ?? null );
		$this->assertSame( true, $renderData[ 'flags' ][ 'has_subject' ] ?? null );

		$payload = $this->renderByUserPage( (string)$userId );
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertHtmlContainsMarker( 'investigate-by-user-ip-card', $html, 'By-user IP card wrapper marker' );
		$this->assertHtmlContainsMarker( 'investigate-by-user-ip-status', $html, 'By-user IP status marker' );
		$this->assertHtmlContainsMarker( 'investigate-by-user-ip-counts', $html, 'By-user IP counts marker' );
		$this->assertHtmlContainsMarker( '203.0.113.88', $html, 'By-user seeded related IP display' );
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderByUserPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_USER );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_USER );
	}
}
