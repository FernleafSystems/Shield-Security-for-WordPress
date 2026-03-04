<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\Render\PluginAdminPages\PageInvestigateByUser,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as ReqLogsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	InvestigatePageAssertions,
	LookupRouteFormAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class InvestigateByUserPageIntegrationTest extends ShieldIntegrationTestCase {

	use InvestigatePageAssertions, LookupRouteFormAssertions, PluginAdminRouteRenderAssertions;

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
		$xpath = $this->investigateDomXPath( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigate-landing="1"]',
			'Legacy by-user route renders investigate landing'
		);
		$this->assertXPathExists(
			$xpath,
			'//button[@data-investigate-subject="user" and contains(concat(" ", normalize-space(@class), " "), " is-active ") and @aria-expanded="true"]',
			'Legacy by-user route marks user tile active'
		);
		$this->assertXPathExists(
			$xpath,
			'//section[@data-investigate-panel="user" and @aria-hidden="false"]',
			'Legacy by-user route opens user panel'
		);
		$this->assertHtmlContainsMarker( 'tab-navlink-user-overview', $html, 'By-user overview rail nav marker' );
		$this->assertHtmlContainsMarker( 'id="tabInvestigateUserOverview"', $html, 'By-user overview tab panel marker' );
		$this->assertHtmlContainsMarker( 'User Overview', $html, 'By-user overview heading marker' );
		$this->assertInvestigateOverviewLabel( $xpath, 'Username', 'By-user overview username row marker' );
		$this->assertInvestigateOverviewLabel( $xpath, 'Recent IPs', 'By-user overview recent IPs row marker' );
		$this->assertHtmlNotContainsMarker( 'Back To Investigate', $html, 'By-user back button removed marker' );
		$this->assertHtmlNotContainsMarker( 'investigate-summary-grid', $html, 'By-user summary cards removed marker' );
		$this->assertInvestigateSubjectTypeByCount( $xpath, 'user', 3, 'By-user subject table markers' );
	}

	public function test_no_lookup_renders_without_investigation_table_markers() :void {
		$payload = $this->renderByUserPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->investigateDomXPath( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-investigate-landing="1"]',
			'Legacy by-user route without lookup still renders investigate landing'
		);
		$this->assertXPathExists(
			$xpath,
			'//button[@data-investigate-subject="user" and contains(concat(" ", normalize-space(@class), " "), " is-active ") and @aria-expanded="true"]',
			'Legacy by-user route without lookup keeps user tile active'
		);
		$this->assertHtmlNotContainsMarker( 'data-subject-type="user"', $html, 'By-user page without lookup should not render user subject tables' );
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

		$payload = $this->renderByUserPage( (string)$userId );
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->investigateDomXPath( $html );

		$this->assertXPathExists(
			$xpath,
			'//section[@id="tabInvestigateUserIps"]//a[@data-ip="203.0.113.88"]',
			'By-user seeded related IP link marker'
		);
		$investigateLinkNodes = $xpath->query( '//section[@id="tabInvestigateUserIps"]//a[@href]' );
		$this->assertNotFalse( $investigateLinkNodes, 'By-user investigate IP route query failed.' );

		$investigateLink = null;
		$investigateHref = '';
		foreach ( $investigateLinkNodes as $linkNode ) {
			if ( !$linkNode instanceof \DOMElement ) {
				continue;
			}

			$candidateHref = \html_entity_decode(
				(string)$linkNode->getAttribute( 'href' ),
				\ENT_QUOTES | \ENT_HTML5,
				'UTF-8'
			);
			$query = [];
			\parse_str( (string)\parse_url( $candidateHref, \PHP_URL_QUERY ), $query );
			if (
				(string)( $query[ Constants::NAV_SUB_ID ] ?? '' ) === PluginNavs::SUBNAV_ACTIVITY_BY_IP
				&& (string)( $query[ 'analyse_ip' ] ?? '' ) === '203.0.113.88'
			) {
				$investigateLink = $linkNode;
				$investigateHref = $candidateHref;
				break;
			}
		}

		$this->assertNotNull( $investigateLink, 'By-user investigate IP route marker missing for seeded related IP.' );
		$investigateLinkClass = $investigateLink instanceof \DOMElement
			? (string)$investigateLink->getAttribute( 'class' )
			: '';
		$this->assertStringNotContainsString(
			'offcanvas_ip_analysis',
			$investigateLinkClass,
			'By-user investigate IP action should not reuse offcanvas class'
		);
		$this->assertSame(
			(string)( $relatedIp[ 'investigate_href' ] ?? '' ),
			$investigateHref,
			'By-user investigate href from contract marker'
		);
		$this->assertHtmlNotContainsMarker( 'Back To Investigate', $html, 'By-user back button removed marker' );
	}

	public function test_lookup_form_includes_route_preservation_contract() :void {
		$payload = $this->renderByUserPage();
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$form = $this->extractLookupFormForSubNav( $html, PluginNavs::SUBNAV_ACTIVITY_BY_USER );
		$this->assertLookupFormRouteContract( $form, PluginNavs::SUBNAV_ACTIVITY_BY_USER );
	}
}
